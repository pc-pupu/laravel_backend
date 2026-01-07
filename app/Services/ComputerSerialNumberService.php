<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ComputerSerialNumberService
{
    /**
     * Generate next computer serial number for NA applications
     * Handles alphanumeric format (e.g., 200001, 200001A, 200001B, etc.)
     *
     * @return string
     */
    public static function generateNextSerialNumber()
    {
        // Check if any NA application exists with computer_serial_no
        $checkNa = DB::table('housing_online_application')
            ->whereRaw("substring(application_no, 1, 2) = 'NA'")
            ->whereNotNull('computer_serial_no')
            ->exists();

        if (!$checkNa) {
            return '200001';
        }

        // Get max computer_serial_no (alphanumeric) - sort by numeric part, then alphabetic
        $maxSerial = DB::table('housing_online_application')
            ->whereRaw("(substring(application_no, 1, 2) = 'NA' OR substring(application_no, 1, 2) = 'PA')")
            ->whereNotNull('computer_serial_no')
            ->orderByRaw("
                LPAD(regexp_replace(computer_serial_no, '[^0-9]', '', 'g'), 10, '0') DESC,
                regexp_replace(computer_serial_no, '[0-9]', '', 'g') DESC
            ")
            ->value('computer_serial_no');

        if ($maxSerial) {
            // Extract numeric part and increment
            $numPart = preg_replace('/[^0-9]/', '', $maxSerial);
            $alphaPart = preg_replace('/[0-9]/', '', $maxSerial);
            $nextNum = (int)$numPart + 1;
            return $nextNum . $alphaPart;
        }

        return '200001';
    }
}

