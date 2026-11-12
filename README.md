# Timesheet for Dolibarr – continued development (`timesheet_next`)

*Deutsche Fassung: [README.de.md](README.de.md)*

Continuation of the Dolibarr module **"Timesheet"** (time tracking, stopwatch / attendance,
approval workflow, project reports, invoicing of time spent) by Patrick Delcroix
([github.com/delcroip](https://github.com/delcroip)), adapted to **Dolibarr 24** and extended
with **start / end / break per day**, as required for construction workers in Germany
(recording duty under § 17 MiLoG).

| | |
|---|---|
| Module version | **4.6.8** (based on 4.6.7) |
| Tested with | Dolibarr **24.0.1**, PHP 8.4, MariaDB 11 |
| License | GPL v3 or later (same as the original module, see `LICENSE`) |

The commit history starts with the original 4.6.7 state. All changes of this version are in the
second commit and can be reviewed with `git diff`.

---

## Installation / upgrade

1. Copy the content of this repository to `htdocs/custom/timesheet/`, e.g. run
   `git clone https://github.com/tronx2100/dolibarr_project_timesheet_next.git timesheet`
   inside `custom/`. Transfer **all files**, not only `.php`: JS, CSS, language and SQL files
   are required too.
2. In Dolibarr go to *Setup → Modules* and **disable and re-enable** the module. This rewrites
   the menu conditions, creates the new table columns and the foreign keys. Data and user
   permissions are kept (tested).
3. Hard-reload the browser once (Ctrl+F5, on phones reload the page). From 4.6.8 on, JS and CSS
   are loaded with a version parameter, so later updates are picked up automatically.

Optional before re-enabling: check for orphaned records that would prevent new foreign keys
(see *Database* below). Enabling the module never fails because of this; a missing key is only
written to the Dolibarr log.

---

## New feature: start / end / break per day

For workers on changing construction sites who have to record their time with the real clock
time, also afterwards (no internet on site).

- **Popup in the existing grid** (week/month view `Timesheet.php`). The grid itself stays as
  compact as before, horizontally scrollable with fixed columns.
  - **Phone/tablet:** tapping the hours of a day opens the popup (large fields, native time
    picker).
  - **Desktop:** hours can still be typed directly; **double click** on the field opens the popup
    (tooltip on the field). Close with ✕, Esc, click outside or "Cancel".
- Fields: **start, end, break, duration (net)** and note. Duration = end − start − break, is
  proposed and can be corrected. Work over midnight is detected.
- Check in the browser *and* on the server: the duration must not be longer than start to end.
- Below the hours a small "07:00–16:00" is shown (only if entered), ✎ marks days with a note.
  Locked and approved cells show the times as well.
- Stored in two new columns `llx_element_time.timesheet_start` / `timesheet_end`, so old entries
  with a generated hour are not mistaken for real times. In addition Dolibarr's
  `element_datehour` is set to the real start, so the core view *Project → Task → Time spent*
  shows the same time (tested with different time zones on server and device).
- **Export (TSV/CSV/XLSX)** and **PDF report** have *start* and *end* columns.
- Setting in the module setup: **"Record start / end"** (`TIMESHEET_SHOW_STARTEND`, on by
  default). If the DB columns are missing (module not re-enabled), everything works as before,
  just without the popup.

---

## Changes in 4.6.8

### Dolibarr 24 compatibility
- **Top menu "Timesheet" was gone:** Dolibarr 24 no longer allows `$conf->global->…` in menu
  conditions (`dol_eval`). All conditions now use `getDolGlobalInt()` / `getDolGlobalString()`
  (`core/modules/modtimesheet.class.php`).
- **Grid stayed empty with "notes per day" enabled:** in Dolibarr 24 `img_picto()` returns
  FontAwesome spans instead of `<img>`, which made `closeNotes()` crash. The `filenew` icon was
  also invisible (it does not exist). Own note icon, robust `closeNotes()`.
- `llx_attendance_event` was **not created** from Dolibarr 22 on: an SQL comment without a space
  before `--` is no longer stripped by the changed `run_sql()`. The stopwatch then reported
  success but saved nothing. Only affected new installations.
- Warning and error messages of the module were never shown (`'warning'`/`'error'` instead of
  Dolibarr's `'warnings'`/`'errors'`).
- The JavaScript init crashed when there is no favourites tab (`defaultOpen`).

### Data integrity / invoicing
- **Saving the grid unlinked invoiced time:** since Dolibarr 20 the core writes
  `invoice_id`/`invoice_line_id`/`fk_product` back from the object on update. The module did not
  load these values, so all entries of a row lost their invoice link as soon as another day of
  the same row was changed. They are now loaded before every update.
- **Notes of locked days were wiped on save.** Note fields that are not posted now mean
  "unchanged".
- **Invoiced time is locked server side** (before: only in the UI). Changes, deletion and times
  are refused with a message. `TIMESHEET_UNBLOCK_INVOICED` still lifts the lock. If the stopwatch
  books on an already invoiced day, a new, not invoiced entry is created.
- Holidays / public holidays were lost when restored from the session or caused a TypeError
  (`Serializable::unserialize()` received a string). Now `__serialize()` / `__unserialize()`,
  which also removes the PHP 8.1 deprecation.

### Reports / export / PDF
- Export: column "Invoiceable" was always 0 (SQL alias `invoicable` vs. `invoiceable`), column
  "Customer" contained HTML link code instead of the name.
- PDF crashed when no company logo is set (`$$pdf` typo).
- PDF and export with start/end (see above).

### Permissions / menu
- Project tab "Project report" checked the non-existing right `report->projet`. It now uses the
  same rights as the page itself: `report->project`, `report->admin` or "read all projects".

### Database (foreign keys)
- `attendance_event.fk_third_party`: syntax error and wrong table (`llx_soc` → `llx_societe`).
- `time_approval`: column typo `fk_user_app_suplier`, duplicate key name (`idac` → `idao`).
- `fk_userid` in `attendance_event` and `project_task_timesheet`: `ON DELETE SET NULL` on a
  NOT NULL column was impossible. Now **`ON DELETE RESTRICT`**: a user with working time records
  cannot be deleted, only disabled (retention duty).
- `element_time.fk_task_time_approval`: the key can never be created because of different types
  (BIGINT UNSIGNED vs. INTEGER). Deliberately disabled instead of altering the core table.

Check for orphaned records before re-enabling (all results 0 = all keys will be created):

```sql
SELECT 'attendance_event.fk_userid' AS check_name, COUNT(*) AS orphans FROM llx_attendance_event a LEFT JOIN llx_user u ON u.rowid=a.fk_userid WHERE u.rowid IS NULL
UNION ALL SELECT 'attendance_event.fk_third_party', COUNT(*) FROM llx_attendance_event a LEFT JOIN llx_societe s ON s.rowid=a.fk_third_party WHERE a.fk_third_party IS NOT NULL AND s.rowid IS NULL
UNION ALL SELECT 'project_task_timesheet.fk_userid', COUNT(*) FROM llx_project_task_timesheet t LEFT JOIN llx_user u ON u.rowid=t.fk_userid WHERE u.rowid IS NULL
UNION ALL SELECT 'time_approval.fk_user_app_supplier', COUNT(*) FROM llx_project_task_time_approval p LEFT JOIN llx_user u ON u.rowid=p.fk_user_app_supplier WHERE p.fk_user_app_supplier IS NOT NULL AND u.rowid IS NULL
UNION ALL SELECT 'time_approval.fk_user_app_other', COUNT(*) FROM llx_project_task_time_approval p LEFT JOIN llx_user u ON u.rowid=p.fk_user_app_other WHERE p.fk_user_app_other IS NOT NULL AND u.rowid IS NULL;
```

### User interface
- On desktop the whole page became as wide as the month grid (~2100 px) and the save buttons
  slid off screen to the right. The grid box now uses `contain: inline-size` (it scrolls inside
  its box, fixed columns stay in place), the "Notes" field is 100 % wide instead of `97vw`.
- Esc and click outside now also close the note popups.
- JS/CSS are loaded with a version (`timesheet.js?<file time>`,
  `timesheet.css?v=<module version>`). Before, old files stayed in the browser cache after an
  update.

### Minor fixes
- PHP 8.2 deprecations `${var}` in strings.
- Warnings: `Undefined array key` in `ajax.php` and `TimesheetReportProject.php`,
  `GETPOST(…, 'apha')` → `'alpha'`.
- Translations for all new texts in de_DE, en_US, fr_FR, es_ES, it_IT.

---

## Dolibarr core bug in 24.0.1 (not part of the module)

In 24.0.1 `htdocs/projet/tasks.php` logs the warning
`Undefined array key "search_options_<extrafield>" in extrafields_list_search_sql.tpl.php` on
every call as soon as tasks have extrafields (the module adds `invoiceable`), and ignores
filters on extrafields. Fixed upstream in branch 24.0 (commit 95b43b7, PR #40616), shipped with
24.0.2. Until then: `dolibarr-core-patches/projet-tasks-24.0.1-search-options.patch`
(apply with `patch -p1` in the Dolibarr directory that contains `htdocs/`).

---

## Known issues / open points

- **Tasks without input fields:** a row can only be edited if the user is a contributor of the
  *task*. Tasks of public projects or with time booked earlier are only displayed; their empty
  read-only cells are invisible. Idea: show read-only days as grey boxes.
- **Holidays** are only locked in the UI, not on the server (already like that before).
- **Submitted timesheets** are not locked on the server, only "approved" ones.
- **Stopwatch:** the JSON sent by the browser is destroyed by Dolibarr (`GETPOST` without filter
  strips quotes), the stopwatch works through the fallback "last event of the user". Start/end
  of the stopwatch are not (yet) written to the new columns.
- **No change log / audit trail** for stopwatch and time entries (old values are overwritten).
- Timesheet entries are only deleted when duration **and** note are empty.
- The local CSS hides tabs and search on phones in portrait mode. If "Favourites" is the default
  tab, workers on phones only see their favourites.
- Dead code: `GetTimeSheetXML` / `timesheetAjax.js`, `$htmlName` in
  `core/ajaxGenericSelectHandler.php`.

---

## Note on working time recording

The module stores time permanently and deletes nothing automatically. Retention periods (e.g.
at least 2 years under German § 16 (2) ArbZG / § 17 MiLoG, longer for payroll or invoicing
relevant records) and deletion after expiry (GDPR) are the operator's responsibility. Not legal
advice.
