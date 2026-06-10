<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnauthorizedOccupantsController extends Controller
{
    public function index()
    {
        try {
            $rows = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_occupant_license as hol', 'hol.license_application_id', '=', 'hla.license_application_id')
                ->join('housing_flat_occupant as hfo', 'hfo.flat_occupant_id', '=', 'hol.flat_occupant_id')
                ->where('hoa.status', 'issued')
                ->whereNotNull('haod.date_of_retirement')
                ->whereDate('haod.date_of_retirement', '<', now()->toDateString())
                ->orderBy('haod.date_of_retirement', 'asc')
                ->select([
                    'hoa.online_application_id',
                    'hoa.application_no',
                    'haod.uid',
                    'haod.date_of_retirement',
                    'hoa.status',
                    'hoa.date_of_application',
                    'hoa.date_of_verified',
                    'hfo.flat_id',
                    'hol.license_no',
                ])
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $rows,
                'status_code' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('Unauthorized occupants list failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch unauthorized occupants list.',
                'status_code' => 500,
            ], 500);
        }
    }

    public function flatDetail(int $flatId)
    {
        try {
            $row = DB::table('housing_flat as hf')
                ->join('housing_estate as he', 'hf.estate_id', '=', 'he.estate_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->join('housing_district as hd', 'he.district_code', '=', 'hd.district_code')
                ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
                ->where('hf.flat_id', $flatId)
                ->select([
                    'hf.flat_id',
                    'hf.flat_no',
                    'hft.flat_type',
                    'he.estate_name',
                    'he.estate_address',
                    'hd.district_name',
                    'hb.block_name',
                ])
                ->first();

            if (!$row) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Flat detail not found.',
                    'status_code' => 404,
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $row,
                'status_code' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('Unauthorized occupant flat detail failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch flat detail.',
                'status_code' => 500,
            ], 500);
        }
    }
}
