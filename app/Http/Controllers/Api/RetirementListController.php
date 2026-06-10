<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetirementListController extends Controller
{
    /**
     * Upcoming retirement list (parity with Drupal retirement_list module).
     *
     * GET /api/retirement-list
     */
    public function index(Request $request)
    {
        try {
            $today = Carbon::today();
            $cutoff = (clone $today)->addMonths(6)->endOfDay();

            $rows = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->where('hoa.status', 'issued')
                ->whereNotNull('haod.date_of_retirement')
                ->whereBetween('haod.date_of_retirement', [$today->toDateString(), $cutoff->toDateString()])
                ->orderBy('haod.date_of_retirement', 'asc')
                ->select([
                    'hoa.online_application_id',
                    'hoa.application_no',
                    'haod.uid',
                    'haod.date_of_retirement',
                    'hoa.status',
                    'hoa.date_of_application',
                    'hoa.date_of_verified',
                ])
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $rows,
                'status_code' => 200,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Retirement list fetch error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch retirement list.',
                'status_code' => 500,
            ], 500);
        }
    }
}

