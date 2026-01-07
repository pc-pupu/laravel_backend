<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StatusService
{
    /**
     * Get status data by short code
     *
     * @param string $shortCode
     * @return object|null
     */
    public static function getStatusByShortCode($shortCode)
    {
        return DB::table('housing_allotment_status_master')
            ->where('short_code', $shortCode)
            ->first();
    }

    /**
     * Get status ID by short code
     *
     * @param string $shortCode
     * @return int|null
     */
    public static function getStatusId($shortCode)
    {
        return DB::table('housing_allotment_status_master')
            ->where('short_code', $shortCode)
            ->value('status_id');
    }

    /**
     * Get status weight by short code
     *
     * @param string $shortCode
     * @return int|null
     */
    public static function getStatusWeight($shortCode)
    {
        return DB::table('housing_allotment_status_master')
            ->where('short_code', $shortCode)
            ->value('weight');
    }

    /**
     * Validate if status exists
     *
     * @param string $shortCode
     * @return bool
     */
    public static function statusExists($shortCode)
    {
        return DB::table('housing_allotment_status_master')
            ->where('short_code', $shortCode)
            ->exists();
    }
}

