<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserTaggingController extends Controller
{
    /**
     * Check if user has already submitted a tagging request
     * GET /api/user-tagging/check-submission/{uid}
     */
    public function checkSubmission($uid)
    {
        try {
            // Check if user has any pending or new tagging request
            $existingSubmission = DB::table('housing_user_tagging')
                ->where('uid', $uid)
                ->whereIn('flag', ['new', 'pending'])
                ->where('status', 1)
                ->first();

            return response()->json([
                'status' => 'success',
                'has_submitted' => !empty($existingSubmission),
                'submission_data' => $existingSubmission,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Check Submission Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'has_submitted' => false,
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Submit User Tagging Form
     * POST /api/user-tagging/submit
     */
    public function submit(Request $request)
    {
        $request->validate([
            'applicant_name' => 'required|string|max:255',
            'mobile_no' => 'required|string|max:10',
            'email' => 'required|email|max:255',
            'license_no' => 'required|string|max:255',
            'license_issue_date' => 'required|date_format:Y-m-d',
            'license_expiry_date' => 'nullable|date_format:Y-m-d',
            'physical_application_vs_cs' => 'required|in:yes,no',
            'physical_application_no' => 'nullable|string',
            'application_type' => 'nullable|in:VS,CS',
            'flat_id' => 'required|integer',
            'uid' => 'required|integer',
            'hrms_id' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // Check if flat is already tagged
            $existingTag = DB::table('housing_user_tagging')
                ->where('flat_id', $request->flat_id)
                ->first();

            if ($existingTag) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This flat is already tagged. Duplicate entries are not allowed.',
                    'status_code' => 400
                ], 400);
            }

            // Prepare data for insertion
            $data = [
                'applicant_name' => trim($request->applicant_name),
                'mobile_no' => trim($request->mobile_no),
                'email' => trim($request->email),
                'physical_application_no' => trim($request->physical_application_no ?? ''),
                'application_type' => trim($request->application_type ?? ''),
                'license_no' => trim($request->license_no),
                'license_issue_date' => $request->license_issue_date,
                'license_expiry_date' => $request->license_expiry_date,
                'flat_id' => $request->flat_id,
                'created_date' => now(),
                'status' => '1',
                'flag' => 'new', // new, pending, tagged, reject
                'hrms_id' => $request->hrms_id,
                'uid' => $request->uid,
            ];

            DB::table('housing_user_tagging')->insert($data);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Your data has been submitted for departmental approval. Please wait until the approval process is complete.',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User Tagging Submit Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get User Tagging List (Admin)
     * GET /api/user-tagging/list
     */
    public function getList(Request $request)
    {
        try {
            $query = DB::table('housing_user_tagging as hut')
                ->join('housing_existing_occupant_draft as heod', 'heod.flat_id', '=', 'hut.flat_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hut.flat_id')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
                ->whereIn('hut.flag', ['new', 'pending'])
                ->select(
                    'hut.*',
                    'heod.*',
                    'hf.floor',
                    'hf.flat_no',
                    'hf.flat_id',
                    'he.estate_name',
                    'hb.block_name',
                    'hft.flat_type'
                )
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $query,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('User Tagging List Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get User Tagging Details
     * GET /api/user-tagging/details/{flat_id}
     */
    public function getDetails($flatId)
    {
        try {
            // Get tagging data
            $tagData = DB::table('housing_user_tagging')
                ->where('flat_id', $flatId)
                ->first();

            // Get draft data
            $draftData = DB::table('housing_existing_occupant_draft')
                ->where('flat_id', $flatId)
                ->first();

            // Get flat info
            $flatInfo = DB::table('housing_flat as hf')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
                ->where('hf.flat_id', $flatId)
                ->select(
                    'he.estate_name',
                    'hb.block_name',
                    'hft.flat_type',
                    'hf.floor',
                    'hf.flat_no'
                )
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'tag_data' => $tagData,
                    'draft_data' => $draftData,
                    'flat_info' => $flatInfo,
                ],
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('User Tagging Details Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Update Tagging Status (Approve/Reject/Pending)
     * POST /api/user-tagging/update-status
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'action' => 'required|in:tagged,reject,pending',
            'flat_id' => 'required|integer',
            'housing_user_tagging_id' => 'required|integer',
            'remarks' => 'required|string',
            'form_info_array' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $action = $request->action;
            $flatId = $request->flat_id;
            $taggingId = $request->housing_user_tagging_id;
            $remarks = $request->remarks;
            $formInfo = $request->form_info_array;

            if ($action == 'tagged') {
                // Complex approval logic
                $this->approveTagging($flatId, $taggingId, $remarks, $formInfo);
                $message = 'User Successfully Tagged with Flat';
            } elseif ($action == 'pending') {
                DB::table('housing_user_tagging')
                    ->where('housing_user_tagging_id', $taggingId)
                    ->update([
                        'flag' => 'pending',
                        'remarks' => $remarks
                    ]);
                $message = 'User Tagging is Awaiting Further Verification';
            } elseif ($action == 'reject') {
                DB::table('housing_user_tagging')
                    ->where('housing_user_tagging_id', $taggingId)
                    ->update([
                        'flag' => 'reject',
                        'remarks' => $remarks,
                        'status' => 0
                    ]);
                $message = 'User Tagging is Rejected';
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User Tagging Update Status Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Approve Tagging - Complex Logic
     */
    private function approveTagging($flatId, $taggingId, $remarks, $formInfo)
    {
        // Check for VS/CS application
        $vsCsApp = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
            ->where(function($query) use ($flatId) {
                $query->where('hva.occupation_flat', $flatId)
                      ->orWhere('hca.occupation_flat', $flatId);
            })
            ->where('hoa.status', 'housingapprover_approved_1')
            ->whereNotNull('haod.hrms_id')
            ->where('haod.hrms_id', '!=', '0')
            ->where('haod.hrms_id', '!=', '')
            ->whereRaw("TRIM(COALESCE(haod.hrms_id, '')) != ''")
            ->select('haod.uid', 'haod.hrms_id', 'haod.applicant_official_detail_id', 'hoa.application_no')
            ->first();

        // Insert housing_applicant
        $housingApplicantId = DB::table('housing_applicant')->insertGetId([
            'uid' => $formInfo['user_id'],
            'applicant_name' => $formInfo['name'],
            'mobile_no' => $formInfo['mobile'],
        ], 'housing_applicant_id');

        // Insert housing_applicant_official_detail
        $applicantOfficialDetailId = DB::table('housing_applicant_official_detail')->insertGetId([
            'uid' => $formInfo['user_id'],
            'housing_applicant_id' => $housingApplicantId,
            'hrms_id' => $formInfo['hrmsid'],
            'ddo_id' => $formInfo['draft_ddo_id'] ?? 1263,
            'pay_band_id' => $formInfo['draft_pay_band_id'] ?? 10,
        ], 'applicant_official_detail_id');

        // Handle VS/CS user deactivation if exists
        if (!empty($vsCsApp) && $vsCsApp->hrms_id == 0) {
            $applicationNo = $vsCsApp->application_no;
            $applicationType = explode("-", $applicationNo, 2)[0];
            
            if (in_array($applicationType, ['VS', 'CS'])) {
                DB::table('users')
                    ->where('uid', $vsCsApp->uid)
                    ->update(['status' => 0]);

                DB::table('housing_applicant_official_detail')
                    ->where('applicant_official_detail_id', $vsCsApp->applicant_official_detail_id)
                    ->update([
                        'uid' => $formInfo['user_id'],
                        'hrms_id' => $formInfo['hrmsid'],
                        'is_active' => 1
                    ]);
            }
        }

        // Insert housing_online_application
        $onlineApplicationId = DB::table('housing_online_application')->insertGetId([
            'applicant_official_detail_id' => $applicantOfficialDetailId,
            'status' => 'existing_occupant',
            'is_backlog_applicant' => 2,
        ], 'online_application_id');

        // Update application number
        DB::table('housing_online_application')
            ->where('online_application_id', $onlineApplicationId)
            ->update(['application_no' => 'EO-' . date('dmY') . '-' . $onlineApplicationId]);

        // Insert housing_flat_occupant
        $flatOccupantId = DB::table('housing_flat_occupant')->insertGetId([
            'online_application_id' => $onlineApplicationId,
            'flat_id' => $flatId,
            'allotment_date' => null,
        ], 'flat_occupant_id');

        // Update flat status to occupied
        DB::table('housing_flat')
            ->where('flat_id', $flatId)
            ->update(['flat_status_id' => 2]);

        // Update tagging status
        DB::table('housing_user_tagging')
            ->where('housing_user_tagging_id', $taggingId)
            ->update([
                'flag' => 'tagged',
                'housing_existing_occupant_draft_id' => $formInfo['housing_existing_occupant_draft_id'] ?? null,
                'remarks' => $remarks
            ]);

        // Insert housing_occupant_license
        DB::table('housing_occupant_license')->insert([
            'flat_occupant_id' => $flatOccupantId,
            'license_no' => $formInfo['license_no'],
            'license_issue_date' => $formInfo['license_issue_date'],
            'license_expiry_date' => $formInfo['license_expiry_date'],
            'authorised_or_not' => $formInfo['authorised_or_not'] ?? null,
        ]);

        // Insert housing_new_allotment_application if no VS/CS exists
        if (empty($vsCsApp)) {
            DB::table('housing_new_allotment_application')->insert([
                'online_application_id' => $onlineApplicationId,
            ]);
        }

        // Deactivate applicant if VS/CS exists
        if (!empty($vsCsApp)) {
            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $applicantOfficialDetailId)
                ->update(['is_active' => 0]);
        }
    }
}

