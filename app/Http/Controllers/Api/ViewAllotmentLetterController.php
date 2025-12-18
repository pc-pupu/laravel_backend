<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViewAllotmentLetterController extends Controller
{
    /**
     * Get flat types for dropdown
     */
    public function getFlatTypes()
    {
        try {
            $flatTypes = DB::table('housing_flat_type')
                ->orderBy('flat_type', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->flat_type_id,
                        'label' => $item->flat_type
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $flatTypes,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Flat Types Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch flat types',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get allotment letter list for a flat type
     */
    public function getAllotmentLetterList(Request $request)
    {
        try {
            $request->validate([
                'flat_type_id' => 'required|integer'
            ]);

            $flatTypeId = $request->flat_type_id;

            // Get flat type name
            $flatType = DB::table('housing_flat_type')
                ->where('flat_type_id', $flatTypeId)
                ->value('flat_type');

            if (!$flatType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid flat type',
                    'status_code' => 400
                ], 400);
            }

            $allotmentLetters = DB::table('housing_allotment_letter as hal')
                ->join('housing_flat as hf', 'hal.flat_id', '=', 'hf.flat_id')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                ->join('housing_online_application as hoa', 'hal.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->join('housing_applicant as ha', 'haod.uid', '=', 'ha.uid')
                ->leftJoin('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
                ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->where('hal.flat_type', $flatType)
                ->select(
                    'hal.online_application_id',
                    'ha.applicant_name',
                    'hna.allotment_category as n_allotment_category',
                    'hva.allotment_category as v_allotment_category',
                    'hca.allotment_category as c_allotment_category',
                    'hf.flat_no',
                    'he.estate_name',
                    'hal.roaster_counter',
                    'hal.list_no',
                    'hal.allotment_letter_id',
                    'hal.flat_id'
                )
                ->orderBy('hal.list_no', 'asc')
                ->orderBy('hal.roaster_counter', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $allotmentLetters,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Allotment Letter List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch allotment letter list',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Allot applicant (create flat occupant record)
     */
    public function allotApplicant(Request $request)
    {
        try {
            $request->validate([
                'online_application_id' => 'required|integer'
            ]);

            $onlineApplicationId = $request->online_application_id;

            // Get allotment letter details
            $allotmentLetter = DB::table('housing_allotment_letter')
                ->where('online_application_id', $onlineApplicationId)
                ->first();

            if (!$allotmentLetter) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Allotment letter not found',
                    'status_code' => 404
                ], 404);
            }

            // Check if already allotted
            $existing = DB::table('housing_flat_occupant')
                ->where('online_application_id', $onlineApplicationId)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Applicant already allotted',
                    'status_code' => 400
                ], 400);
            }

            DB::beginTransaction();

            // Insert into housing_flat_occupant
            DB::table('housing_flat_occupant')->insert([
                'online_application_id' => $onlineApplicationId,
                'flat_id' => $allotmentLetter->flat_id,
                'allotment_no' => $allotmentLetter->allotment_letter_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully allotted!',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Allot Applicant Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to allot applicant',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Cancel allotment
     */
    public function cancelAllotment(Request $request)
    {
        try {
            $request->validate([
                'online_application_id' => 'required|integer'
            ]);

            $onlineApplicationId = $request->online_application_id;

            DB::beginTransaction();

            // Delete from housing_allotment_letter
            DB::table('housing_allotment_letter')
                ->where('online_application_id', $onlineApplicationId)
                ->delete();

            // Update housing_online_application status
            DB::table('housing_online_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update(['status' => 'cancel']);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully updated!',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cancel Allotment Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel allotment',
                'status_code' => 500
            ], 500);
        }
    }
}

