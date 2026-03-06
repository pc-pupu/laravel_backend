<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ViewShiftingAllotmentDetailsController extends Controller
{
    /**
     * Get VS Allotment Details for logged-in user
     * GET /api/view-shifting-allotment-details/vs
     */
    public function getVsAllotmentDetails(Request $request)
    {
        try {
            $uid = $request->input('uid');
            $onlineApplicationId = $request->input('online_application_id');

            if (!$uid) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User ID is required',
                    'status_code' => 400
                ], 400);
            }

            $query = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_estate as he', 'hf.estate_id', '=', 'he.estate_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->join('housing_district as hd', 'he.district_code', '=', 'hd.district_code')
                ->select(
                    'ha.*',
                    'hoa.online_application_id',
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
                    'hva.occupation_flat',
                    'hoa.status'
                );

            // Allowed statuses
            $statuses = [
                'housing_official_approved', 'allotted', 'applicant_acceptance', 'applicant_reject',
                'ddo_verified_2', 'ddo_reject_2', 'housing_sup_approved_2', 'housing_sup_reject_2',
                'license_generate', 'offer_letter_extended'
            ];

            if ($onlineApplicationId) {
                $query->where('hoa.online_application_id', $onlineApplicationId)
                    ->whereIn('hoa.status', $statuses);
            } else {
                $query->where('haod.uid', $uid)
                    ->whereIn('hoa.status', $statuses);
            }

            $allotments = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $allotments,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get VS Allotment Details Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch VS allotment details',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get CS Allotment Details for logged-in user
     * GET /api/view-shifting-allotment-details/cs
     */
    public function getCsAllotmentDetails(Request $request)
    {
        try {
            $uid = $request->input('uid');
            $onlineApplicationId = $request->input('online_application_id');

            if (!$uid) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User ID is required',
                    'status_code' => 400
                ], 400);
            }

            $query = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_estate as he', 'hf.estate_id', '=', 'he.estate_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->join('housing_district as hd', 'he.district_code', '=', 'hd.district_code')
                ->select(
                    'ha.*',
                    'hoa.online_application_id',
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
                    'hca.occupation_flat',
                    'hoa.status'
                );

            // Allowed statuses
            $statuses = [
                'housing_official_approved', 'allotted', 'applicant_acceptance', 'applicant_reject',
                'ddo_verified_2', 'ddo_reject_2', 'housing_sup_approved_2', 'housing_sup_reject_2',
                'license_generate', 'offer_letter_extended'
            ];

            if ($onlineApplicationId) {
                $query->where('hoa.online_application_id', $onlineApplicationId)
                    ->whereIn('hoa.status', $statuses);
            } else {
                $query->where('haod.uid', $uid)
                    ->whereIn('hoa.status', $statuses);
            }

            $allotments = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $allotments,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get CS Allotment Details Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch CS allotment details',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Update VS Allotment Status (Accept/Reject)
     * POST /api/view-shifting-allotment-details/vs/update-status
     */
    public function updateVsStatus(Request $request)
    {
        try {
            $request->validate([
                'online_application_id' => 'required|integer',
                'status' => 'required|string|in:Accept,Reject',
                'uid' => 'required|integer'
            ]);

            $onlineApplicationId = $request->input('online_application_id');
            $status = $request->input('status');
            $uid = $request->input('uid');

            DB::beginTransaction();

            try {
                if ($status == 'Accept') {
                    // Redirect to download-and-upload page (handled by frontend)
                    // The actual acceptance is done in the download-and-upload flow
                    DB::commit();
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'You accepted the allotment.',
                        'redirect' => true,
                        'redirect_url' => 'download-and-upload',
                        'status_code' => 200
                    ], 200);
                } else if ($status == 'Reject') {
                    // Update application status
                    DB::table('housing_online_application')
                        ->where('online_application_id', $onlineApplicationId)
                        ->update(['status' => 'applicant_reject']);

                    // Get status ID
                    $statusId = DB::table('housing_allotment_status_master')
                        ->where('short_code', 'applicant_reject')
                        ->value('status_id');

                    // Insert into process flow
                    DB::table('housing_process_flow')->insert([
                        'online_application_id' => $onlineApplicationId,
                        'status_id' => $statusId,
                        'created_at' => now(),
                        'uid' => $uid,
                        'short_code' => 'applicant_reject'
                    ]);

                    // Get flat_id and applicant_official_detail_id
                    $data = DB::table('housing_applicant_official_detail as haod')
                        ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                        ->leftJoin('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                        ->where('hoa.online_application_id', $onlineApplicationId)
                        ->select('haod.applicant_official_detail_id', 'hfo.flat_id')
                        ->first();

                    if ($data) {
                        // Deactivate applicant official detail
                        DB::table('housing_applicant_official_detail')
                            ->where('applicant_official_detail_id', $data->applicant_official_detail_id)
                            ->update(['is_active' => 0]);

                        // Update flat status to vacant if flat_id exists
                        if ($data->flat_id) {
                            DB::table('housing_flat')
                                ->where('flat_id', $data->flat_id)
                                ->update(['flat_status_id' => 1]);
                        }
                    }

                    // Update accept_reject_status
                    DB::table('housing_flat_occupant')
                        ->where('online_application_id', $onlineApplicationId)
                        ->update([
                            'accept_reject_status' => 'Reject',
                            'allotment_approve_or_reject_date' => Carbon::now()->format('Y-m-d')
                        ]);

                    DB::commit();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'You rejected the allotment.',
                        'status_code' => 200
                    ], 200);
                }

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Update VS Status Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update status: ' . $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Update CS Allotment Status (Accept/Reject)
     * POST /api/view-shifting-allotment-details/cs/update-status
     */
    public function updateCsStatus(Request $request)
    {
        try {
            $request->validate([
                'online_application_id' => 'required|integer',
                'status' => 'required|string|in:Accept,Reject',
                'uid' => 'required|integer'
            ]);

            $onlineApplicationId = $request->input('online_application_id');
            $status = $request->input('status');
            $uid = $request->input('uid');

            DB::beginTransaction();

            try {
                if ($status == 'Accept') {
                    // Redirect to download-and-upload page (handled by frontend)
                    // The actual acceptance is done in the download-and-upload flow
                    DB::commit();
                    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'You accepted the allotment.',
                        'redirect' => true,
                        'redirect_url' => 'download-and-upload',
                        'status_code' => 200
                    ], 200);
                } else if ($status == 'Reject') {
                    // Update application status
                    DB::table('housing_online_application')
                        ->where('online_application_id', $onlineApplicationId)
                        ->update(['status' => 'applicant_reject']);

                    // Get status ID
                    $statusId = DB::table('housing_allotment_status_master')
                        ->where('short_code', 'applicant_reject')
                        ->value('status_id');

                    // Insert into process flow
                    DB::table('housing_process_flow')->insert([
                        'online_application_id' => $onlineApplicationId,
                        'status_id' => $statusId,
                        'created_at' => now(),
                        'uid' => $uid,
                        'short_code' => 'applicant_reject'
                    ]);

                    // Get flat_id and applicant_official_detail_id
                    $data = DB::table('housing_applicant_official_detail as haod')
                        ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                        ->leftJoin('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                        ->where('hoa.online_application_id', $onlineApplicationId)
                        ->select('haod.applicant_official_detail_id', 'hfo.flat_id')
                        ->first();

                    if ($data) {
                        // Deactivate applicant official detail
                        DB::table('housing_applicant_official_detail')
                            ->where('applicant_official_detail_id', $data->applicant_official_detail_id)
                            ->update(['is_active' => 0]);

                        // Update flat status to vacant if flat_id exists
                        if ($data->flat_id) {
                            DB::table('housing_flat')
                                ->where('flat_id', $data->flat_id)
                                ->update(['flat_status_id' => 1]);
                        }
                    }

                    // Update accept_reject_status
                    DB::table('housing_flat_occupant')
                        ->where('online_application_id', $onlineApplicationId)
                        ->update([
                            'accept_reject_status' => 'Reject',
                            'allotment_approve_or_reject_date' => Carbon::now()->format('Y-m-d')
                        ]);

                    DB::commit();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'You rejected the allotment.',
                        'status_code' => 200
                    ], 200);
                }

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Update CS Status Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update status: ' . $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get uploaded documents for CS application
     * GET /api/view-shifting-allotment-details/cs/documents
     */
    public function getCsDocuments(Request $request)
    {
        try {
            $request->validate([
                'online_application_id' => 'required|integer'
            ]);

            $onlineApplicationId = $request->input('online_application_id');

            // Get applicant official detail to find uid
            $applicantDetail = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->where('hoa.online_application_id', $onlineApplicationId)
                ->select('haod.uid')
                ->first();

            if (!$applicantDetail) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application not found',
                    'status_code' => 404
                ], 404);
            }

            // Get uploaded documents (similar to Drupal's get_applicant_upload_docs)
            $documents = DB::table('housing_applicant_document as had')
                ->where('had.online_application_id', $onlineApplicationId)
                ->select(
                    'had.license_application_signed_form',
                    'had.declaration_signed_form',
                    'had.current_pay_slip'
                )
                ->first();

            $docData = [];
            if ($documents) {
                $uid = $applicantDetail->uid;
                $basePath = storage_path('app/public/doc/' . $uid . '/');

                if (!empty($documents->license_application_signed_form)) {
                    $docData['license_application_signed_form'] = [
                        'filename' => $documents->license_application_signed_form,
                        'url' => url('storage/doc/' . $uid . '/' . $documents->license_application_signed_form)
                    ];
                }

                if (!empty($documents->declaration_signed_form)) {
                    $docData['declaration_signed_form'] = [
                        'filename' => $documents->declaration_signed_form,
                        'url' => url('storage/doc/' . $uid . '/' . $documents->declaration_signed_form)
                    ];
                }

                if (!empty($documents->current_pay_slip)) {
                    $docData['current_pay_slip'] = [
                        'filename' => $documents->current_pay_slip,
                        'url' => url('storage/doc/' . $uid . '/' . $documents->current_pay_slip)
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $docData,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get CS Documents Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch documents',
                'status_code' => 500
            ], 500);
        }
    }
}
