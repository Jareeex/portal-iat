<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/** Membaca masa berlaku seperti "6 Months", "2 Yrs", "90 Days", "06/12 Months" (ambil angka pertama). */
class Validity
{
    public static function addTo(?CarbonInterface $from, ?string $validity): ?Carbon
    {
        if (! $from || ! $validity) {
            return null;
        }

        $date = Carbon::instance($from);
        if (preg_match('/(\d+)\s*(?:\/\s*\d+\s*)?(?:months?|bulan)/i', $validity, $m)) {
            return $date->addMonthsNoOverflow((int) $m[1]);
        }
        if (preg_match('/(\d+)\s*(?:yrs?|years?|tahun)/i', $validity, $m)) {
            return $date->addYearsNoOverflow((int) $m[1]);
        }
        if (preg_match('/(\d+)\s*(?:days?|hari)/i', $validity, $m)) {
            return $date->addDays((int) $m[1]);
        }

        return null;
    }
}
