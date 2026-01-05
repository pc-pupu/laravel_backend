<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\UrlEncryptionHelper;

class ViewAllotmentDetailsController extends Controller
{
    /**
     * Get allotment details for a user
     */
    public function getAllotmentDetails(Request $request)
    {
        try {
            $uid = $request->get('uid');
            $onlineApplicationId = $request->get('online_application_id');

            $query = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_estate as he', 'hf.estate_id', '=', 'he.estate_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->join('housing_district as hd', 'he.district_code', '=', 'hd.district_code')
                ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
                ->select(
                    'ha.*',
                    'hoa.online_application_id',
                    'hoa.application_no',
                    'hoa.status',
                    'hfo.allotment_no',
                    'hfo.allotment_date',
                    'hfo.flat_id',
                    'hfo.accept_reject_status',
                    'hfo.allotment_approve_or_reject_date',
                    'hf.flat_no',
                    'hft.flat_type',
                    'he.estate_name',
                    'he.estate_address',
                    'hd.district_name',
                    'hb.block_name',
                    'hf.floor'
                );

            // Allowed statuses
            $statusArr = [
                'housing_official_approved', 'allotted', 'applicant_acceptance',
                'applicant_reject', 'ddo_verified_2', 'ddo_reject_2',
                'housing_sup_approved_2', 'housing_sup_reject_2', 'license_generate',
                'offer_letter_extended', 'flat_possession_taken'
            ];

            if ($onlineApplicationId) {
                $query->where('hoa.online_application_id', $onlineApplicationId)
                    ->whereIn('hoa.status', $statusArr);
            } else {
                $query->where('haod.uid', $uid)
                    ->whereIn('hoa.status', $statusArr);
            }

            $allotment = $query->first();

            if (!$allotment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Allotment details not found',
                    'status_code' => 404
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $allotment,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Allotment Details Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch allotment details',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get uploaded documents for an application
     */
    public function getUploadedDocuments(Request $request)
    {
        try {
            $uid = $request->get('uid');
            $onlineApplicationId = $request->get('online_application_id');

            $query = DB::table('housing_new_allotment_application');

            if ($onlineApplicationId) {
                $query->where('online_application_id', $onlineApplicationId);
            } else {
                $query->where('uid', $uid);
            }

            $documents = $query->first();

            return response()->json([
                'status' => 'success',
                'data' => $documents,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Uploaded Documents Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch uploaded documents',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Update status (Accept/Reject)
     */
    public function updateStatus(Request $request)
    {
        try {
            $request->validate([
                'online_application_id' => 'required|integer',
                'status' => 'required|string|in:Accept,Reject'
            ]);

            $onlineApplicationId = $request->online_application_id;
            $status = $request->status;
            $user = auth()->user();
            $userId = $user->uid ?? $user->id ?? null;

            DB::beginTransaction();

            if ($status == 'Accept') {
                // For Accept, just redirect to declaration page (no DB update needed)
                // The actual acceptance happens after declaration is uploaded
                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'You have accepted the allotment. Please accept this Declaration to finalize your acceptance.',
                    'redirect' => '/download-and-upload/' . UrlEncryptionHelper::encryptUrl($onlineApplicationId),
                    'status_code' => 200
                ], 200);

            } elseif ($status == 'Reject') {
                // Update online application status
                DB::table('housing_online_application')
                    ->where('online_application_id', $onlineApplicationId)
                    ->update(['status' => 'applicant_reject']);

                // Get status data
                $statusData = DB::table('housing_allotment_status_master')
                    ->where('short_code', 'applicant_reject')
                    ->first();

                // Insert process flow
                DB::table('housing_process_flow')->insert([
                    'online_application_id' => $onlineApplicationId,
                    'status_id' => $statusData->status_id,
                    'created_at' => now(),
                    'uid' => $userId,
                    'short_code' => 'applicant_reject',
                    'status_weight' => $statusData->weight,
                ]);

                // Get flat_id and applicant_official_detail_id
                $data = DB::table('housing_applicant_official_detail as haod')
                    ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                    ->leftJoin('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                    ->where('hoa.online_application_id', $onlineApplicationId)
                    ->select('haod.applicant_official_detail_id', 'hfo.flat_id')
                    ->first();

                if ($data) {
                    // Update applicant official detail
                    DB::table('housing_applicant_official_detail')
                        ->where('applicant_official_detail_id', $data->applicant_official_detail_id)
                        ->update(['is_active' => 0]);

                    // Update flat status
                    if ($data->flat_id) {
                        DB::table('housing_flat')
                            ->where('flat_id', $data->flat_id)
                            ->update(['flat_status_id' => 1]);
                    }
                }

                // Update flat occupant
                DB::table('housing_flat_occupant')
                    ->where('online_application_id', $onlineApplicationId)
                    ->update(['accept_reject_status' => 'Reject']);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'You rejected the allotment.',
                    'status_code' => 200
                ], 200);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update Status Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update status',
                'status_code' => 500
            ], 500);
        }
    }
}

