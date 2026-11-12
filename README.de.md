# Timesheet für Dolibarr – Weiterentwicklung (`timesheet_next`)

*English version: [README.md](README.md)*

Weiterentwicklung des Dolibarr-Moduls **„Timesheet“** (Zeiterfassung, Stempeluhr, Genehmigung,
Projektberichte, Abrechnung von Zeiten) von Patrick Delcroix
([github.com/delcroip](https://github.com/delcroip)), angepasst an **Dolibarr 24** und
ergänzt um die Erfassung von **Beginn / Ende / Pause** pro Tag, wie sie im Baugewerbe
(Aufzeichnungspflicht nach § 17 MiLoG) gebraucht wird.

| | |
|---|---|
| Modulversion | **4.6.8** (Basis: 4.6.7) |
| Getestet mit | Dolibarr **24.0.1**, PHP 8.4, MariaDB 11 |
| Lizenz | GPL v3 oder später (wie das Originalmodul, siehe `LICENSE`) |

Die Commit-Historie beginnt mit dem Originalstand 4.6.7. Alle Änderungen dieser Version stehen im
zweiten Commit und lassen sich mit `git diff` nachvollziehen.

---

## Installation / Update

1. Den Inhalt dieses Repositorys nach `htdocs/custom/timesheet/` kopieren, z. B.
   `git clone https://github.com/tronx2100/dolibarr_project_timesheet_next.git timesheet`
   im Verzeichnis `custom/`. **Alle Dateien** übertragen, nicht nur `.php`:
   JS, CSS, Sprach- und SQL-Dateien gehören dazu.
2. In Dolibarr unter *Einstellungen → Module* das Modul **deaktivieren und wieder aktivieren**.
   Erst dabei werden die Menübedingungen neu geschrieben, die neuen Tabellenspalten angelegt
   und die Fremdschlüssel gesetzt. Daten und Benutzerrechte bleiben erhalten (getestet).
3. Browser einmal hart neu laden (Strg+F5, am Handy Seite neu laden). Ab 4.6.8 tragen JS und CSS
   eine Versionsangabe, spätere Updates werden dann automatisch geladen.

Optional vor dem Reaktivieren: verwaiste Datensätze prüfen, an denen neue Fremdschlüssel
scheitern würden (siehe Abschnitt *Datenbank*). Das Aktivieren bricht dabei nie ab, ein
fehlender Schlüssel wird nur ins Dolibarr-Log geschrieben.

---

## Neue Funktion: Beginn / Ende / Pause pro Tag

Für Monteure auf wechselnden Baustellen, die Zeiten (auch nachträglich) mit echter Uhrzeit
erfassen müssen.

- **Popup im gewohnten Raster** (Wochen-/Monatsansicht `Timesheet.php`). Das Raster selbst
  bleibt unverändert schmal, horizontal scrollbar mit fixierten Spalten.
  - **Handy/Tablet:** Tippen auf die Stunden eines Tages öffnet das Popup (große Felder,
    nativer Uhrzeit-Picker).
  - **PC:** Stunden weiterhin direkt eintippen; **Doppelklick** auf das Feld öffnet das Popup
    (Tooltip am Feld). Schließen mit ✕, Esc, Klick daneben oder „Abbrechen“.
- Felder: **Beginn, Ende, Pause, Dauer (netto)** und Notiz. Dauer = Ende − Beginn − Pause,
  wird vorgeschlagen und ist korrigierbar. Arbeit über Mitternacht wird erkannt.
- Prüfung im Browser *und* auf dem Server: Die Dauer darf nicht länger sein als Beginn bis Ende.
- Unter den Stunden steht klein „07:00–16:00“ (nur wenn erfasst), ✎ markiert Tage mit Notiz.
  Auch gesperrte und genehmigte Zellen zeigen die Uhrzeiten.
- Speicherung in zwei neuen Spalten `llx_element_time.timesheet_start` / `timesheet_end`.
  Alte Einträge mit vom Modul erzeugter Ersatz-Uhrzeit werden so nicht mit echten Zeiten
  verwechselt. Zusätzlich wird Dolibarrs `element_datehour` auf den echten Beginn gesetzt, sodass
  die Core-Ansicht *Projekt → Aufgabe → Zeitaufwand* dieselbe Uhrzeit zeigt (auch bei
  unterschiedlichen Zeitzonen von Server und Gerät getestet).
- **Export (TSV/CSV/XLSX)** und **PDF-Bericht** haben Spalten *Beginn* und *Ende*.
- Schalter in den Moduleinstellungen: **„Beginn / Ende erfassen“** (`TIMESHEET_SHOW_STARTEND`,
  Standard an). Fehlen die DB-Spalten (Modul nicht reaktiviert), läuft alles wie vorher, nur
  ohne Popup.

---

## Änderungen in 4.6.8

### Dolibarr-24-Kompatibilität
- **Top-Menü „Timesheet“ war verschwunden:** Dolibarr 24 erlaubt `$conf->global->…` nicht mehr
  in Menübedingungen (`dol_eval`). Alle Bedingungen auf `getDolGlobalInt()` /
  `getDolGlobalString()` umgestellt (`core/modules/modtimesheet.class.php`).
- **Raster blieb leer bei eingeschalteten „Notizen pro Tag“:** `img_picto()` liefert in
  Dolibarr 24 FontAwesome-Spans statt `<img>`, `closeNotes()` brach dadurch ab. Außerdem war das
  Symbol `filenew` unsichtbar (existiert nicht). Eigenes Notiz-Symbol, `closeNotes()` robust.
- `llx_attendance_event` wurde ab Dolibarr 22 **nicht angelegt**: Ein SQL-Kommentar ohne
  Leerzeichen vor `--` wird vom geänderten `run_sql()` nicht mehr entfernt. Die Stempeluhr
  meldete dann Erfolg, speicherte aber nichts. Betraf nur Neuinstallationen.
- Warn- und Fehlermeldungen des Moduls wurden nie angezeigt (`'warning'`/`'error'` statt
  Dolibarrs `'warnings'`/`'errors'`).
- Das JavaScript-Init brach ab, wenn kein Favoriten-Reiter existiert (`defaultOpen`).

### Datenintegrität / Abrechnung
- **Speichern im Raster hat abgerechnete Zeiten entkoppelt:** Seit Dolibarr 20 schreibt der Core
  beim Update `invoice_id`/`invoice_line_id`/`fk_product` aus dem Objekt zurück. Das Modul hat
  diese Werte nicht geladen, deshalb verloren alle Einträge einer Zeile ihre
  Rechnungsverknüpfung, sobald in derselben Zeile ein anderer Tag geändert wurde. Jetzt werden
  sie vor jedem Update geladen.
- **Notizen gesperrter Tage wurden beim Speichern geleert.** Nicht mitgeschickte Notizfelder
  bedeuten jetzt „unverändert“.
- **Abgerechnete Zeiten sind serverseitig gesperrt** (vorher nur in der Oberfläche). Ändern,
  Löschen und Uhrzeiten werden abgewiesen, mit Meldung. `TIMESHEET_UNBLOCK_INVOICED` hebt die
  Sperre weiterhin auf. Bucht die Stempeluhr auf einen bereits abgerechneten Tag, entsteht ein
  neuer, offener Eintrag.
- Urlaub/Feiertage gingen beim Laden aus der Session verloren bzw. lösten einen TypeError aus
  (`Serializable::unserialize()` bekam einen String). Jetzt `__serialize()` / `__unserialize()`,
  damit ist auch die PHP-8.1-Deprecation weg.

### Berichte / Export / PDF
- Export: Spalte „Abrechenbar“ war immer 0 (SQL-Alias `invoicable` vs. `invoiceable`),
  Spalte „Kunde“ enthielt HTML-Link-Code statt des Namens.
- PDF brach ab, wenn kein Firmenlogo hinterlegt ist (`$$pdf`-Tippfehler).
- PDF und Export mit Beginn/Ende (siehe oben).

### Rechte / Menü
- Projekt-Tab „Projektbericht“ prüfte das nicht existierende Recht `report->projet`. Jetzt
  dieselben Rechte wie die Seite selbst: `report->project`, `report->admin` oder
  „alle Projekte lesen“.

### Datenbank (Fremdschlüssel)
- `attendance_event.fk_third_party`: Syntaxfehler und falsche Tabelle (`llx_soc` →
  `llx_societe`).
- `time_approval`: Spaltentippfehler `fk_user_app_suplier`, doppelter Schlüsselname
  (`idac` → `idao`).
- `fk_userid` in `attendance_event` und `project_task_timesheet`: `ON DELETE SET NULL` auf eine
  NOT-NULL-Spalte war unmöglich. Jetzt **`ON DELETE RESTRICT`**: Ein Benutzer mit
  Arbeitszeitnachweisen kann nicht gelöscht, nur deaktiviert werden (Aufbewahrungspflicht).
- `element_time.fk_task_time_approval`: Der Schlüssel kann wegen unterschiedlicher Typen
  (BIGINT UNSIGNED vs. INTEGER) nie angelegt werden. Bewusst deaktiviert, statt die Core-Tabelle
  umzubauen.

Prüfabfrage für verwaiste Datensätze vor dem Reaktivieren (alle Ergebnisse 0 = alle Schlüssel
werden angelegt):

```sql
SELECT 'attendance_event.fk_userid' AS pruefung, COUNT(*) AS verwaist FROM llx_attendance_event a LEFT JOIN llx_user u ON u.rowid=a.fk_userid WHERE u.rowid IS NULL
UNION ALL SELECT 'attendance_event.fk_third_party', COUNT(*) FROM llx_attendance_event a LEFT JOIN llx_societe s ON s.rowid=a.fk_third_party WHERE a.fk_third_party IS NOT NULL AND s.rowid IS NULL
UNION ALL SELECT 'project_task_timesheet.fk_userid', COUNT(*) FROM llx_project_task_timesheet t LEFT JOIN llx_user u ON u.rowid=t.fk_userid WHERE u.rowid IS NULL
UNION ALL SELECT 'time_approval.fk_user_app_supplier', COUNT(*) FROM llx_project_task_time_approval p LEFT JOIN llx_user u ON u.rowid=p.fk_user_app_supplier WHERE p.fk_user_app_supplier IS NOT NULL AND u.rowid IS NULL
UNION ALL SELECT 'time_approval.fk_user_app_other', COUNT(*) FROM llx_project_task_time_approval p LEFT JOIN llx_user u ON u.rowid=p.fk_user_app_other WHERE p.fk_user_app_other IS NOT NULL AND u.rowid IS NULL;
```

### Oberfläche
- Am PC wurde die ganze Seite so breit wie das Monatsraster (~2100 px), die Speichern-Buttons
  rutschten rechts aus dem Bild. Raster-Rahmen mit `contain: inline-size` (scrollt jetzt in sich,
  fixierte Spalten bleiben stehen), Feld „Anmerkungen“ 100 % statt `97vw`.
- Esc und Klick daneben schließen jetzt auch die Notiz-Popups.
- JS/CSS werden mit Versionsangabe geladen (`timesheet.js?<Dateizeit>`,
  `timesheet.css?v=<Modulversion>`). Vorher blieben nach einem Update alte Dateien im
  Browser-Cache.

### Kleinere Korrekturen
- PHP-8.2-Deprecations `${var}` in Strings.
- Warnungen: `Undefined array key` in `ajax.php` und `TimesheetReportProject.php`,
  `GETPOST(…, 'apha')` → `'alpha'`.
- Übersetzungen für alle neuen Texte in de_DE, en_US, fr_FR, es_ES, it_IT.

---

## Dolibarr-Core-Bug in 24.0.1 (nicht Teil des Moduls) – behoben in 24.0.2

**Ab Dolibarr 24.0.2: nichts zu tun.**

Nur Dolibarr 24.0.1: `htdocs/projet/tasks.php` erzeugt bei jedem Aufruf die Warnung
`Undefined array key "search_options_<extrafield>" in extrafields_list_search_sql.tpl.php`,
sobald Aufgaben Extrafields haben (das Modul legt `invoiceable` an), und ignoriert Filter auf
Extrafields. Upstream behoben (Commit 95b43b7, PR #40616) und mit 24.0.2 ausgeliefert
(im Quellcode von 24.0.2 und 24.0.3 geprüft). Besser Dolibarr aktualisieren; wer auf 24.0.1
bleiben muss: `dolibarr-core-patches/projet-tasks-24.0.1-search-options.patch`
(im Dolibarr-Verzeichnis `htdocs/..` mit `patch -p1` anwenden).

---

## Bekannte Punkte / offen

- **Aufgaben ohne Eingabefelder:** Beschreibbar ist eine Zeile nur, wenn der Benutzer in der
  *Aufgabe* als Mitwirkender eingetragen ist. Aufgaben aus öffentlichen Projekten oder mit früher
  gebuchter Zeit werden nur angezeigt; ihre leeren, schreibgeschützten Zellen sind unsichtbar.
  Idee: schreibgeschützte Tage als graue Kästchen darstellen.
- **Urlaubstage** sind nur in der Oberfläche gesperrt, nicht serverseitig (war schon so).
- **Eingereichte Stundenzettel** sind serverseitig nicht gesperrt, nur „genehmigt“.
- **Stempeluhr:** Das JSON vom Browser wird von Dolibarr (`GETPOST` ohne Filter entfernt
  Anführungszeichen) zerstört, die Uhr arbeitet über den Rückfall „letztes Event des
  Benutzers“. Beginn/Ende der Stempeluhr landen (noch) nicht in den neuen Spalten.
- **Kein Änderungsprotokoll** für Stempel- und Zeiteinträge (alte Werte werden überschrieben).
- Stundenzettel-Einträge löschen sich nur, wenn Dauer **und** Notiz leer sind.
- Im eigenen CSS sind Reiter und Suche am Handy im Hochformat ausgeblendet. Ist „Favoriten“ der
  Standard-Reiter, sehen Monteure am Handy nur Favoriten.
- Toter Code: `GetTimeSheetXML` / `timesheetAjax.js`, `$htmlName` in
  `core/ajaxGenericSelectHandler.php`.

---

## Hinweis zur Arbeitszeiterfassung

Das Modul speichert Zeiten dauerhaft und löscht nichts automatisch. Aufbewahrungsfristen (z. B.
mindestens 2 Jahre nach § 16 Abs. 2 ArbZG / § 17 MiLoG, länger bei lohn- oder
abrechnungsrelevanten Unterlagen) und die Löschung nach Fristablauf (DSGVO) liegen beim Betreiber.
Keine Rechtsberatung.
