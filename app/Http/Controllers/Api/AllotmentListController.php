<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Helpers\UrlEncryptionHelper;

class AllotmentListController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get allotment process dates
     */
    public function getProcessDates()
    {
        try {
            $dates = DB::table('housing_allotment_process')
                ->whereIn('allotment_process_type', ['ALOT', 'MANUAL'])
                ->select('allotment_date')
                ->distinct()
                ->orderBy('allotment_date', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->allotment_date,
                        'label' => implode('/', array_reverse(explode('-', $item->allotment_date)))
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $dates,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Process Dates Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch process dates',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get allotment process numbers by date
     */
    public function getProcessNumbers(Request $request)
    {
        try {
            $request->validate([
                'allotment_process_date' => 'required|date'
            ]);

            $processNos = DB::table('housing_allotment_process')
                ->where('allotment_date', $request->allotment_process_date)
                ->whereIn('allotment_process_type', ['ALOT', 'MANUAL'])
                ->select('allotment_process_id', 'allotment_process_no')
                ->orderBy('allotment_process_no', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->allotment_process_no,
                        'label' => $item->allotment_process_no
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $processNos,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Process Numbers Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch process numbers',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get allotment process types
     */
    public function getProcessTypes()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                ['value' => 'NAL', 'label' => 'New Allotment'],
                ['value' => 'VSAL', 'label' => 'Floor Shifting'],
                ['value' => 'CSAL', 'label' => 'Category Shifting'],
                ['value' => 'MSR', 'label' => 'Manual Special Recommendation']
            ],
            'status_code' => 200
        ], 200);
    }

    /**
     * Get allottee list for display
     */
    public function getAllotteeList(Request $request)
    {
        try {
            $request->validate([
                'allotment_process_date' => 'required|date',
                'allotment_process_no' => 'required|string',
                'allotment_process_type' => 'required|string|in:NAL,VSAL,CSAL,MSR'
            ]);

            $allotmentProcessDate = $request->allotment_process_date;
            $allotmentProcessNo = $request->allotment_process_no;
            $allotmentProcessType = $request->allotment_process_type;

            $query = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_allotment_status_master as hsm', 'hsm.short_code', '=', 'hoa.status')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_estate as he', 'hf.estate_id', '=', 'he.estate_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->join('housing_district as hd', 'he.district_code', '=', 'hd.district_code')
                ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
                ->where('hfo.allotment_date', $allotmentProcessDate)
                ->where('hfo.allotment_process_no', $allotmentProcessNo)
                ->whereRaw("substring(hfo.allotment_no, '\\w+') = ?", [$allotmentProcessType]);

            // Join based on type
            if ($allotmentProcessType == 'NAL' || $allotmentProcessType == 'MSR') {
                $query->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id');
            } elseif ($allotmentProcessType == 'VSAL') {
                $query->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                    ->join('housing_flat as hf1', 'hf1.flat_id', '=', 'hva.occupation_flat')
                    ->join('housing_block as hb1', 'hb1.block_id', '=', 'hva.occupation_block');
            } elseif ($allotmentProcessType == 'CSAL') {
                $query->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                    ->join('housing_flat as hf2', 'hf2.flat_id', '=', 'hca.occupation_flat')
                    ->join('housing_block as hb1', 'hb1.block_id', '=', 'hca.occupation_block');
            }

            // Status conditions
            $statuses = [
                'allotted', 'housing_sup_approved_1', 'housing_sup_reject_1',
                'applicant_acceptance', 'applicant_reject', 'ddo_verified_2',
                'ddo_reject_2', 'housing_sup_approved_2', 'housing_sup_reject_2',
                'offer_letter_cancel', 'offer_letter_extended', 'license_generate',
                'license_cancel', 'flat_possession_taken', 'housingapprover_approved_1',
                'housing_approver_reject_1', 'housing_official_approved', 'housing_official_reject',
                'housingapprover_approved_2', 'housing_approver_reject_2', 'license_extended',
                'flat_released'
            ];
            $query->whereIn('hoa.status', $statuses);

            // Select fields
            $query->select(
                'ha.*',
                'haod.*',
                'hoa.online_application_id',
                'hoa.date_of_application',
                'hoa.application_no',
                'hfo.allotment_no',
                'hfo.allotment_date',
                'hfo.roaster_vacancy_position',
                'hfo.allotment_reason',
                'hfo.allowed_for_floor_shifting',
                'hf.flat_no',
                'hf.floor',
                'hft.flat_type',
                'he.estate_name',
                'he.estate_address',
                'hd.district_name',
                'hoa.status',
                'hsm.status_description',
                'hb.block_name'
            );

            if ($allotmentProcessType == 'VSAL') {
                $query->addSelect('hva.*', 'hf1.flat_no as occupied_flat_vs', 'hf1.flat_type_id as flat_type_id_vs', 'hb1.block_name as block_name_vs');
            } elseif ($allotmentProcessType == 'CSAL') {
                $query->addSelect('hca.*', 'hf2.flat_no as occupied_flat_cs', 'hf2.flat_type_id as flat_type_id_cs', 'hb1.block_name as block_name_cs');
            }

            $query->orderBy('hfo.flat_occupant_id', 'asc');

            $allottees = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $allottees,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Allottee List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch allottee list',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get allottee list for approval
     */
    public function getAllotteeListForApprove(Request $request)
    {
        try {
            $request->validate([
                'allotment_process_date' => 'required|date',
                'allotment_process_no' => 'required|string',
                'allotment_process_type' => 'required|string|in:NAL,VSAL,CSAL,MSR'
            ]);

            $allotmentProcessDate = $request->allotment_process_date;
            $allotmentProcessNo = $request->allotment_process_no;
            $allotmentProcessType = $request->allotment_process_type;

            $query = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_allotment_status_master as hsm', 'hsm.short_code', '=', 'hoa.status')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_estate as he', 'hf.estate_id', '=', 'he.estate_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->join('housing_district as hd', 'he.district_code', '=', 'hd.district_code')
                ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
                ->where('hfo.allotment_date', $allotmentProcessDate)
                ->where('hfo.allotment_process_no', $allotmentProcessNo)
                ->whereRaw("substring(hfo.allotment_no, '\\w+') = ?", [$allotmentProcessType])
                ->whereNotNull('hfo.online_application_id');

            // Join based on type
            if ($allotmentProcessType == 'NAL') {
                $query->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                    ->join('housing_allotment_roaster_details as hard', 'hard.flat_occupant_id', '=', 'hfo.flat_occupant_id');
            } elseif ($allotmentProcessType == 'VSAL') {
                $query->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                    ->join('housing_flat as hf1', 'hf1.flat_id', '=', 'hva.occupation_flat')
                    ->join('housing_block as hb1', 'hb1.block_id', '=', 'hva.occupation_block');
            } elseif ($allotmentProcessType == 'CSAL') {
                $query->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                    ->join('housing_flat as hf2', 'hf2.flat_id', '=', 'hca.occupation_flat')
                    ->join('housing_block as hb1', 'hb1.block_id', '=', 'hca.occupation_block');
            } elseif ($allotmentProcessType == 'MSR') {
                $query->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id');
            }

            // Select fields
            $query->select(
                'ha.*',
                'haod.*',
                'hoa.online_application_id',
                'hoa.date_of_application',
                'hoa.application_no',
                'hfo.allotment_no',
                'hfo.allotment_date',
                'hfo.roaster_vacancy_position',
                'hfo.allotment_reason',
                'hfo.allowed_for_floor_shifting',
                'hf.flat_no',
                'hf.floor',
                'hft.flat_type',
                'he.estate_name',
                'he.estate_address',
                'hd.district_name',
                'hoa.status',
                'hsm.status_description',
                'hb.block_name'
            );

            if ($allotmentProcessType == 'VSAL') {
                $query->addSelect('hva.*', 'hf1.flat_no as occupied_flat_vs', 'hf1.flat_type_id as flat_type_id_vs', 'hb1.block_name as block_name_vs');
            } elseif ($allotmentProcessType == 'CSAL') {
                $query->addSelect('hca.*', 'hf2.flat_no as occupied_flat_cs', 'hf2.flat_type_id as flat_type_id_cs', 'hb1.block_name as block_name_cs');
            }

            $query->orderBy('hfo.flat_occupant_id', 'asc');

            $allottees = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $allottees,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Allottee List For Approve Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch allottee list for approval',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get allotment detail by online_application_id
     */
    public function getAllotmentDetail($encryptedAppId)
    {
        try {
            $appId = UrlEncryptionHelper::decrypt($encryptedAppId);

            if (!is_numeric($appId) || $appId <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid application ID',
                    'status_code' => 400
                ], 400);
            }

            $allotment = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_estate as he', 'hf.estate_id', '=', 'he.estate_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->join('housing_district as hd', 'he.district_code', '=', 'hd.district_code')
                ->where('hoa.status', 'allotted')
                ->where('hoa.online_application_id', $appId)
                ->select(
                    'ha.*',
                    'hnaa.*',
                    'hoa.online_application_id',
                    'hoa.date_of_application',
                    'hoa.application_no',
                    'hfo.allotment_no',
                    'hfo.allotment_date',
                    'hf.flat_no',
                    'hft.flat_type',
                    'he.estate_name',
                    'he.estate_address',
                    'hd.district_name'
                )
                ->first();

            if (!$allotment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Allotment not found',
                    'status_code' => 404
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $allotment,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Allotment Detail Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch allotment detail',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get allottee list on hold
     */
    public function getAllotteeListOnHold()
    {
        try {
            $allottees = DB::table('housing_online_application as hoa')
                ->join('housing_applicant_official_detail as haod', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                ->leftJoin('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->leftJoin('housing_flat_type as hft1', 'hft1.flat_type_id', '=', 'hnaa.flat_type_id')
                ->leftJoin('housing_flat_type as hft2', 'hft2.flat_type_id', '=', 'hca.flat_type_id')
                ->leftJoin('housing_flat_type as hft3', 'hft3.flat_type_id', '=', 'hva.flat_type_id')
                ->join('housing_allotment_status_master as hasm', 'hasm.short_code', '=', 'hoa.status')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
                ->join('housing_estate as he', 'hf.estate_id', '=', 'he.estate_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->join('housing_district as hd', 'he.district_code', '=', 'hd.district_code')
                ->where('hoa.status', 'allotment_on_hold')
                ->where('haod.is_active', 1)
                ->select(
                    'hoa.application_no',
                    'hoa.date_of_application',
                    'hoa.computer_serial_no',
                    'hoa.date_of_verified',
                    'hoa.online_application_id',
                    'hoa.status',
                    'ha.*',
                    'haod.*',
                    'hft1.flat_type as hft1_flat_type',
                    'hft2.flat_type as hft2_flat_type',
                    'hft3.flat_type as hft3_flat_type',
                    'hasm.status_description',
                    'hf.flat_no',
                    'hf.floor',
                    'hft.flat_type',
                    'he.estate_name',
                    'he.estate_address',
                    'hd.district_name',
                    'hb.block_name'
                )
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $allottees,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Allottee List On Hold Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch allottee list on hold',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Approve allotments
     */
    public function approveAllotments(Request $request)
    {
        try {
            $request->validate([
                'online_application_ids' => 'required|array',
                'online_application_ids.*' => 'required|integer'
            ]);

            $onlineApplicationIds = $request->online_application_ids;
            $user = auth()->user();
            $userId = $user->uid ?? $user->id ?? null;

            DB::beginTransaction();

            foreach ($onlineApplicationIds as $appId) {
                // Update online application status
                DB::table('housing_online_application')
                    ->where('online_application_id', $appId)
                    ->update([
                        'status' => 'housing_official_approved',
                        'date_of_verified' => now()
                    ]);

                // Insert process flow
                $statusId = DB::table('housing_allotment_status_master')
                    ->where('short_code', 'housing_official_approved')
                    ->value('status_id') ?? 9;

                DB::table('housing_process_flow')->insert([
                    'online_application_id' => $appId,
                    'status_id' => $statusId,
                    'created_at' => now(),
                    'uid' => $userId,
                    'short_code' => 'housing_official_approved'
                ]);

                // Update flat occupant
                DB::table('housing_flat_occupant')
                    ->where('online_application_id', $appId)
                    ->update([
                        'allotment_approve_or_reject_date' => now()->format('Y-m-d')
                    ]);

                // Get applicant details for email/SMS
                $applicant = DB::table('users as u')
                    ->join('housing_applicant_official_detail as haod', 'u.uid', '=', 'haod.uid')
                    ->join('housing_online_application as hoa', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                    ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                    ->where('hoa.online_application_id', $appId)
                    ->select('u.mail as email', 'ha.applicant_name', 'ha.mobile_no')
                    ->first();

                if ($applicant) {
                    // Send email
                    $subject = 'Offer of Allotment';
                    $message = '<html><body>Dear Applicant ' . $applicant->applicant_name . ',<br><br>
                    Dear Applicant, your application for RHE has been approved and a flat has been allotted to you. Kindly visit the portal to view allotment details and complete further necessary formalities. -Dept. of Housing, GoWB
                    <br><br>
                    Regards,<br>
                    Housing Department<br>
                    Government of West Bengal
                    </html></body>';

                    $this->notificationService->sendMail($applicant->email, $subject, $message, 'RHE');

                    // Send SMS
                    $smsMessage = 'Dear ' . $applicant->applicant_name . ', your application for RHE has been approved and a flat has been allotted to you. Kindly visit the portal to view allotment details and complete further necessary formalities. -Dept. of Housing, GoWB';
                    $templateId = '1107175508647688715';
                    $this->notificationService->sendSms($applicant->mobile_no, $smsMessage, $templateId);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Allotments approved successfully',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approve Allotments Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve allotments',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Reject allotments
     */
    public function rejectAllotments(Request $request)
    {
        try {
            $request->validate([
                'online_application_ids' => 'required|array',
                'online_application_ids.*' => 'required|integer'
            ]);

            $onlineApplicationIds = $request->online_application_ids;
            $user = auth()->user();
            $userId = $user->uid ?? $user->id ?? null;

            DB::beginTransaction();

            foreach ($onlineApplicationIds as $appId) {
                // Update online application status
                DB::table('housing_online_application')
                    ->where('online_application_id', $appId)
                    ->update([
                        'status' => 'housing_official_reject'
                    ]);

                // Update flat occupant
                DB::table('housing_flat_occupant')
                    ->where('online_application_id', $appId)
                    ->update([
                        'allotment_approve_or_reject_date' => now()->format('Y-m-d')
                    ]);

                // Get status_id
                $statusId = DB::table('housing_allotment_status_master')
                    ->where('short_code', 'housing_official_reject')
                    ->value('status_id');

                // Insert process flow
                DB::table('housing_process_flow')->insert([
                    'online_application_id' => $appId,
                    'status_id' => $statusId,
                    'created_at' => now(),
                    'uid' => $userId,
                    'short_code' => 'housing_official_reject'
                ]);

                // Get flat_id and applicant_official_detail_id
                $data = DB::table('housing_applicant_official_detail as haod')
                    ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                    ->leftJoin('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                    ->where('hoa.online_application_id', $appId)
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
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Allotments rejected successfully',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reject Allotments Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject allotments',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Hold allotments
     */
    public function holdAllotments(Request $request)
    {
        try {
            $request->validate([
                'online_application_ids' => 'required|array',
                'online_application_ids.*' => 'required|integer'
            ]);

            $onlineApplicationIds = $request->online_application_ids;
            $user = auth()->user();
            $userId = $user->uid ?? $user->id ?? null;

            DB::beginTransaction();

            foreach ($onlineApplicationIds as $appId) {
                // Update online application status
                DB::table('housing_online_application')
                    ->where('online_application_id', $appId)
                    ->update([
                        'status' => 'allotment_on_hold',
                        'date_of_verified' => now()
                    ]);

                // Get status_id
                $statusId = DB::table('housing_allotment_status_master')
                    ->where('short_code', 'allotment_on_hold')
                    ->value('status_id');

                // Insert process flow
                DB::table('housing_process_flow')->insert([
                    'online_application_id' => $appId,
                    'status_id' => $statusId,
                    'created_at' => now(),
                    'uid' => $userId,
                    'short_code' => 'allotment_on_hold'
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Allotments kept on hold successfully',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Hold Allotments Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to hold allotments',
                'status_code' => 500
            ], 500);
        }
    }
}

