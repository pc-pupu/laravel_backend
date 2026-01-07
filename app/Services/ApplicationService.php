<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicationService
{
    /**
     * Handle rejection - deactivate official detail and free flat
     *
     * @param int $applicationId
     * @return void
     */
    public static function handleRejection($applicationId)
    {
        $officialDetail = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->leftJoin('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->where('hoa.online_application_id', $applicationId)
            ->select('haod.applicant_official_detail_id', 'hfo.flat_id')
            ->first();

        Log::info('Handling Rejection', [
            'application_id' => $applicationId,
            'official_detail' => $officialDetail,
        ]);

        if ($officialDetail) {
            // Deactivate official detail
            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $officialDetail->applicant_official_detail_id)
                ->update(['is_active' => 0]);

            // Free the flat if allocated
            if ($officialDetail->flat_id) {
                DB::table('housing_flat')
                    ->where('flat_id', $officialDetail->flat_id)
                    ->update(['flat_status_id' => 1]); // 1 = Available
            }
        }
    }

    /**
     * Check if status is a rejection status
     *
     * @param string $status
     * @return bool
     */
    public static function isRejectionStatus($status)
    {
        $rejectionStatuses = [
            'ddo_rejected_1',
            'ddo_rejected_2',
            'housing_sup_reject_1',
            'housing_sup_reject_2',
            'housing_approver_reject_1',
            'housing_approver_reject_2',
            'housing_official_reject',
            'applicant_reject',
        ];

        return in_array($status, $rejectionStatuses);
    }
}

