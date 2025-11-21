<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ExistingApplicantService
{
    /**
     * Generate username from applicant name
     */
    public static function generateUsername($applicantName, $physicalApplicationNo, $computerSerialNo)
    {
        $wordCount = str_word_count(trim($applicantName));
        $pieces = explode(' ', trim($applicantName));
        
        if ($wordCount < 2) {
            $name = strtolower(substr($pieces[0], 0, 3));
        } elseif ($wordCount == 2) {
            if (in_array(strtolower($pieces[0]), ['dr.', 'dr'])) {
                $name = strtolower(substr($pieces[1], 0, 3));
            } else {
                $name = strtolower(substr($pieces[0], 0, 3)) . strtolower(substr($pieces[1], 0, 3));
            }
        } else {
            if (in_array(strtolower($pieces[0]), ['dr.', 'dr'])) {
                $name = strtolower(substr($pieces[1], 0, 3)) . strtolower(substr($pieces[2], 0, 3));
            } else {
                $name = strtolower(substr($pieces[0], 0, 3)) . strtolower(substr($pieces[1], 0, 3));
            }
        }
        
        $physicalAppNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $physicalApplicationNo);
        return str_replace('.', '', $name) . '_' . $physicalAppNo . '_' . $computerSerialNo;
    }

    /**
     * Convert date from DD/MM/YYYY to YYYY-MM-DD
     */
    public static function convertDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }
        
        $parts = explode('/', $dateString);
        if (count($parts) == 3) {
            return implode('-', array_reverse($parts));
        }
        
        return null;
    }

    /**
     * Get flat type ID from flat type name
     */
    public static function getFlatTypeId($flatType)
    {
        return DB::table('housing_flat_type')
            ->where('flat_type', trim($flatType))
            ->value('flat_type_id') ?? 0;
    }

    /**
     * Validate computer serial no uniqueness
     */
    public static function isComputerSerialNoUnique($computerSerialNo, $excludeId = null)
    {
        $query = DB::table('housing_online_application')
            ->where('computer_serial_no', $computerSerialNo);
            
        if ($excludeId) {
            $query->where('online_application_id', '!=', $excludeId);
        }
        
        return $query->doesntExist();
    }

    /**
     * Validate physical application no uniqueness
     */
    public static function isPhysicalApplicationNoUnique($physicalApplicationNo, $excludeId = null)
    {
        $query = DB::table('housing_online_application')
            ->where('physical_application_no', $physicalApplicationNo);
            
        if ($excludeId) {
            $query->where('online_application_id', '!=', $excludeId);
        }
        
        return $query->doesntExist();
    }
}

