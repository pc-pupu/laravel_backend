<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProcessFlowService
{
    /**
     * Insert a record into housing_process_flow table
     *
     * @param int $onlineApplicationId
     * @param string $shortCode Status short code (e.g., 'applied', 'verified', etc.)
     * @param int $uid User ID
     * @return bool
     * @throws \Exception
     */
    public static function insertProcessFlow($onlineApplicationId, $shortCode, $uid, $remarks = null)
    {
        // Get status data from housing_allotment_status_master
        $statusData = DB::table('housing_allotment_status_master')
            ->where('short_code', $shortCode)
            ->first();

        if (!$statusData) {
            throw new \Exception("Status with short_code '{$shortCode}' not found");
        }

        $data = [
            'online_application_id' => $onlineApplicationId,
            'status_id' => $statusData->status_id,
            'created_at' => now(),
            'uid' => $uid,
            'short_code' => $shortCode,
            'status_weight' => $statusData->weight,
        ];

        if ($remarks !== null) {
            $data['remarks'] = $remarks;
        }

        // Insert into process flow
        return DB::table('housing_process_flow')->insert($data);
    }

    /**
     * Insert process flow with custom status data (when status data is already retrieved)
     *
     * @param int $onlineApplicationId
     * @param int $statusId
     * @param string $shortCode
     * @param int $statusWeight
     * @param int $uid
     * @return bool
     */
    public static function insertProcessFlowWithData($onlineApplicationId, $statusId, $shortCode, $statusWeight, $uid)
    {
        return DB::table('housing_process_flow')->insert([
            'online_application_id' => $onlineApplicationId,
            'status_id' => $statusId,
            'created_at' => now(),
            'uid' => $uid,
            'short_code' => $shortCode,
            'status_weight' => $statusWeight,
        ]);
    }
}

