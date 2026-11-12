<?php

class HolidayCalculatorDE
{
    // Bundesländer-Codes
    public const STATE_BW = 'BW';
    public const STATE_BY = 'BY';
    public const STATE_BE = 'BE';
    public const STATE_BB = 'BB';
    public const STATE_HB = 'HB';
    public const STATE_HH = 'HH';
    public const STATE_HE = 'HE';
    public const STATE_MV = 'MV';
    public const STATE_NI = 'NI';
    public const STATE_NW = 'NW';
    public const STATE_RP = 'RP';
    public const STATE_SL = 'SL';
    public const STATE_SN = 'SN';
    public const STATE_ST = 'ST';
    public const STATE_SH = 'SH';
    public const STATE_TH = 'TH';

    /**
     * Arbeitstage (Mo–Fr, ohne Feiertage) in einem Monat für ein Bundesland.
     */
    public static function getWorkdaysInMonth(int $year, int $month, string $state): int
    {
        if ($month < 1 || $month > 12 || $year < 1900 || $year > 2100) {
            return 0;
        }

        $firstDay  = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $lastDay   = $firstDay->modify('last day of this month');

        $holidaysList = self::getHolidaysForYear($year, $state);      // ['Y-m-d' => 'Name']
        $holidaySet   = array_fill_keys(array_keys($holidaysList), true);

        $workdays = 0;

        for ($d = $firstDay; $d <= $lastDay; $d = $d->modify('+1 day')) {
            $weekday = (int) $d->format('N'); // 1=Mo..7=So
            if ($weekday >= 6) {
                continue; // Wochenende
            }
            $ymd = $d->format('Y-m-d');
            if (isset($holidaySet[$ymd])) {
                continue; // Feiertag
            }
            $workdays++;
        }

        return $workdays;
    }

    /**
     * Arbeitstage zwischen zwei Daten inkl. Grenzen (Mo–Fr, ohne Feiertage).
     */
    public static function getWorkdaysBetween(DateTimeInterface $start, DateTimeInterface $end, string $state): int
    {
        if ($end < $start) {
            return 0;
        }

        $startDay = new DateTimeImmutable($start->format('Y-m-d'));
        $endDay   = new DateTimeImmutable($end->format('Y-m-d'));

        $yearMin = (int) $startDay->format('Y');
        $yearMax = (int) $endDay->format('Y');

        $holidayByYear = [];
        for ($y = $yearMin; $y <= $yearMax; $y++) {
            $holidayByYear[$y] = array_fill_keys(
                array_keys(self::getHolidaysForYear($y, $state)),
                true
            );
        }

        $workdays = 0;
        for ($d = $startDay; $d <= $endDay; $d = $d->modify('+1 day')) {
            $weekday = (int) $d->format('N'); // 1=Mo..7=So
            if ($weekday >= 6) {
                continue;
            }
            $y   = (int) $d->format('Y');
            $ymd = $d->format('Y-m-d');
            if (isset($holidayByYear[$y][$ymd])) {
                continue;
            }
            $workdays++;
        }

        return $workdays;
    }

    /**
     * Feiertage für ein Jahr & Bundesland.
     * Rückgabe: ['Y-m-d' => 'Name', ...]
     */
    public static function getHolidaysForYear(int $year, string $state): array
    {
        $state = strtoupper(trim($state));
        $holidays = [];

        // Ostern
        $easterTs = easter_date($year);
        $easter   = (new DateTimeImmutable())->setTimestamp($easterTs);

        $goodFriday        = $easter->modify('-2 days');
        $easterMonday      = $easter->modify('+1 day');
        $ascensionDay      = $easter->modify('+39 days');
        $whitMonday        = $easter->modify('+50 days');
        $corpusChristi     = $easter->modify('+60 days');

        // Bundesweite Feiertage
        $holidays[self::dateStr($year, 1, 1)]   = 'Neujahr';
        $holidays[$goodFriday->format('Y-m-d')] = 'Karfreitag';
        $holidays[$easterMonday->format('Y-m-d')] = 'Ostermontag';
        $holidays[self::dateStr($year, 5, 1)]   = 'Tag der Arbeit';
        $holidays[$ascensionDay->format('Y-m-d')] = 'Christi Himmelfahrt';
        $holidays[$whitMonday->format('Y-m-d')] = 'Pfingstmontag';
        $holidays[self::dateStr($year, 10, 3)]  = 'Tag der Deutschen Einheit';
        $holidays[self::dateStr($year, 12, 25)] = '1. Weihnachtstag';
        $holidays[self::dateStr($year, 12, 26)] = '2. Weihnachtstag';

        switch ($state) {
            case self::STATE_BW: // Baden-Württemberg
                $holidays[self::dateStr($year, 1, 6)]  = 'Heilige Drei Könige';
                $holidays[self::dateStr($year, 11, 1)] = 'Allerheiligen';
                $holidays[$corpusChristi->format('Y-m-d')] = 'Fronleichnam';
                break;

            case self::STATE_BY: // Bayern
                $holidays[self::dateStr($year, 1, 6)]  = 'Heilige Drei Könige';
                $holidays[self::dateStr($year, 11, 1)] = 'Allerheiligen';
                $holidays[$corpusChristi->format('Y-m-d')] = 'Fronleichnam';
                // Formal nicht überall, aber oft pauschal gerechnet:
                $holidays[self::dateStr($year, 8, 15)] = 'Mariä Himmelfahrt';
                break;

            case self::STATE_BE: // Berlin
                if ($year >= 2019) {
                    $holidays[self::dateStr($year, 3, 8)] = 'Internationaler Frauentag';
                }
                break;

            case self::STATE_BB: // Brandenburg
                $holidays[self::dateStr($year, 10, 31)] = 'Reformationstag';
                // Ostersonntag / Pfingstsonntag sind zwar Feiertage, liegen aber auf Sonntag → für Arbeitstage irrelevant.
                break;

            case self::STATE_HB: // Bremen
            case self::STATE_HH: // Hamburg
            case self::STATE_NI: // Niedersachsen
            case self::STATE_SH: // Schleswig-Holstein
                if ($year >= 2018) {
                    $holidays[self::dateStr($year, 10, 31)] = 'Reformationstag';
                }
                break;

            case self::STATE_HE: // Hessen
                $holidays[$corpusChristi->format('Y-m-d')] = 'Fronleichnam';
                break;

            case self::STATE_MV: // Mecklenburg-Vorpommern
                $holidays[self::dateStr($year, 10, 31)] = 'Reformationstag';
                if ($year >= 2023) {
                    $holidays[self::dateStr($year, 3, 8)] = 'Internationaler Frauentag';
                }
                break;

            case self::STATE_NW: // Nordrhein-Westfalen
                $holidays[$corpusChristi->format('Y-m-d')] = 'Fronleichnam';
                $holidays[self::dateStr($year, 11, 1)] = 'Allerheiligen';
                break;

            case self::STATE_RP: // Rheinland-Pfalz
                $holidays[$corpusChristi->format('Y-m-d')] = 'Fronleichnam';
                $holidays[self::dateStr($year, 11, 1)] = 'Allerheiligen';
                break;

            case self::STATE_SL: // Saarland
                $holidays[$corpusChristi->format('Y-m-d')] = 'Fronleichnam';
                $holidays[self::dateStr($year, 11, 1)] = 'Allerheiligen';
                $holidays[self::dateStr($year, 8, 15)]  = 'Mariä Himmelfahrt';
                break;

            case self::STATE_SN: // Sachsen
                $holidays[self::dateStr($year, 10, 31)] = 'Reformationstag';

                // Buß- und Bettag: Mittwoch zwischen 16.11. und 22.11.
                $nov16     = new DateTimeImmutable(self::dateStr($year, 11, 16));
                $weekday   = (int) $nov16->format('N'); // 1=Mo..7=So
                $offset    = (3 - $weekday + 7) % 7;    // 3=Mittwoch
                $bub       = $nov16->modify("+{$offset} days");
                if ($bub->format('m') === '11' && (int)$bub->format('d') <= 22) {
                    $holidays[$bub->format('Y-m-d')] = 'Buß- und Bettag';
                }
                break;

            case self::STATE_ST: // Sachsen-Anhalt
                $holidays[self::dateStr($year, 1, 6)]  = 'Heilige Drei Könige';
                $holidays[self::dateStr($year, 10, 31)] = 'Reformationstag';
                break;

            case self::STATE_TH: // Thüringen
                $holidays[self::dateStr($year, 10, 31)] = 'Reformationstag';
                if ($year >= 2019) {
                    $holidays[self::dateStr($year, 9, 20)] = 'Weltkindertag';
                }
                break;
        }

        ksort($holidays);
        return $holidays;
    }

    /**
     * Prüft, ob Datum im Bundesland Feiertag ist.
     */
    public static function isHoliday(string $state, DateTimeInterface $date): bool
    {
        $year = (int) $date->format('Y');
        $ymd  = $date->format('Y-m-d');
        $holidays = self::getHolidaysForYear($year, $state);
        return isset($holidays[$ymd]);
    }

    /**
     * Gibt ggf. den Namen eines Feiertags zurück, sonst null.
     */
    public static function getHolidayName(string $state, DateTimeInterface $date): ?string
    {
        $year = (int) $date->format('Y');
        $ymd  = $date->format('Y-m-d');
        $holidays = self::getHolidaysForYear($year, $state);
        return $holidays[$ymd] ?? null;
    }

    private static function dateStr(int $y, int $m, int $d): string
    {
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
}
