<?php
/* Copyright (C) 2010-2012 Regis Houssin  <regis.houssin@capnetworks.com>
 * Copyright (C) 2018      Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2022-2025 Pierre Ardoin <mapiolca@me.com>
 *
 * This program is free software;you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation;either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY;without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */
/**
 *        \file       htdocs/core/modules/project/doc/pdf_rat.modules.php
 *        \ingroup    project
 *        \brief      File of class to generate project document Baleine
 *        \author     Regis Houssin
 */
require_once './class/TimesheetReport.class.php';
require_once './class/HoliDayCalculatorDE.class.php';
require_once './core/modules/modules_timesheet.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';


class pdf_rat extends ModelPDFTimesheetReport
{
    public $emetteur;
    public $posxworker;
    public $posxdate;
    public $posxduration;
    public $posxstart;
    public $posxend;
    public $showStartEnd = false;

    public function __construct($db)
    {
        global $conf, $langs, $mysoc;
        $langs->load("main");
        $langs->load("projects");
        $langs->load("companies");
        $this->noteISOtask=getConf('TIMESHEET_PDF_NOTEISOTASK');
        $this->db = $db;
        $this->name = "rat";
        $this->description = $langs->trans("DocumentModelRat");
        $this->type = 'pdf';
        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur, $this->page_hauteur);
        $this->marge_gauche=getConf('MAIN_PDF_MARGIN_LEFT',10);
        $this->marge_droite=getConf('MAIN_PDF_MARGIN_RIGHT',10);
        $this->marge_haute = getConf('MAIN_PDF_MARGIN_TOP',10);
        $this->marge_basse = getConf('MAIN_PDF_MARGIN_BOTTOM',10);
        $this->option_logo = 1;
        $this->option_tva = 1;
        $this->option_codeproduitservice = 1;
        $this->emetteur=$mysoc;
        if (!$this->emetteur->country_code) $this->emetteur->country_code=substr($langs->defaultlang, -2);
        $this->posxref=$this->marge_gauche+1;
        $this->posxdate=$this->marge_gauche+9;
        $this->posxlabel=$this->marge_gauche+33;
        $this->posxduration=$this->marge_gauche+175;
        if ($this->page_largeur < 210) {
            $this->posxref-=20;
            $this->posxlabel-=20;
            $this->posxdate-=20;
            $this->posxduration-=20;
        }
        // Start / end of work (Beginn / Ende) columns between task and duration
        $this->showStartEnd = timesheetUseStartEnd($db);
        if ($this->showStartEnd) {
            $this->posxstart = $this->posxduration - 30;
            $this->posxend = $this->posxduration - 15;
        }
    }

    public function writeFile($object, $outputlangs)
    {
        global $conf, $hookmanager, $langs, $user;
        if (!is_object($outputlangs)) $outputlangs = $langs;
        if (getConf('MAIN_USE_FPDF') == 1) $outputlangs->charset_output='ISO-8859-1';
        $outputlangs->load("main");
        $outputlangs->load("dict");
        $outputlangs->load("companies");
        $outputlangs->load("projects");
        $outputlangs->load("timesheet@timesheet");

        if ($conf->projet->dir_output) {
            $objectref = dol_sanitizeFileName($object->name);
            $dir = $conf->timesheet->dir_output.'/reports/';
            $file = $dir."/".$objectref.".pdf";
            if (!file_exists($dir)) {
                if (dol_mkdir($dir) < 0) {
                    $this->error=$langs->transnoentities("ErrorCanNotCreateDir", $dir);
                    return 0;
                }
            } else {
                if (!is_object($hookmanager)) {
                    include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
                    $hookmanager = new HookManager($this->db);
                }
                $hookmanager->initHooks(array('pdfgeneration'));
                $parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
                global $action;
                $reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);
                $pdf = pdf_getInstance($this->format);
                $pdf->SetAutoPageBreak(1, 0);
                if (class_exists('TCPDF')) {
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                }
                $pdf->SetFont(pdf_getPDFFont($outputlangs));
                if (getConf('MAIN_DISABLE_FPDI') == false && getConf('MAIN_ADD_PDF_BACKGROUND') == 1) {
                    $pagecount = $pdf->setSourceFile($conf->mycompany->dir_output.'/'.getConf('MAIN_ADD_PDF_BACKGROUND'));
                    $tplidx = $pdf->importPage(1);
                }

                $tasktimearray = $object->getReportArray(!($object->ungroup == 1));
                $TotalLines = array();
                $userTaskArray = array();

                foreach ($tasktimearray as $line) {
                    $projectid = $line['projectId'];
                    if (!isset($userTaskArray[$projectid])) $userTaskArray[$projectid] = array();
                    if (!isset($userTaskArray[$projectid][$line['userId']])) $userTaskArray[$projectid][$line['userId']] = array('lines' => array());
                    if (!isset($TotalLines[$projectid])) $TotalLines[$projectid] = array();
                    if (!isset($TotalLines[$projectid][$line['userId']])) $TotalLines[$projectid][$line['userId']] = 0;
                    if (!isset($TotalLines[$projectid]['Total'])) $TotalLines[$projectid]['Total'] = 0;

                    $userTaskArray[$projectid][$line['userId']]['lines'][] = $line;
                    $TotalLines[$projectid][$line['userId']] += $line['duration'];
                    $TotalLines[$projectid]['Total'] += $line['duration'];
                }

                foreach ($userTaskArray as $projectid => $userArray){
                    foreach ($userArray as $userid => $taskArray) {
                        $userTaskArray[$projectid][$userid]['Total'] = formatTime($TotalLines[$projectid][$userid], -2);
                    }
                }

                $userActualSec = array();
                $userDaysSet = array();
                foreach ($userTaskArray as $projectid => $userArray) {
                    foreach ($userArray as $userid => $taskArray) {
                        if (!isset($userActualSec[$userid])) $userActualSec[$userid]=0;
                        $userActualSec[$userid] += (int)$TotalLines[$projectid][$userid];
                        if (!isset($userDaysSet[$userid])) $userDaysSet[$userid]=array();
                        if (!empty($taskArray['lines'])) {
                            foreach ($taskArray['lines'] as $ln) {
                                if (!empty($ln['date'])) {
                                    $dayKey = dol_print_date($ln['date'], '%Y-%m-%d');
                                    $userDaysSet[$userid][$dayKey] = true;
                                }
                            }
                        }
                    }
                }
                
$invoiceableOnly = GETPOST('invoicabletaskOnly', 'int');
if (!empty($invoiceableOnly) && isset($invoiceableOnly)) {
    
                $hoursPerDayConf = (float)getConf('TIMESHEET_DAY_DURATION', 8);
                if ($hoursPerDayConf <= 0) $hoursPerDayConf = 8.0;

                $userExpectedSec = array();
                foreach ($userActualSec as $uid => $act) {
                    if (!isset($object->user[$uid])) {
                        $object->user[$uid] = new User($this->db);
                        $object->user[$uid]->fetch($uid);
                    }
                    $weekly = isset($object->user[$uid]->weeklyhours) ? (float)$object->user[$uid]->weeklyhours : 0.0;
                    $daysPerWeek = 5;
                    if (isset( $object->user) && isset($object->user[$uid]->array_options['options_days_per_week']) &&  $object->user[$uid]->array_options['options_days_per_week'] > 0) {
                        $daysPerWeek = (float)  $object->user[$uid]->array_options['options_days_per_week'];
                    }
                    $perDayHours = ($weekly > 0.0 && $daysPerWeek > 0) ? ($weekly / $daysPerWeek) : $hoursPerDayConf;

                    $workedDays = isset($userDaysSet[$uid]) ? count($userDaysSet[$uid]) : 0;
                    $userExpectedSec[$uid] = (int)round($perDayHours * 3600 * $workedDays);
                }


                foreach ($userTaskArray as $projectid => $userArray) {
                    foreach ($userArray as $userid => $taskArray) {
                        $actualSec = (int)$TotalLines[$projectid][$userid];
                        $totalUserActual = max(0, (int)($userActualSec[$userid] ?? 0));
                        $expectedUser = max(0, (int)($userExpectedSec[$userid] ?? 0));
                        $expectedProjectSec = ($totalUserActual > 0) ? (int)round($expectedUser * ($actualSec / $totalUserActual)) : 0;
                        $deltaSec = $actualSec - $expectedProjectSec;
                        $sign = ($deltaSec > 0) ? '+' : (($deltaSec < 0) ? '-' : '');
                        $deltaHours = number_format(abs($deltaSec) / 3600, 1, '.', '');
                        $rgb = ($deltaSec > 0) ? array(211,47,47) : (($deltaSec < 0) ? array(25,118,210) : array(0,0,0));
                        $userTaskArray[$projectid][$userid]['TotalDelta'] = array(
                            'text' => ', '.$sign.$deltaHours,
                            'rgb'  => $rgb
                        );
                    }
                }
                
}

                $pdf->Open();
                $pagenb = 0;
                $pdf->SetDrawColor(128, 128, 128);
                $pdf->SetTitle($outputlangs->convToOutputCharset($object->name));
                $pdf->SetSubject($outputlangs->transnoentities("ProjectTimeReport"));
                $pdf->SetCreator("Dolibarr ".DOL_VERSION);
                $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
                $pdf->SetKeyWords(implode(',', $object->ref));
                if (getConf('MAIN_DISABLE_PDF_COMPRESSION') == 1) $pdf->SetCompression(false);
                $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
                $tplidx = $tplidx ?? '';
                foreach ($userTaskArray as $projectid => $userArray){
                    foreach ($userArray as $userid => $taskArray) {
                        $pagenb++;
                        $pagenb = $this->writeUser($pdf, $tplidx, $object, $outputlangs, $pagenb, $taskArray, $projectid);
                    }
                }
                $pdf->Close();
                $pdf->Output($file, 'F');
                $hookmanager->initHooks(array('pdfgeneration'));
                $parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
                $reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);
                if (getConf('MAIN_UMASK') != false) @chmod($file, octdec(getConf('MAIN_UMASK')));
                $this->result = array('fullpath'=>$file);
                return 1;
            }
        } else {
            $this->error=$langs->transnoentities("ErrorConstantNotDefined", "PROJECT_OUTPUTDIR");
            return 0;
        }
    }

    public function writeUser(&$pdf, $tplidx, $object, $outputlangs, $pagenb, $tasktimearray, $projectid)
    {
        global $conf,$langs;
        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $tab_height_newpage = (getConf('MAIN_PDF_DONOTREPEAT_HEAD') == false?170:202);
        $HeightSignBox = 30;
        $heightforlastfooter = (getConf('TIMESHEET_PDF_HIDE_SIGNBOX') == 1)?7:($HeightSignBox + 7);
        $heightforfooter = $this->marge_basse+1;
        $pageposbefore = 0;
        $heightoftitleline = 6;
        $bottomlasttab = $this->page_hauteur - $heightforlastfooter - $heightforfooter + 1;
        $widthSignBox = ($this->page_largeur-$this->marge_gauche-$this->marge_droite)/2-1;

        $pdf->AddPage();
        $pdf->setPage($pagenb);
        if (!empty($tplidx)) $pdf->useTemplate($tplidx);
        $pdf->setPageOrientation('', 1, $heightforfooter);
        $starty = $this->pageHead($pdf, $object, 1, $outputlangs, $projectid, $tasktimearray['lines'][0]['userName']);
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->MultiCell(0, 3, '');
        $pdf->SetTextColor(0, 0, 0);
        $nexY = $starty + $heightoftitleline + 1;
        $nblignes = count($tasktimearray['lines']);

        for ($i = 0 ;$i < $nblignes ;$i++) {
            $curY = ($i == 0)? $nexY:$nexY+2;
            $pageposbefore = $pdf->getPage();
            $posybefore = $curY;
            $tasktimearray['lines'][$i]['ref']=$i;
            $pdf->startTransaction();
            $posyafter = $this->writeLine($pdf, $tasktimearray['lines'][$i], $curY, $outputlangs);
            $pageposafter = $pdf->getPage();
            $addpagebreak = false;
            $lineheight = $posyafter-$posybefore;
            if ($pageposafter>$pageposbefore) {
                $addpagebreak = true;
            } elseif ($posyafter>$this->page_hauteur - $heightforfooter - $heightforlastfooter) {
                if ($posyafter >($this->page_hauteur -($heightforfooter))) {
                    $addpagebreak = true;
                } elseif (($nblignes-$i)*($lineheight)< $this->page_hauteur - $heightforfooter - $posyafter) {
                    $addpagebreak = true;
                }
            } elseif (isset($tasktimearray['lines'][$i+1]['pagebreak']) && $tasktimearray['lines'][$i+1]['pagebreak']) {
                $addpagebreak = true;
            }

            if ($addpagebreak == true) {
                $starty=10;
                $pdf->rollbackTransaction(true);
                $pageposafter = $pageposbefore+1;
                $pdf->AddPage();
                if (!empty($tplidx)) $pdf->useTemplate($tplidx);
                $pdf->setPage($pageposafter);
                if (getConf('MAIN_PDF_DONOTREPEAT_HEAD') == false) $starty = $this->pageHead($pdf, $object, 1, $outputlangs, $projectid, $tasktimearray['lines'][0]['userName']);
                $curY = $starty + $heightoftitleline + 1;
                $pdf->SetFont('', '', $default_font_size - 1);
                $pdf->MultiCell(0, 3, '');
                $pdf->SetTextColor(0, 0, 0);
                $pdf->setPageOrientation('', 1, $heightforfooter);
                $nexY = $this->writeLine($pdf, $tasktimearray['lines'][$i], $curY, $outputlangs);
                $pdf->setPage($pagenb);
                $this->tableau($pdf, $starty, $this->page_hauteur - $starty - $heightforfooter, $heightoftitleline, $outputlangs, 0);
                $pdf->setPage($pagenb);
                $pdf->setPageOrientation('', 1, 0);
                $pagenb++;
                $pdf->setPage($pageposafter);
                $pageposafter = $pdf->getPage();
            } else {
                $pdf->commitTransaction();
                $nexY = $posyafter;
                $pageposafter = $pdf->getPage();
            }
        }

        $this->tableau($pdf, $starty, $this->page_hauteur - $starty - $heightforlastfooter - $heightforfooter, $heightoftitleline, $outputlangs, 0);

$invoiceableOnly = GETPOST('invoicabletaskOnly', 'int');
if (!empty($invoiceableOnly) && isset($invoiceableOnly)) {
// =====================
// Zusatzzeile: Tage-Summary
// =====================

// 1) Erfasste Tage (unique Dates)
$erfassteTage = isset($tasktimearray['lines'])
    ? count(array_unique(array_map(
        fn($l) => dol_print_date($l['date'], '%Y-%m-%d'),
        $tasktimearray['lines']
    )))
    : 0;

// 2) Arbeitstage (Soll) für Zeitraum dieses Users
$startDate = new DateTimeImmutable(date('Y-m-d', $object->startDate));
$endDate   = new DateTimeImmutable(date('Y-m-d', $object->stopDate));


$const1 = getDolGlobalString('MAIN_INFO_SOCIETE_STATE');  // z.B. "368:BB:Brandenburg"
$state = explode(':', (string) $const1)[1] ?? 'BB'; // Brandenburg Default

// Soll-Arbeitstage ermitteln
$arbeitstage = HolidayCalculatorDE::getWorkdaysBetween($startDate, $endDate, $state);

// 3) Differenz
$diffTage = $erfassteTage - $arbeitstage;

// 4) Darstellung
$summaryText = sprintf(
    $langs->trans('collectedDays').': %d / '.$langs->trans('workDays').': %d / '.$langs->trans('difference').': %d',
    $erfassteTage,
    $arbeitstage,
    $diffTage
);

// Textposition (direkt über dem Total/Signbox-Bereich)
$pdf->SetFont('', '', $default_font_size - 1);
$pdf->writeHTMLCell(
    120,
    4,
    $this->marge_gauche,
    $bottomlasttab - 6,
    $summaryText,
    0,
    1,
    0,
    true,
    'L'
);
// =====================
}


        if (getConf('TIMESHEET_PDF_HIDE_SIGNBOX') == 1) {
            $unitLabel = (getConf('TIMESHEET_INVOICE_TIMETYPE','days') == "days") ? $outputlangs->transnoentities('Days') : $outputlangs->transnoentities('Hours');
            $txtTotalBase = $tasktimearray['Total']." ".$unitLabel;
            $deltaText = '';
            $rgb = array(0,0,0);
            if (!empty($tasktimearray['TotalDelta']['text'])) $deltaText = $tasktimearray['TotalDelta']['text'];
            if (!empty($tasktimearray['TotalDelta']['rgb']) && is_array($tasktimearray['TotalDelta']['rgb'])) $rgb = $tasktimearray['TotalDelta']['rgb'];
            $colorCSS = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
            $txtTotalWithDelta = $txtTotalBase.' <span style="color:'.$colorCSS.'">'.$deltaText.'</span>';
            $txtTotal = $txtTotalWithDelta;
            $pdf->writeHTMLCell(60, 3, $this->page_largeur-$this->marge_droite-60, $bottomlasttab, $outputlangs->transnoentities('Total').": ", 0, 1, 0, true, 'L');
            $pdf->writeHTMLCell(60, 3, $this->page_largeur-$this->marge_droite-60, $bottomlasttab, $txtTotal, 0, 1, 0, true, 'R');
            $pdf->Rect($this->page_largeur-$this->marge_droite-60, $bottomlasttab, 60, 5);
        } else {
            $unitLabel = (getConf('TIMESHEET_INVOICE_TIMETYPE','days') == "days") ? $outputlangs->transnoentities('Days') : $outputlangs->transnoentities('Hours');
            $txtTotalBase = $tasktimearray['Total']." ".$unitLabel;
            $deltaText = '';
            $rgb = array(0,0,0);
            if (!empty($tasktimearray['TotalDelta']['text'])) $deltaText = $tasktimearray['TotalDelta']['text'];
            if (!empty($tasktimearray['TotalDelta']['rgb']) && is_array($tasktimearray['TotalDelta']['rgb'])) $rgb = $tasktimearray['TotalDelta']['rgb'];
            $colorCSS = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
            $txtTotalWithDelta = $txtTotalBase.' <span style="color:'.$colorCSS.'">'.$deltaText.'</span>';
            $pdf->SetFont('', 'B', $default_font_size);
            $txtTotal = $txtTotalWithDelta;
            $pdf->writeHTMLCell(60, 3, $this->page_largeur-$this->marge_droite-60, $bottomlasttab, $outputlangs->transnoentities('Total').": ", 0, 1, 0, true, 'L');
            $pdf->writeHTMLCell(60, 3, $this->page_largeur-$this->marge_droite-60, $bottomlasttab, $txtTotal, 0, 1, 0, true, 'R');
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->writeHTMLCell(80, 3, $this->marge_gauche+1, $bottomlasttab+7, $outputlangs->transnoentities('employeeSignature'), 0, 1);
            $pdf->writeHTMLCell(80, 3, $this->marge_gauche+$widthSignBox+2, $bottomlasttab+7, $outputlangs->transnoentities('customerSignature'), 0, 1);
            $nexY = $pdf->GetY();
            $pdf->SetDrawColor(192, 192, 192);
            $pdf->Rect($this->page_largeur-$this->marge_droite-60, $bottomlasttab, 60, 5);
            $pdf->Rect($this->marge_gauche, $bottomlasttab+6, $widthSignBox, $HeightSignBox);
            $pdf->Rect($this->marge_gauche+$widthSignBox+2, $bottomlasttab+6, $widthSignBox, $HeightSignBox);
        }

        return $pagenb;
    }

    public function writeLine(&$pdf, $line, $curY, $outputlangs)
    {
        global $conf;
        $ref = $line['ref'];
        $libelleline = "";
        switch($this->noteISOtask) {
            case 2:
                $libelleline = $line['taskLabel'].(empty($line['note'])?'':': '.$line['note']);
                break;
            case 1:
                $libelleline = $line['note'];
                break;
            case 0:
            default:
                $libelleline = $line['taskLabel'];
        }
        $userName = $line['userName'];
        $date = dol_print_date($line['date'], 'day');
        $duration = formatTime($line['duration'], -2);
        $pdf->SetXY($this->posxref, $curY);
        $pdf->MultiCell($this->posxdate-$this->posxref, 0, dol_string_nohtmltag($ref), 0, 'L');
        $nexY = $pdf->GetY();
        $pdf->SetXY($this->posxdate, $curY);
        $pdf->MultiCell($this->posxlabel-$this->posxdate, 0, $date, 0, 'L');
        $nexY = max($nexY, $pdf->GetY());
        $labelEnd = $this->showStartEnd ? $this->posxstart : $this->posxduration;
        $pdf->SetXY($this->posxlabel, $curY);
        $pdf->MultiCell($labelEnd-$this->posxlabel, 3, dol_string_nohtmltag($libelleline), 0, 'L');
        $nexY = max($nexY, $pdf->GetY());
        if ($this->showStartEnd) {
            $pdf->SetXY($this->posxstart, $curY);
            $pdf->MultiCell($this->posxend-$this->posxstart, 3, isset($line['timeStart']) ? $line['timeStart'] : '', 0, 'C');
            $pdf->SetXY($this->posxend, $curY);
            $pdf->MultiCell($this->posxduration-$this->posxend, 3, isset($line['timeEnd']) ? $line['timeEnd'] : '', 0, 'C');
        }
        $pdf->SetXY($this->posxduration, $curY);
        $pdf->MultiCell($this->page_largeur-$this->marge_droite-$this->posxduration, 3, $duration, 0, 'R');
        $nexY = max($nexY, $pdf->GetY());
        if (!empty($conf->global->MAIN_PDF_DASH_BETWEEN_LINES)) {
            $pdf->SetLineStyle(array('dash' => '1, 1', 'color' => array(80, 80, 80)));
            $pdf->line($this->marge_gauche, $nexY+1, $this->page_largeur - $this->marge_droite, $nexY+1);
            $pdf->SetLineStyle(array('dash' => 0));
        }
        return $nexY;
    }

    public function tableau(&$pdf, $tab_top, $tab_height, $heightoftitleline, $outputlangs, $hidetop = 0)
    {
        global $conf;
        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $pdf->SetDrawColor(128, 128, 128);
        $pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $tab_height);
        if ($hidetop == 0){
            $pdf->line($this->marge_gauche, $tab_top+$heightoftitleline, $this->page_largeur-$this->marge_droite, $tab_top+$heightoftitleline);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('', 'B', $default_font_size);
            $pdf->SetXY($this->posxref, $tab_top+1);
            $pdf->MultiCell($this->posxdate-$this->posxref, 3, "#", '', 'L');
            $pdf->SetXY($this->posxdate, $tab_top+1);
            $pdf->MultiCell($this->posxlabel-$this->posxdate, 3, $outputlangs->transnoentities('Date'), 0, 'L');
            $pdf->SetXY($this->posxlabel, $tab_top+1);
            $libelleline = "";
            switch($this->noteISOtask) {
                case 2:
                    $libelleline = $outputlangs->transnoentities("Task").':'.$outputlangs->transnoentities("Note");
                    break;
                case 1:
                    $libelleline = $outputlangs->transnoentities("Note");
                    break;
                case 0:
                default:
                    $libelleline = $outputlangs->transnoentities("Task");
            }
            $pdf->MultiCell(($this->showStartEnd ? $this->posxstart : $this->posxduration)-$this->posxlabel, 3, $libelleline, 0, 'L');
            if ($this->showStartEnd) {
                $pdf->SetXY($this->posxstart, $tab_top+1);
                $pdf->MultiCell($this->posxend-$this->posxstart, 3, $outputlangs->transnoentities('TimesheetStart'), 0, 'C');
                $pdf->SetXY($this->posxend, $tab_top+1);
                $pdf->MultiCell($this->posxduration-$this->posxend, 3, $outputlangs->transnoentities('TimesheetEnd'), 0, 'C');
            }
            $pdf->SetXY($this->posxduration, $tab_top+1);
            if (getConf('TIMESHEET_INVOICE_TIMETYPE','days') == "hours") {
                $pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxduration, 3, 'h:m', 0, 'R');
            } else{
                $pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxduration, 3, $outputlangs->transnoentities("Days"), 0, 'R');
            }
        }
    }

    public function pageHead(&$pdf, $object, $showaddress, $outputlangs, $projectid, $userName = "")
    {
        global $langs, $conf, $mysoc, $db;

        $project = new Project($db);
        $project->fetch($projectid);
        $project->fetch_thirdparty();

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->SetFont('', 'B', $default_font_size + 3);
        $posx = $this->page_largeur-$this->marge_droite-100;
        $posy = $this->marge_haute;
        $pdf->SetXY($this->marge_gauche, $posy);
        $image_height = 0;
        $height = $default_font_size + 3;
        $logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
        $logoWidth = 0;
        if ($mysoc->logo) {
            if (is_readable($logo)) {
                $image_height = pdf_getHeightForLogo($logo);
                $tmp = dol_getImageSize($logo, '');
                $logoWidth = $tmp['width']>130?130:$tmp['width'];
                $pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);
            } else {
                $pdf->SetTextColor(200, 0, 0);
                $pdf->SetFont('', 'B', $default_font_size - 2);
                $pdf->MultiCell(100, 3, $langs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
                $pdf->MultiCell(100, 3, $langs->transnoentities("ErrorGoToModuleSetup"), 0, 'L');
            }
            $posy_l =  $pdf->GetY() + 1 ;
        } else {
            $pdf->MultiCell(100, 4, $outputlangs->transnoentities($this->emetteur->name), 0, 'L');
            if ($showaddress == true){
                $pdf->MultiCell(100, $pdf->GetY() + 1, $outputlangs->transnoentities($mysoc->address), 0, 'L');
                $pdf->MultiCell(100, $pdf->GetY() + 1, $outputlangs->transnoentities($mysoc->zip.' - '.$mysoc->town), 0, 'L');
            }
            $posy_l =  $pdf->GetY() + 1 ;
        }

        $pdf->SetFont('', 'B', $default_font_size +1);
        $pdf->SetXY($this->marge_gauche+$logoWidth, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->MultiCell($this->page_largeur - $this->marge_gauche -  $this->marge_droite  - $logoWidth, 4, $outputlangs->convToOutputCharset($project->ref." - ".$project->title), '', 'R');
        $pdf->SetFont('', '', $default_font_size);
        $posy = $pdf->GetY() + 1;

        $pdf->SetXY($posx, $posy);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->MultiCell(100, 4, $outputlangs->transnoentities("DateStart")." : " . dol_print_date($object->startDate, 'day', false, $outputlangs, true), '', 'R');
        $posy = $pdf->GetY() + 1;

        $pdf->SetXY($posx, $posy);
        $pdf->MultiCell(100, 4, $outputlangs->transnoentities("DateEnd")." : " . dol_print_date($object->stopDate, 'day', false, $outputlangs, true), '', 'R');
        $posy = $pdf->GetY() + 1;

        if (!empty($project->thirdparty->id) && $project->thirdparty->id > 0) {
            $pdf->SetXY($posx, $posy);
            $pdf->MultiCell(100, 4, $outputlangs->transnoentities("ThirdParty")." : " . $project->thirdparty->getFullName($outputlangs), '', 'R');
            $posy = $pdf->GetY() + 1;
        }
        $pdf->SetTextColor(0, 0, 60);
        $posy = max($posy, $posy_l);
        if (!empty($userName) && getConf('TIMESHEET_PDF_HIDE_NAME') == false) {
            $pdf->SetXY($this->marge_gauche,  $posy);
            $pdf->MultiCell($this->page_largeur - $this->marge_gauche -  $this->marge_droite, 4, $outputlangs->transnoentities('Employee').': '.$outputlangs->convToOutputCharset($userName), 0, 'L');
        }
        return $pdf->GetY() + 1;
    }
}
