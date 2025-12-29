<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;

class ApplicationListController extends Controller
{
    /**
     * Get application list for applicants (their own applications)
     * GET /api/application-list
     */
    public function index(Request $request)
    {
        $uid = $request->input('uid');

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'UID is required',
            ], 422);
        }

        try {
            $applications = $this->fetchApplicationList($uid);

            \Log::info('Fetched Application List', ['uid' => $uid, 'applications' => $applications]);
            return response()->json([
                'status' => 'success',
                'data' => $applications,
            ]);

        } catch (\Exception $e) {
            Log::error('Application List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch application list',
            ], 500);
        }
    }

    /**
     * Get application list for admins/officials (filtered by status and entity type)
     * GET /api/application-list/admin
     */
    public function adminList(Request $request)
    {
        $status = $request->input('status');
        $entity = $request->input('entity'); // new-apply, vs, cs
        $pageStatus = $request->input('page_status', 'action-list'); // action-list, verified-list, reject-list
        $userRole = $request->input('user_role');
        $ddoCode = $request->input('ddo_code'); // For DDO role filtering

        if(empty($userRole)){
            $uid = $request->input('uid');
            $userRole = DB::table('user_role')
                ->where('uid', $uid)
                ->orderBy('rid', 'ASC')
                ->value('rid');
        }
        \Log::info('Admin Application List Request', [
            'status' => $status,
            'entity' => $entity,
            'page_status' => $pageStatus,
            'user_role' => $userRole,
            'ddo_code' => $ddoCode,
        ]);
        if (!$status || !$entity) {
            return response()->json([
                'status' => 'error',
                'message' => 'Status and entity are required',
            ], 422);
        }

        
        try {
            if ($pageStatus == 'action-list') {
                $applications = $this->fetchApplicationListForAction($entity, $status, $userRole, $ddoCode);
            } else {
                $applications = $this->fetchApplicationListForVerifiedReject($entity, $status, $userRole, $ddoCode);
            }

            // Get counts for verified and rejected
            $verifiedStatus = $this->getVerifiedStatus($status, $userRole);
            $rejectedStatus = $this->getRejectedStatus($status, $userRole);

            $counts = [
                'total' => count($applications),
                'verified' => $this->getApplicationCount($entity, $verifiedStatus, $userRole, $ddoCode),
                'rejected' => $this->getApplicationCount($entity, $rejectedStatus, $userRole, $ddoCode),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $applications,
                'counts' => $counts,
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Application List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch application list',
            ], 500);
        }
    }

    /**
     * Get application detail
     * GET /api/application-list/{id}
     */
    public function show(Request $request, $id)
    {
        $uid = $request->input('uid');
        $pageStatus = $request->input('page_status');
        $status = $request->input('status');

        try {
            if ($pageStatus == 'verified-list' || $pageStatus == 'reject-list') {
                $application = $this->fetchApplicationDetailVerifiedReject($id, $status);
            } else {
                $application = $this->fetchApplicationDetail($id, $uid);
            }
            
            // \Log::info('Fetched Application Detail', ['application_id' => $id, 'application' => $application]);
            if (!$application) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application not found',
                ], 404);
            }

            // Get additional data
            $application['estate_preferences'] = $this->fetchEstatePreferences($id);
            $application['status_description'] = $this->fetchStatusDescription($application['application_status']);
            $application['allotment_flat_details'] = $this->fetchAllotmentFlatDetails($id);
            $application['applicant_personal_info'] = $this->fetchApplicantPersonalInfo($id);
           
            $documents = DB::table('housing_new_allotment_application as hna')
                ->where('hna.online_application_id', $id)
                ->select(
                    'hna.extra_doc_path'
                )
                ->first();

                 $application['documents'] = $documents;

            return response()->json([
                'status' => 'success',
                'data' => $application,
            ]);

        } catch (\Exception $e) {
            Log::error('Application Detail Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch application detail',
            ], 500);
        }
    }

    /**
     * Update application status (approve/reject)
     * POST /api/application-list/{id}/update-status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'new_status' => 'required|string',
            'entity' => 'required|string',
            'current_status' => 'required|string',
            'computer_serial_no' => 'nullable|string',
            'remarks' => 'nullable|string',
            'uid' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $newStatus = $request->input('new_status');
            $entity = $request->input('entity');
            $currentStatus = $request->input('current_status');
            $computerSerialNo = $request->input('computer_serial_no');
            $remarks = $request->input('remarks');
            $uid = $request->input('uid');

            // Validate minimum serial number requirement
            $validationError = $this->validateMinimumSerialNumber($entity, $currentStatus, $computerSerialNo, $id);
            if ($validationError) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => $validationError,
                ], 422);
            }

            // Update application status
            DB::table('housing_online_application')
                ->where('online_application_id', $id)
                ->update([
                    'status' => $newStatus,
                    'date_of_verified' => now(),
                ]);

            // Get status ID
            $statusId = DB::table('housing_allotment_status_master')
                ->where('short_code', $newStatus)
                ->value('status_id');

            // Insert into process flow
            DB::table('housing_process_flow')->insert([
                'online_application_id' => $id,
                'status_id' => $statusId,
                'created_at' => now(),
                'uid' => $uid,
                'short_code' => $newStatus,
                'remarks' => $remarks,
            ]);

            // If rejected, deactivate official detail and free flat
            if (in_array($newStatus, [
                'ddo_rejected_1', 'ddo_rejected_2',
                'housing_sup_reject_1', 'housing_sup_reject_2',
                'housing_approver_reject_1', 'housing_approver_reject_2',
                'housing_official_reject'
            ])) {
                $this->handleRejection($id);
            }

            DB::commit();

            // Send notification if rejected
            if (strpos($newStatus, 'reject') !== false || strpos($newStatus, 'rejected') !== false) {
                $this->sendRejectionNotification($id);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Application status updated successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update Status Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update application status',
            ], 500);
        }
    }

    /**
     * Fetch application list for applicants
     */
    private function fetchApplicationList($uid)
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->join('housing_district as hds', 'hds.district_code', '=', 'hd.district_code')
            ->join('housing_pay_band_categories as hpb', 'hpb.pay_band_id', '=', 'haod.pay_band_id')
            ->leftJoin('housing_flat_type as hft', 'hpb.flat_type_id', '=', 'hft.flat_type_id')
            ->leftJoin('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_process_flow as hpf', 'hpf.online_application_id', '=', 'hoa.online_application_id')
            ->where('haod.uid', $uid)
            ->where('haod.is_active', 1)
            ->select(
                'hoa.online_application_id',
                'hoa.application_no',
                'hoa.status',
                'hoa.date_of_application',
                'hoa.date_of_verified',
                'hoa.computer_serial_no',
                'hoa.uploaded_app_form',
                'hft.flat_type',
                'hft.flat_type_id',
                'hpf.short_code',
                DB::raw("CASE 
                    WHEN hna.online_application_id IS NOT NULL THEN 'new-apply'
                    WHEN hva.online_application_id IS NOT NULL THEN 'vs'
                    WHEN hca.online_application_id IS NOT NULL THEN 'cs'
                    WHEN hla.online_application_id IS NOT NULL THEN 'license'
                    ELSE 'unknown'
                END as application_type")
            )
            ->orderBy('hoa.online_application_id', 'ASC')
            ->get();

            \Log::info('Fetched Application List Query', ['query' => $query]);
        return $query->map(function ($app) {
            return [
                'online_application_id' => $app->online_application_id,
                'application_no' => $app->application_no,
                'status' => $app->status,
                'date_of_application' => $app->date_of_application,
                'date_of_verified' => $app->date_of_verified,
                'computer_serial_no' => $app->computer_serial_no,
                'flat_type' => $app->flat_type,
                'application_type' => $app->application_type,
            ];
        })->toArray();
    }

    /**
     * Fetch application list for action (pending approval/rejection)
     */
    private function fetchApplicationListForAction($entity, $status, $userRole, $ddoCode)
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->where('haod.is_active', 1)
            ->where('hoa.status', $status);

        // 🔹 Filter by DDO code for DDO role
        if ($userRole == 11 && $ddoCode) {
            $query->where('hd.ddo_code', $ddoCode);
        }

        // 🔹 Entity-specific joins
        if ($entity === 'new-apply') {

            $query->join('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hna.flat_type_id', '=', 'hft.flat_type_id')
                ->addSelect('hna.allotment_category', 'hft.flat_type');

        } elseif ($entity === 'vs') {

            $query->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hva.flat_type_id', '=', 'hft.flat_type_id')
                ->addSelect('hva.allotment_category', 'hft.flat_type');

        } elseif ($entity === 'cs') {

            $query->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hca.flat_type_id', '=', 'hft.flat_type_id')
                ->addSelect('hca.allotment_category', 'hft.flat_type');
        }

        // 🔹 Common selects
        $query->addSelect(
            'ha.applicant_name',
            'hoa.online_application_id',
            'hoa.application_no',
            'hoa.date_of_application',
            'hoa.computer_serial_no'
        );

        // 🔹 PostgreSQL-safe ordering
        if ($entity === 'new-apply') {

            $query->orderByRaw("
                NULLIF(regexp_replace(hoa.computer_serial_no, '[^0-9]', '', 'g'), '')::INTEGER ASC,
                regexp_replace(hoa.computer_serial_no, '[0-9]', '', 'g') ASC
            ");

        } else {
            $query->orderBy('hoa.online_application_id', 'ASC');
        }

        return $query->get()->map(function ($app) {
            return (array) $app;
        })->toArray();
    }


    /**
     * Fetch application list for verified/rejected
     */
    private function fetchApplicationListForVerifiedReject($entity, $status, $userRole, $ddoCode)
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->join('housing_process_flow as hpf', 'hpf.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_allotment_status_master as hsm', 'hsm.status_id', '=', 'hpf.status_id')
            ->where('hpf.short_code', $status);

        // Filter by DDO code for DDO role
        if ($userRole == 11 && $ddoCode) {
            $query->where('hd.ddo_code', $ddoCode);
        }

        // Handle is_active based on status
        $rejectedStatuses = [
            'housing_sup_reject_1', 'housing_official_reject', 'housing_sup_reject_2',
            'housing_approver_reject_1', 'housing_approver_reject_2',
            'ddo_rejected_1', 'ddo_rejected_2'
        ];
        if (in_array($status, $rejectedStatuses)) {
            $query->where('haod.is_active', 0);
        } else {
            $query->where('haod.is_active', 1);
        }

        // Join entity-specific tables
        if ($entity == 'new-apply') {
            $query->join('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hna.flat_type_id', '=', 'hft.flat_type_id')
                ->addSelect('hna.allotment_category', 'hft.flat_type');
        } elseif ($entity == 'vs') {
            $query->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hva.flat_type_id', '=', 'hft.flat_type_id')
                ->addSelect('hva.allotment_category', 'hft.flat_type');
        } elseif ($entity == 'cs') {
            $query->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hca.flat_type_id', '=', 'hft.flat_type_id')
                ->addSelect('hca.allotment_category', 'hft.flat_type');
        }

        $query->addSelect(
            'ha.applicant_name',
            'hoa.online_application_id',
            'hoa.application_no',
            'hoa.date_of_application',
            'hoa.computer_serial_no',
            'hpf.created_at as approval_or_rejection_date',
            'hsm.status_description'
        );

        // Order by computer serial number for new-apply, by ID for others
        if ($entity == 'new-apply') {
            $query->orderByRaw('CAST(hoa.computer_serial_no AS UNSIGNED) ASC')
                ->orderBy('hoa.computer_serial_no', 'ASC');
        } else {
            $query->orderBy('hoa.online_application_id', 'ASC');
        }

        return $query->get()->map(function ($app) {
            return (array) $app;
        })->toArray();
    }

    /**
     * Fetch application detail
     */
    private function fetchApplicationDetail($onlineApplicationId, $uid = null)
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->join('housing_district as hds', 'hds.district_code', '=', 'hd.district_code')
            ->join('housing_pay_band_categories as hpb', 'hpb.pay_band_id', '=', 'haod.pay_band_id')
            ->leftJoin('housing_flat_type as hft', 'hpb.flat_type_id', '=', 'hft.flat_type_id')
            ->leftJoin('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
            ->where('hoa.online_application_id', $onlineApplicationId);

        // if ($uid) {
        //     $query->where('haod.uid', $uid);
        // }

        $result = $query->select(
            'hoa.status as application_status',
            'hoa.*',
            'haod.*',
            'hd.*',
            'hds.district_name',
            'hft.flat_type',
            'hft.flat_type_id',
            'hpb.scale_from',
            'hpb.scale_to',
            'hna.allotment_category as na_allotment_category',
            'hva.allotment_category as vs_allotment_category',
            'hca.allotment_category as cs_allotment_category',
            'hna.extra_doc_path'
        )->first();
           
        return $result ? (array) $result : null;
    }


 

    /**
     * Fetch application detail for verified/rejected list
     */
    private function fetchApplicationDetailVerifiedReject($onlineApplicationId, $status)
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->join('housing_district as hds', 'hds.district_code', '=', 'hd.district_code')
            ->join('housing_pay_band_categories as hpb', 'hpb.pay_band_id', '=', 'haod.pay_band_id')
            ->join('housing_process_flow as hpf', 'hpf.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_allotment_status_master as hsm', 'hsm.status_id', '=', 'hpf.status_id')
            ->leftJoin('housing_flat_type as hft', 'hpb.flat_type_id', '=', 'hft.flat_type_id')
            ->leftJoin('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('file_managed as fm_app_form', 'fm_app_form.fid', '=', 'hoa.uploaded_app_form')
            ->leftJoin('file_managed as fm_doc', 'fm_doc.fid', '=', 'hna.document')
            ->leftJoin('file_managed as fm_extra_doc', 'fm_extra_doc.fid', '=', 'hna.extra_doc')
            ->leftJoin('file_managed as fm_scaned_sign', 'fm_scaned_sign.fid', '=', 'hna.scaned_sign')
            ->leftJoin('file_managed as fm_vs', 'fm_vs.fid', '=', 'hva.file_licence')
            ->leftJoin('file_managed as fm_cs', 'fm_cs.fid', '=', 'hca.file_licence')
            ->leftJoin('file_managed as fm_licence', 'fm_licence.fid', '=', 'hla.document')
            ->leftJoin('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
            ->where('hoa.online_application_id', $onlineApplicationId)
            ->where('hpf.short_code', $status)
            ->select(
                'hoa.*',
                'haod.*',
                'hd.*',
                'hds.district_name',
                'hft.flat_type',
                'hft.flat_type_id',
                'hpb.scale_from',
                'hpb.scale_to',
                'hpf.short_code',
                'hpf.created_at',
                'hpf.remarks',
                'hsm.status_description',
                'hna.allotment_category as na_allotment_category',
                'hva.allotment_category as vs_allotment_category',
                'hca.allotment_category as cs_allotment_category',
                'fm_app_form.uri as uri_app_form',
                'fm_doc.uri as uri_doc',
                'fm_extra_doc.uri as uri_extra_doc',
                'fm_scaned_sign.uri as uri_scaned_sign',
                'fm_vs.uri as uri_vs',
                'fm_cs.uri as uri_cs',
                'fm_licence.uri as uri_licence'
            )
            ->first();

        return $query ? (array) $query : null;
    }

    /**
     * Fetch estate preferences
     */
    private function fetchEstatePreferences($onlineApplicationId)
    {
        return DB::table('housing_new_application_estate_preferences as hnaep')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hnaep.estate_id')
            ->where('hnaep.online_application_id', $onlineApplicationId)
            ->whereNotNull('hnaep.estate_id')
            ->orderBy('hnaep.preference_order', 'ASC')
            ->select('he.estate_name', 'hnaep.preference_order')
            ->get()
            ->toArray();
    }

    /**
     * Fetch status description
     */
    private function fetchStatusDescription($status)
    {
        $statusData = DB::table('housing_allotment_status_master')
            ->where('short_code', $status)
            ->select('status_description', 'short_code')
            ->first();

        return $statusData ? (array) $statusData : null;
    }

    /**
     * Fetch allotment flat details
     */
    private function fetchAllotmentFlatDetails($onlineApplicationId)
    {
        $flatDetails = DB::table('housing_flat_occupant as hfo')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->where('hfo.online_application_id', $onlineApplicationId)
            ->where('hfo.allotment_no', '!=', '')
            ->where('hfo.allotment_process_no', '!=', '')
            ->select(
                'hfo.flat_occupant_id',
                'hfo.flat_id',
                'hf.floor',
                'hf.flat_no',
                'hft.flat_type',
                'hb.block_name',
                'he.estate_name'
            )
            ->first();

        return $flatDetails ? (array) $flatDetails : null;
    }

    /**
     * Get verified status based on current status and user role
     */
    private function getVerifiedStatus($status, $userRole)
    {
        $statusMap = [
            '11' => [ // DDO
                'applied' => 'ddo_verified_1',
                'applicant_acceptance' => 'ddo_verified_2',
            ],
            '10' => [ // Housing Supervisor
                'ddo_verified_1' => 'housing_sup_approved_1',
                'ddo_verified_2' => 'housing_sup_approved_2',
            ],
            '13' => [ // Housing Approver
                'housing_sup_approved_1' => 'housingapprover_approved_1',
                'housing_sup_approved_2' => 'housingapprover_approved_2',
            ],
            '6' => [ // Housing Official
                'housingapprover_approved_1' => 'housing_official_approved',
                'housingapprover_approved_2' => 'housing_official_approved',
            ],
        ];

        return $statusMap[$userRole][$status] ?? null;
    }

    /**
     * Get rejected status based on current status and user role
     */
    private function getRejectedStatus($status, $userRole)
    {
        $statusMap = [
            '11' => [ // DDO
                'applied' => 'ddo_rejected_1',
                'applicant_acceptance' => 'ddo_rejected_2',
            ],
            '10' => [ // Housing Supervisor
                'ddo_verified_1' => 'housing_sup_reject_1',
                'ddo_verified_2' => 'housing_sup_reject_2',
            ],
            '13' => [ // Housing Approver
                'housing_sup_approved_1' => 'housing_approver_reject_1',
                'housing_sup_approved_2' => 'housing_approver_reject_2',
            ],
            '6' => [ // Housing Official
                'housing_official_approved' => 'housing_official_reject',
            ],
        ];

        return $statusMap[$userRole][$status] ?? null;
    }

    /**
     * Get application count
     */
    private function getApplicationCount($entity, $status, $userRole, $ddoCode)
    {
        \Log::info('Getting application count', [
            'entity' => $entity,
            'status' => $status,
            'userRole' => $userRole,
            'ddoCode' => $ddoCode,
        ]);
        if (!$status) {
            return 0;
        }

        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->where('haod.is_active', 1)
            ->where('hoa.status', $status);

        if ($userRole == 11 && $ddoCode) {
            $query->where('hd.ddo_code', $ddoCode);
        }

        if ($entity == 'new-apply') {
            $query->join('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id');
        } elseif ($entity == 'vs') {
            $query->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id');
        } elseif ($entity == 'cs') {
            $query->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id');
        }

        return $query->count();
    }

    /**
     * Validate minimum serial number requirement
     */
    private function validateMinimumSerialNumber($entity, $status, $computerSerialNo, $applicationId)
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('haod.is_active', 1)
            ->where('hoa.status', $status);

        if ($entity == 'new-apply') {
            $query->join('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id');
            // Filter out null, 0, and blank computer_serial_no values (considered as null data)
            $query->whereNotNull('hoa.computer_serial_no')
                ->where('hoa.computer_serial_no', '!=', '0')
                ->where('hoa.computer_serial_no', '!=', '')
                ->whereRaw("TRIM(COALESCE(hoa.computer_serial_no, '')) != ''");
            $minSerial = $query->min(DB::raw('CAST(hoa.computer_serial_no AS UNSIGNED)'));
            
            if ($minSerial && $minSerial < (int)$computerSerialNo) {
                return 'Approval or Rejection must begin with the application that has the lowest computer serial number.';
            }
        } else {
            $minId = $query->min('hoa.online_application_id');
            
            if ($minId && $minId < $applicationId) {
                return 'Approval or Rejection must begin with the application that has the lowest Application number.';
            }
        }

        return null;
    }

    /**
     * Handle rejection (deactivate official detail, free flat)
     */
    private function handleRejection($applicationId)
    {
        $officialDetail = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->leftJoin('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->where('hoa.online_application_id', $applicationId)
            ->select('haod.applicant_official_detail_id', 'hfo.flat_id')
            ->first();

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
     * Send rejection notification
     */
    private function sendRejectionNotification($applicationId)
    {
        try {
            // Get user info by application ID
            $userInfo = $this->getUserInfoByOnlineAppId($applicationId);
            
            if ($userInfo && !empty($userInfo['mail']) && !empty($userInfo['mobile_no'])) {
                $notificationService = new NotificationService();
                $notificationService->sendRejectionNotification(
                    $applicationId,
                    $userInfo['applicant_name'],
                    $userInfo['mail'],
                    $userInfo['mobile_no']
                );
            }
        } catch (\Exception $e) {
            Log::error('Rejection Notification Error', [
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get user info by online application ID
     * Equivalent to get_userinfo_by_onlineapp_id in Drupal
     */
    private function getUserInfoByOnlineAppId($applicationId)
    {
        $result = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('users as u', 'haod.uid', '=', 'u.uid')
            ->where('hoa.online_application_id', $applicationId)
            ->select(
                'u.uid',
                'u.mail',
                'ha.mobile_no',
                'ha.applicant_name'
            )
            ->first();

        return $result ? (array) $result : null;
    }

    /**
     * Get dashboard counts for view_application_list
     * GET /api/view-application-list/dashboard
     */
    public function getDashboardCounts(Request $request)
    {
        $status = $request->input('status');
        $entity = $request->input('entity'); // new-apply, vs, cs
        $userRole = $request->input('user_role');
        $ddoCode = $request->input('ddo_code');
        $uid = $request->input('uid');
        $userName = $request->input('userName');
        if(empty($userRole)){
            $userRole = DB::table('user_role')
                ->where('uid', $uid)
                ->orderBy('rid', 'ASC')
                ->value('rid');
        }
        
        if (!$status || !$entity) {
            return response()->json([
                'status' => 'error',
                'message' => 'Status and entity are required',
            ], 422);
        }

        try {
            // Get verified and rejected statuses based on user role and current status
            $verifiedStatus = $this->getVerifiedStatus($status, $userRole);
            $rejectedStatus = $this->getRejectedStatus($status, $userRole);

            // Get counts
            $actionCount = $this->getApplicationCount($entity, $status, $userRole, $ddoCode);
            $verifiedCount = $verifiedStatus ? $this->getApplicationCountForVerifiedReject($entity, $verifiedStatus, $userRole, $ddoCode) : 0;
            $rejectedCount = $rejectedStatus ? $this->getApplicationCountForVerifiedReject($entity, $rejectedStatus, $userRole, $ddoCode) : 0;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'action_count' => $actionCount,
                    'verified_count' => $verifiedCount,
                    'rejected_count' => $rejectedCount,
                    'verified_status' => $verifiedStatus,
                    'rejected_status' => $rejectedStatus,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Counts Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch dashboard counts',
            ], 500);
        }
    }

    /**
     * Get application count for verified/rejected list
     */
    private function getApplicationCountForVerifiedReject($entity, $status, $userRole, $ddoCode)
    {
        if (!$status) {
            return 0;
        }

        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->join('housing_process_flow as hpf', 'hpf.online_application_id', '=', 'hoa.online_application_id')
            ->where('hpf.short_code', $status);

        if ($userRole == 11 && $ddoCode) {
            $query->where('hd.ddo_code', $ddoCode);
        }

        // Handle is_active based on status
        $rejectedStatuses = [
            'housing_sup_reject_1', 'housing_official_reject', 'housing_sup_reject_2',
            'housing_approver_reject_1', 'housing_approver_reject_2',
            'ddo_rejected_1', 'ddo_rejected_2'
        ];
        if (in_array($status, $rejectedStatuses)) {
            $query->where('haod.is_active', 0);
        } else {
            $query->where('haod.is_active', 1);
        }

        if ($entity == 'new-apply') {
            $query->join('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id');
        } elseif ($entity == 'vs') {
            $query->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id');
        } elseif ($entity == 'cs') {
            $query->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id');
        }

        return $query->count();
    }

    /**
     * Approve application (with file upload support)
     * POST /api/view-application-list/approve
     */
    public function approveApplication(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'online_application_id' => 'required|integer',
            'status_new' => 'required|string',
            'status' => 'required|string',
            'entity' => 'required|string',
            'computer_serial_no' => 'nullable|string',
            'flat_type' => 'nullable|string',
            'uid' => 'required|integer',
            'application_form_file' => 'nullable|file|mimes:pdf|max:1024', // 1MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $onlineApplicationId = $request->input('online_application_id');
            $statusNew = $request->input('status_new');
            $status = $request->input('status');
            $entity = $request->input('entity');
            $computerSerialNo = $request->input('computer_serial_no');
            $flatType = $request->input('flat_type');
            $uid = $request->input('uid');

            // Validate minimum serial number requirement (by flat type for new-apply)
            if ($entity == 'new-apply' && $flatType) {
                $validationError = $this->validateMinimumSerialNumberByFlatType($entity, $status, $flatType, $computerSerialNo, $onlineApplicationId);
                if ($validationError) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => $validationError,
                    ], 422);
                }
            } else {
                $validationError = $this->validateMinimumSerialNumber($entity, $status, $computerSerialNo, $onlineApplicationId);
                if ($validationError) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => $validationError,
                    ], 422);
                }
            }

            // Handle file upload if provided
            $fileId = null;
            if ($request->hasFile('application_form_file')) {
                $file = $request->file('application_form_file');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('public/signed_doc', $fileName);
                
                $fileId = DB::table('file_managed')->insertGetId([
                    'uid' => $uid,
                    'filename' => $fileName,
                    'uri' => 'public://signed_doc/' . $fileName,
                    'filemime' => $file->getMimeType(),
                    'filesize' => $file->getSize(),
                    'status' => 1,
                    'created' => now()->timestamp,
                    'changed' => now()->timestamp,
                ]);

                // Update application with uploaded form
                DB::table('housing_online_application')
                    ->where('online_application_id', $onlineApplicationId)
                    ->update(['uploaded_app_form' => $fileId]);
            }

            // Get status ID
            $statusId = DB::table('housing_allotment_status_master')
                ->where('short_code', $statusNew)
                ->value('status_id');

            if (!$statusId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid status',
                ], 422);
            }

            // Update application status
            DB::table('housing_online_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update([
                    'status' => $statusNew,
                    'date_of_verified' => now(),
                ]);

            // Insert into process flow
            DB::table('housing_process_flow')->insert([
                'online_application_id' => $onlineApplicationId,
                'status_id' => $statusId,
                'created_at' => now(),
                'uid' => $uid,
                'short_code' => $statusNew,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Application approved successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approve Application Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve application',
            ], 500);
        }
    }

    /**
     * Validate minimum serial number by flat type
     */
    private function validateMinimumSerialNumberByFlatType($entity, $status, $flatType, $computerSerialNo, $applicationId)
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('haod.is_active', 1)
            ->where('hoa.status', $status);

        if ($entity == 'new-apply') {
            $query->join('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hna.flat_type_id', '=', 'hft.flat_type_id')
                ->where('hft.flat_type', $flatType);
            
            $minSerial = $query->min(DB::raw('CAST(hoa.computer_serial_no AS UNSIGNED)'));
            
            if ($minSerial && $minSerial < (int)$computerSerialNo) {
                return 'Approval must begin with the application that has the lowest computer serial number with the same Flat Type.';
            }
        } else {
            $minId = $query->min('hoa.online_application_id');
            
            if ($minId && $minId < $applicationId) {
                return 'Approval must begin with the application that has the lowest Application number with the same Flat Type.';
            }
        }

        return null;
    }

    /**
     * Get applicant uploaded documents
     * GET /api/view-application-list/{id}/documents
     */
    public function getApplicantDocuments(Request $request, $id)
    {
        try {
            $documents = DB::table('housing_new_allotment_application as hna')
                ->where('hna.online_application_id', $id)
                ->select(
                    'hna.extra_doc_path'
                )
                ->first();

            if (!$documents) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Documents not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $documents,
            ]);

        } catch (\Exception $e) {
            Log::error('Get Documents Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch documents',
            ], 500);
        }
    }

    /**
     * Fetch applicant personal information
     */
    private function fetchApplicantPersonalInfo($onlineApplicationId)
    {
        $query = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('users as u', 'u.uid', '=', 'haod.uid')
            ->where('hoa.online_application_id', $onlineApplicationId)
            ->select(
                'ha.*',
                'u.mail as email',
                'u.uid'
            )
            ->first();

        return $query ? (array) $query : null;
    }

    /**
     * Fetch applicant documents data
     */
    // private function fetchApplicantDocumentsData($onlineApplicationId)
    // {
    //     $documents = DB::table('housing_online_application_upload_doc')
    //         ->where('online_application_id', $onlineApplicationId)
    //         ->first();

    //     if (!$documents) {
    //         return null;
    //     }

    //     $docData = (array) $documents;
        
    //     // Get applicant UID for file paths
    //     $uid = DB::table('housing_applicant_official_detail as haod')
    //         ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
    //         ->where('hoa.online_application_id', $onlineApplicationId)
    //         ->value('haod.uid');

    //     // Build file URLs
    //     $baseUrl = url('/');
    //     if ($docData['license_application_signed_form']) {
    //         $docData['license_application_signed_form_url'] = $baseUrl . '/storage/documents/' . $uid . '/' . $docData['license_application_signed_form'];
    //     }
    //     if ($docData['declaration_signed_form']) {
    //         $docData['declaration_signed_form_url'] = $baseUrl . '/storage/documents/' . $uid . '/' . $docData['declaration_signed_form'];
    //     }
    //     if ($docData['current_pay_slip']) {
    //         $docData['current_pay_slip_url'] = $baseUrl . '/storage/documents/' . $uid . '/' . $docData['current_pay_slip'];
    //     }

    //     return $docData;
    // }

    /**
     * Get application entity type (check_application_entity equivalent)
     * GET /api/view-application-list/{id}/entity-type
     */
    public function getApplicationEntityType(Request $request, $id)
    {
        try {
            $entityType = $this->getApplicationEntityTypeData($id);

            if (!$entityType) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application entity type not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $entityType,
            ]);

        } catch (\Exception $e) {
            Log::error('Get Entity Type Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch entity type',
            ], 500);
        }
    }

    /**
     * Get application entity type data (internal method)
     */
    private function getApplicationEntityTypeData($id)
    {
        // Check for new application
        $newApp = DB::table('housing_new_allotment_application')
            ->where('online_application_id', $id)
            ->first();
        
        if ($newApp) {
            return [
                'type' => 'New Allotment',
                'entity' => 'new-apply',
            ];
        }

        // Check for VS application
        $vsApp = DB::table('housing_vs_application')
            ->where('online_application_id', $id)
            ->first();
        
        if ($vsApp) {
            return [
                'type' => 'Vertical Shifting',
                'entity' => 'vs',
            ];
        }

        // Check for CS application
        $csApp = DB::table('housing_cs_application')
            ->where('online_application_id', $id)
            ->first();
        
        if ($csApp) {
            return [
                'type' => 'Category Shifting',
                'entity' => 'cs',
            ];
        }

        // Check for license application
        $licenseApp = DB::table('housing_license_application')
            ->where('online_application_id', $id)
            ->first();
        
        if ($licenseApp) {
            $type = 'New Licence';
            if ($licenseApp->type_of_application == 'vs') {
                $type = 'VS Licence';
            } elseif ($licenseApp->type_of_application == 'cs') {
                $type = 'CS Licence';
            } elseif ($licenseApp->type_of_application == 'renew') {
                $type = 'Renew Licence';
            }

            return [
                'type' => $type,
                'entity' => 'license',
            ];
        }

        return null;
    }
}

