<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ProcessFlowService;
use App\Helpers\UrlEncryptionHelper;

class SpecialRecommendationController extends Controller
{
    protected $processFlowService;

    public function __construct(ProcessFlowService $processFlowService)
    {
        $this->processFlowService = $processFlowService;
    }

    /**
     * Get housing approver list (applications approved by housing approver)
     * GET /api/special-recommendation/housing-approver-list
     */
    public function getHousingApproverList(Request $request)
    {
        try {
            $typeOfCategory = $request->input('type_of_category');
            $flatType = $request->input('flat_type');

            $query = DB::table('housing_online_application as hoa')
                ->join('housing_applicant_official_detail as haod', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
                ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hnaa.flat_type_id')
                ->whereNotNull('haod.hrms_id')
                ->where('hoa.status', 'housingapprover_approved_1')
                ->where('haod.is_active', 1)
                ->select(
                    'hoa.application_no',
                    'hoa.date_of_application',
                    'hoa.computer_serial_no',
                    'hoa.date_of_verified',
                    'hoa.online_application_id',
                    'ha.applicant_name',
                    'hnaa.allotment_category',
                    'hft.flat_type',
                    'hft.flat_type_id'
                );

            // Apply filters if provided
            if ($typeOfCategory) {
                $query->where('hnaa.allotment_category', $typeOfCategory);
            }

            if ($flatType) {
                $query->where('hnaa.flat_type_id', $flatType);
            }

            $applications = $query->get();

            // Check if each application is already in special recommendation list
            $result = $applications->map(function ($app) {
                $specialRec = DB::table('housing_special_recommended')
                    ->where('housing_online_application_id', $app->online_application_id)
                    ->first();

                return [
                    'application_no' => $app->application_no ?? null,
                    'date_of_application' => $app->date_of_application ?? null,
                    'computer_serial_no' => $app->computer_serial_no ?? null,
                    'date_of_verified' => $app->date_of_verified ?? null,
                    'online_application_id' => $app->online_application_id ?? null,
                    'applicant_name' => $app->applicant_name ?? null,
                    'allotment_category' => $app->allotment_category ?? null,
                    'flat_type' => $app->flat_type ?? null,
                    'flat_type_id' => $app->flat_type_id ?? null,
                    'is_special_recommended' => !empty($specialRec),
                    'special_recommend_id' => $specialRec->special_recommend_id ?? null,
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $result,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Housing Approver List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch housing approver list: ' . $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Add application to special recommendation list
     * POST /api/special-recommendation/add
     */
    public function addToSpecialRecommendation(Request $request)
    {
        $request->validate([
            'online_application_id' => 'required|integer',
            'allotment_category' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $onlineApplicationId = $request->online_application_id;
            $allotmentCategory = $request->allotment_category;

            // Check if application exists and is approved
            $application = DB::table('housing_online_application as hoa')
                ->join('housing_applicant_official_detail as haod', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->where('hoa.online_application_id', $onlineApplicationId)
                ->where('hoa.status', 'housingapprover_approved_1')
                ->where('haod.is_active', 1)
                ->first();

            if (!$application) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application not found or not approved',
                    'status_code' => 404
                ], 404);
            }

            // Check if already in special recommendation
            $existing = DB::table('housing_special_recommended')
                ->where('housing_online_application_id', $onlineApplicationId)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application is already in special recommendation list',
                    'status_code' => 400
                ], 400);
            }

            // Get max priority order
            $maxOrder = DB::table('housing_special_recommended')
                ->max('priority_order') ?? 0;

            // Insert into special recommendation
            DB::table('housing_special_recommended')->insert([
                'housing_online_application_id' => $onlineApplicationId,
                'old_category' => $allotmentCategory,
                'created_at' => date('Y-m-d'),
                'new_category' => 'special_recommended',
                'priority_order' => $maxOrder + 1,
                'updated_at' => now(),
            ]);

            // Update allotment category
            DB::table('housing_new_allotment_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update(['allotment_category' => 'Special Recommended']);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Special Recommendation is Successful.',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Add to Special Recommendation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add to special recommendation',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Remove application from special recommendation list
     * POST /api/special-recommendation/remove
     */
    public function removeFromSpecialRecommendation(Request $request)
    {
        $request->validate([
            'online_application_id' => 'required|integer',
            'action' => 'nullable|string|in:manual,delete',
        ]);

        try {
            DB::beginTransaction();

            $onlineApplicationId = $request->online_application_id;
            $action = $request->input('action', 'delete');

            // Get special recommendation data
            $specialRec = DB::table('housing_special_recommended')
                ->where('housing_online_application_id', $onlineApplicationId)
                ->first();

            if (!$specialRec) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application not found in special recommendation list',
                    'status_code' => 404
                ], 404);
            }

            // Insert into log table
            $logData = [
                'special_recommend_id' => $specialRec->special_recommend_id,
                'housing_online_application_id' => $specialRec->housing_online_application_id,
                'priority_order' => $specialRec->priority_order,
                'flag' => $action === 'manual' ? 'manual-allotment-deleted' : 'deleted',
                'old_category' => $specialRec->old_category,
                'new_category' => $specialRec->new_category,
                'created_at_housing_special_recommended' => $specialRec->created_at,
                'updated_at_housing_special_recommended' => $specialRec->updated_at,
                'created_at' => now(),
            ];

            DB::table('housing_special_recommended_log')->insert($logData);

            // Update old category if not manual action
            if ($action !== 'manual') {
                DB::table('housing_new_allotment_application')
                    ->where('online_application_id', $onlineApplicationId)
                    ->update(['allotment_category' => $specialRec->old_category]);
            }

            // Delete from special recommendation
            $priorityOrder = $specialRec->priority_order;
            DB::table('housing_special_recommended')
                ->where('special_recommend_id', $specialRec->special_recommend_id)
                ->delete();

            // Update priority orders for remaining items
            $maxOrder = DB::table('housing_special_recommended')
                ->max('priority_order') ?? 0;

            for ($i = $priorityOrder + 1; $i <= $maxOrder; $i++) {
                DB::table('housing_special_recommended')
                    ->where('priority_order', $i)
                    ->update(['priority_order' => $i - 1, 'updated_at' => now()]);
            }

            DB::commit();

            $message = $action === 'manual' 
                ? 'Flat has been successfully tagged for special recommendation.'
                : 'Special Recommendation is Removed for the Application';

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Remove from Special Recommendation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove from special recommendation',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get special recommendation list (for editing priority)
     * GET /api/special-recommendation/list-view
     */
    public function getSpecialRecommendationListView()
    {
        try {
            $applications = DB::table('housing_online_application as hoa')
                ->join('housing_applicant_official_detail as haod', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
                ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hnaa.flat_type_id')
                ->join('housing_special_recommended as hsr', 'hsr.housing_online_application_id', '=', 'hoa.online_application_id')
                ->where('haod.is_active', 1)
                ->select(
                    'hoa.application_no',
                    'hoa.date_of_application',
                    'hoa.computer_serial_no',
                    'hoa.date_of_verified',
                    'hoa.online_application_id',
                    'hoa.status',
                    'ha.applicant_name',
                    'hnaa.allotment_category',
                    'hft.flat_type',
                    'hsr.priority_order'
                )
                ->orderBy('hsr.priority_order', 'ASC')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $applications,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Special Recommendation List View Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch special recommendation list',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Update priority order
     * POST /api/special-recommendation/update-priority
     */
    public function updatePriorityOrder(Request $request)
    {
        $request->validate([
            'online_application_ids' => 'required|string', // Comma-separated IDs
        ]);

        try {
            DB::beginTransaction();

            $ids = explode(',', $request->online_application_ids);
            $priority = 1;

            foreach ($ids as $onlineApplicationId) {
                $onlineApplicationId = trim($onlineApplicationId);
                if (empty($onlineApplicationId)) {
                    continue;
                }

                DB::table('housing_special_recommended')
                    ->where('housing_online_application_id', $onlineApplicationId)
                    ->update([
                        'priority_order' => $priority,
                        'updated_at' => now(),
                    ]);

                $priority++;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data Saved successfully.',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update Priority Order Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update priority order',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get final special recommended list
     * GET /api/special-recommendation/final-list
     */
    public function getFinalSpecialRecommendedList()
    {
        try {
            $applications = DB::table('housing_special_recommended as hsr')
                ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hsr.housing_online_application_id')
                ->join('housing_applicant_official_detail as haod', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
                ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hnaa.flat_type_id')
                ->where('hoa.status', 'housingapprover_approved_1')
                ->where('haod.is_active', 1)
                ->select(
                    'hsr.housing_online_application_id',
                    'hsr.priority_order',
                    'hoa.application_no',
                    'hoa.date_of_application',
                    'hoa.computer_serial_no',
                    'hoa.date_of_verified',
                    'hoa.online_application_id',
                    'hoa.status',
                    'ha.applicant_name',
                    'hnaa.allotment_category',
                    'hft.flat_type'
                )
                ->orderBy('hsr.priority_order', 'ASC')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $applications,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Final Special Recommended List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch final special recommended list',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get application details for viewing
     * GET /api/special-recommendation/view-details/{online_application_id}
     */
    public function getApplicationDetails($onlineApplicationId)
    {
        try {
            $decryptedId = UrlEncryptionHelper::decryptUrl($onlineApplicationId);

            $application = DB::table('housing_online_application as hoa')
                ->join('housing_applicant_official_detail as haod', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
                ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->join('users as u', 'u.uid', '=', 'haod.uid')
                ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
                ->leftJoin('housing_district as hdis', 'hdis.district_code', '=', 'hd.district_code')
                ->leftJoin('housing_district as hdis_present', 'hdis_present.district_code', '=', 'ha.present_district')
                ->leftJoin('housing_district as hdis_permanent', 'hdis_permanent.district_code', '=', 'ha.permanent_district')
                ->leftJoin('housing_district as hdis_office', 'hdis_office.district_code', '=', 'haod.office_district')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hnaa.flat_type_id')
                ->where('hoa.status', 'housingapprover_approved_1')
                ->where('haod.is_active', 1)
                ->where('hoa.online_application_id', $decryptedId)
                ->select(
                    'hoa.application_no',
                    'hoa.date_of_application',
                    'hoa.computer_serial_no',
                    'hoa.date_of_verified',
                    'hoa.online_application_id',
                    'ha.*',
                    'haod.*',
                    'hnaa.allotment_category',
                    'hft.flat_type',
                    'hd.ddo_designation',
                    'hd.ddo_address',
                    'hdis.district_name',
                    'hdis_present.district_name as present_district_name',
                    'hdis_permanent.district_name as permanent_district_name',
                    'hdis_office.district_name as office_district_name',
                    'u.mail as email'
                )
                ->first();

            if (!$application) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application not found',
                    'status_code' => 404
                ], 404);
            }

            // Format addresses
            $presentAddressParts = array_filter([
                $application->present_street ?? '',
                $application->present_city_town_village ?? '',
                $application->present_post_office ? 'P.O- ' . $application->present_post_office : '',
                $application->present_district_name ?? '',
                $application->present_pincode ? '-' . $application->present_pincode : '',
            ]);
            $application->present_address = !empty($presentAddressParts) ? implode(', ', $presentAddressParts) : 'Data Not Available';

            $permanentAddressParts = array_filter([
                $application->permanent_street ?? '',
                $application->permanent_city_town_village ?? '',
                $application->permanent_post_office ? 'P.O- ' . $application->permanent_post_office : '',
                $application->permanent_district_name ?? '',
                $application->permanent_pincode ? '-' . $application->permanent_pincode : '',
            ]);
            $application->permanent_address = !empty($permanentAddressParts) ? implode(', ', $permanentAddressParts) : 'Data Not Available';

            $officeAddressParts = array_filter([
                $application->office_street ?? '',
                $application->office_city_town_village ?? '',
                $application->office_post_office ? 'P.O- ' . $application->office_post_office : '',
                $application->office_district_name ?? '',
                $application->office_pin_code ? '-' . $application->office_pin_code : '',
            ]);
            $application->office_address = !empty($officeAddressParts) ? implode(', ', $officeAddressParts) : 'Data Not Available';

            return response()->json([
                'status' => 'success',
                'data' => $application,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Application Details Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch application details',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Convert from Recommended to General category
     * POST /api/special-recommendation/convert-to-general
     */
    public function convertToGeneralCategory(Request $request)
    {
        $request->validate([
            'online_application_id' => 'required|integer',
            'old_category' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $onlineApplicationId = $request->online_application_id;
            $oldCategory = $request->input('old_category', 'Recommended');

            // Update allotment category
            DB::table('housing_new_allotment_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update(['allotment_category' => 'General']);

            // Insert into log
            DB::table('housing_special_recommended_log')->insert([
                'special_recommend_id' => 0,
                'housing_online_application_id' => $onlineApplicationId,
                'priority_order' => 0,
                'flag' => 'convert_to_general',
                'old_category' => $oldCategory,
                'new_category' => 'General',
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Applicant converted to General category with same waiting list',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Convert to General Category Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to convert to general category',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Manual allotment for special recommendation
     * POST /api/special-recommendation/manual-allotment
     */
    public function manualAllotment(Request $request)
    {
        $request->validate([
            'online_application_id' => 'required|integer',
            'allotment_date' => 'required|string', // DD/MM/YYYY format
            'rhe_id' => 'required|integer',
            'flat_type_id' => 'required|integer',
            'block_id' => 'required|integer',
            'floor_no' => 'required|string',
            'flat_id' => 'required|integer',
        ]);

        try {
            DB::beginTransaction();

            $uid = $request->input('uid');
            $onlineApplicationId = $request->online_application_id;
            
            // Convert date from DD/MM/YYYY to Y-m-d
            $allotmentDateStr = $request->allotment_date;
            $allotmentDate = null;
            if (!empty($allotmentDateStr)) {
                try {
                    $dateParts = explode('/', $allotmentDateStr);
                    if (count($dateParts) == 3) {
                        $allotmentDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
                    } else {
                        // Try parsing as Y-m-d
                        $allotmentDate = date('Y-m-d', strtotime($allotmentDateStr));
                    }
                } catch (\Exception $e) {
                    $allotmentDate = date('Y-m-d', strtotime($allotmentDateStr));
                }
            }
            
            if (!$allotmentDate) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid allotment date format',
                    'status_code' => 400
                ], 400);
            }
            
            $flatId = $request->flat_id;
            $flatTypeId = $request->flat_type_id;

            // Get max allotment process number
            $maxProcessData = DB::table('housing_allotment_process')
                ->whereIn('allotment_process_type', ['MANUAL', 'ALOT'])
                ->select(DB::raw("MAX(allotment_process_no) as max_process_no"))
                ->first();

            $maxProcessNo = $maxProcessData->max_process_no ?? null;
            
            if (!empty($maxProcessNo)) {
                // Extract numeric part from ALOT-XX format
                $numericPart = (int) preg_replace('/\D/', '', $maxProcessNo);
                $allotmentProcessNo = 'ALOT-' . str_pad($numericPart + 1, 2, '0', STR_PAD_LEFT);
            } else {
                $allotmentProcessNo = 'ALOT-01';
            }

            // Insert into housing_allotment_process
            DB::table('housing_allotment_process')->insert([
                'allotment_date' => $allotmentDate,
                'allotment_process_no' => $allotmentProcessNo,
                'allotment_process_type' => 'MANUAL',
                'allotment_flat_type' => $flatTypeId,
            ]);

            // Update housing_new_allotment_application
            DB::table('housing_new_allotment_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update(['flat_type_id' => $flatTypeId]);

            // Check for duplicate entry
            $existing = DB::table('housing_flat_occupant')
                ->where('online_application_id', $onlineApplicationId)
                ->where('flat_id', $flatId)
                ->first();

            if (!$existing) {
                // Insert into housing_flat_occupant
                DB::table('housing_flat_occupant')->insert([
                    'online_application_id' => $onlineApplicationId,
                    'allotment_date' => $allotmentDate,
                    'flat_id' => $flatId,
                    'allotment_no' => 'MSR-' . $onlineApplicationId . '-' . time(),
                    'allotment_process_no' => $allotmentProcessNo,
                    'allotment_approve_or_reject_date' => now(),
                ]);
            }

            // Update housing_online_application status
            DB::table('housing_online_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update(['status' => 'allotted']);

            // Update housing_flat status
            DB::table('housing_flat')
                ->where('flat_id', $flatId)
                ->update(['flat_status_id' => 2]); // 2 = allotted

            // Insert into housing_process_flow
            $statusId = $this->getStatusId('allotted');
            if ($statusId) {
                DB::table('housing_process_flow')->insert([
                    'online_application_id' => $onlineApplicationId,
                    'status_id' => $statusId,
                    'created_at' => now(),
                    'uid' => $uid,
                    'short_code' => 'allotted',
                ]);
            }

            // Remove from special recommendation (with manual action)
            $specialRec = DB::table('housing_special_recommended')
                ->where('housing_online_application_id', $onlineApplicationId)
                ->first();

            if ($specialRec) {
                // Insert into log table
                $logData = [
                    'special_recommend_id' => $specialRec->special_recommend_id,
                    'housing_online_application_id' => $specialRec->housing_online_application_id,
                    'priority_order' => $specialRec->priority_order,
                    'flag' => 'manual-allotment-deleted',
                    'old_category' => $specialRec->old_category,
                    'new_category' => $specialRec->new_category,
                    'created_at_housing_special_recommended' => $specialRec->created_at,
                    'updated_at_housing_special_recommended' => $specialRec->updated_at,
                    'created_at' => now(),
                ];

                DB::table('housing_special_recommended_log')->insert($logData);

                // Delete from special recommendation
                $priorityOrder = $specialRec->priority_order;
                DB::table('housing_special_recommended')
                    ->where('special_recommend_id', $specialRec->special_recommend_id)
                    ->delete();

                // Update priority orders for remaining items
                $maxOrder = DB::table('housing_special_recommended')
                    ->max('priority_order') ?? 0;

                for ($i = $priorityOrder + 1; $i <= $maxOrder; $i++) {
                    DB::table('housing_special_recommended')
                        ->where('priority_order', $i)
                        ->update(['priority_order' => $i - 1, 'updated_at' => now()]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Flat has been successfully tagged for special recommendation.',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Manual Allotment Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process manual allotment',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get RHE list for manual allotment
     * GET /api/special-recommendation/helpers/rhe-list
     */
    public function getRheList()
    {
        try {
            $rheList = DB::table('housing_estate')
                ->orderBy('estate_name', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->estate_id,
                        'label' => $item->estate_name
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $rheList,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get RHE List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch RHE list',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get flat types under RHE
     * GET /api/special-recommendation/helpers/flat-types/{rhe_id}
     */
    public function getFlatTypesUnderRhe($rheId)
    {
        try {
            $flatTypes = DB::table('housing_flat_type as hft')
                ->join('housing_flat as hf', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->where('hf.estate_id', $rheId)
                ->distinct()
                ->select('hft.flat_type_id', 'hft.flat_type')
                ->orderBy('hft.flat_type', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->flat_type_id,
                        'label' => trim($item->flat_type),
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $flatTypes,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Flat Types Under RHE Error', [
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
     * Get blocks under RHE and flat type
     * GET /api/special-recommendation/helpers/blocks/{rhe_id}/{flat_type_id}
     */
    public function getBlocksUnderRhe($rheId, $flatTypeId)
    {
        try {
            $blocks = DB::table('housing_block as hb')
                ->join('housing_flat as hf', 'hf.block_id', '=', 'hb.block_id')
                ->where('hf.estate_id', $rheId)
                ->where('hf.flat_type_id', $flatTypeId)
                ->distinct()
                ->select('hb.block_id', 'hb.block_name')
                ->orderBy('hb.block_name', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->block_id,
                        'label' => $item->block_name
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $blocks,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Blocks Under RHE Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch blocks',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get floors under RHE, flat type, and block
     * GET /api/special-recommendation/helpers/floors/{rhe_id}/{flat_type_id}/{block_id}
     */
    public function getFloorsUnderRhe($rheId, $flatTypeId, $blockId)
    {
        try {
            $floors = DB::table('housing_flat')
                ->where('estate_id', $rheId)
                ->where('flat_type_id', $flatTypeId)
                ->where('block_id', $blockId)
                ->distinct()
                ->select('floor')
                ->orderBy('floor', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->floor,
                        'label' => $item->floor
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $floors,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Floors Under RHE Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch floors',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get flats under RHE, flat type, block, and floor
     * GET /api/special-recommendation/helpers/flats/{rhe_id}/{flat_type_id}/{block_id}/{floor_no}
     */
    public function getFlatsUnderRhe($rheId, $flatTypeId, $blockId, $floorNo)
    {
        try {
            $flats = DB::table('housing_flat')
                ->where('estate_id', $rheId)
                ->where('flat_type_id', $flatTypeId)
                ->where('block_id', $blockId)
                ->where('floor', $floorNo)
                ->where('flat_status_id', 1) // Available flats only
                ->select('flat_id', 'flat_no')
                ->orderBy('flat_no', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->flat_id,
                        'label' => $item->flat_no
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $flats,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Flats Under RHE Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch flats',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get status ID by short code
     */
    protected function getStatusId($shortCode)
    {
        $status = DB::table('housing_allotment_status_master')
            ->where('short_code', $shortCode)
            ->first();

        return $status ? $status->status_id : null;
    }
}
