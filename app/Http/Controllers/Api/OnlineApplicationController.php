<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnlineApplicationController extends Controller
{
    /**
     * Get online application statuses for a user across application types.
     * GET /api/online-application/statuses
     * Query params: uid (required)
     */
    public function statuses(Request $request)
    {
        $uid = $request->input('uid');

        if (empty($uid)) {
            return response()->json([
                'status' => 'error',
                'message' => 'uid is required',
            ], 400);
        }

        try {
            $types = [
                'new-apply'      => 'NA',
                'vs'             => 'VS',
                'cs'             => 'CS',
                'new_license'    => 'NL',
                'vs_licence'     => 'VSL',
                'cs_licence'     => 'CSL',
                'renew_license'  => 'RL',
            ];

            $statuses = [];

            foreach ($types as $label => $prefix) {
                $statuses[$label] = $this->getLatestStatusForType($uid, $prefix);
            }

            return response()->json([
                'status' => 'success',
                'data' => $statuses,
            ]);
        } catch (\Exception $e) {
            Log::error('Online application statuses error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'uid' => $uid,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch online application statuses',
            ], 500);
        }
    }

    /**
     * Fetch the latest status for a given application type (prefix).
     */
    private function getLatestStatusForType(string $uid, string $prefix): array
    {
        $record = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->where('haod.uid', $uid)
            ->where('hoa.application_no', 'like', $prefix . '%')
            ->orderByDesc('hoa.date_of_application')
            ->select(
                'hoa.application_no',
                'hoa.status',
                'hoa.date_of_application',
                'hoa.date_of_verified'
            )
            ->first();

        if (!$record) {
            return [
                'applied' => false,
                'status' => null,
                'application_no' => null,
                'date_of_application' => null,
                'date_of_verified' => null,
            ];
        }

        return [
            'applied' => true,
            'status' => $record->status,
            'application_no' => $record->application_no,
            'date_of_application' => $record->date_of_application,
            'date_of_verified' => $record->date_of_verified,
        ];
    }
}

