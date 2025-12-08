<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;


class ExistingApplicantVsCsController extends Controller
{
    /**
     * Get flat-wise existing applicant details
     */
    
    public function getFlatApplicantDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rhe_name' => 'required|integer',
            'flat_type' => 'required|integer',
            'block_name' => 'required|integer',
            'flat_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $rheName  = $request->rhe_name;
        $flatType = $request->flat_type;
        $blockName = $request->block_name;
        $flatId   = $request->flat_id;

        // Logged-in user details
        $uid = auth()->user()->uid;
        $userDetails = DB::table('users_details')->where('uid', $uid)->first();

        /** -----------------------------------------
         *  🔹 Build Main Query (for housing_applicant)
         * ------------------------------------------
         */
        $baseQuery = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->join('users as u', 'u.uid', '=', 'haod.uid')
            ->where('hoa.status', 'existing_occupant')
            ->select([
                'hoa.online_application_id',
                'ha.applicant_name',
                'he.estate_name',
                'hft.flat_type',
                'haod.hrms_id',
                'u.status',
                'haod.uid',
                'hf.flat_id',
                'hb.block_name',
                'hf.flat_no',
            ]);

        /** -----------------------------------------
         *  🔹 Apply RHE / BLOCK / TYPE conditions
         * ------------------------------------------
         */
        if ($rheName && $flatType && $blockName && $flatId) {
            $baseQuery->where([
                ['hf.estate_id', $rheName],
                ['hf.flat_type_id', $flatType],
                ['hf.block_id', $blockName],
                ['hf.flat_id', $flatId],
            ]);
        }

        /** -----------------------------------------
         *  🔹 Apply division/subdivision filters (if available)
         * ------------------------------------------
         */
        if ($userDetails && !empty($userDetails->division_id)) {

            $baseQuery->where('he.division_id', $userDetails->division_id);

            if (!empty($userDetails->subdiv_id) && $userDetails->subdiv_id != 0) {
                $baseQuery->where('he.subdiv_id', $userDetails->subdiv_id);
            }
        }

        // Fetch main applicant data
        $data = $baseQuery->first();

        /** -----------------------------------------
         *  🔹 If NO data → check housing_existing_occupant_draft
         * ------------------------------------------
         */
        if (!$data) {
            $draftQuery = DB::table('housing_existing_occupant_draft as head')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'head.flat_id')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
                ->select([
                    'head.applicant_name',
                    'head.housing_existing_occupant_draft_id',
                    'he.estate_name',
                    'hft.flat_type',
                    'hf.flat_id',
                    'hb.block_name',
                    'hf.flat_no',
                ]);

            // Apply RHE filters
            if ($rheName && $flatType && $blockName && $flatId) {
                $draftQuery->where([
                    ['hf.estate_id', $rheName],
                    ['hf.flat_type_id', $flatType],
                    ['hf.block_id', $blockName],
                    ['hf.flat_id', $flatId],
                ]);
            }

            // Apply division filters
            if ($userDetails && !empty($userDetails->division_id)) {
                $draftQuery->where('he.division_id', $userDetails->division_id);

                if (!empty($userDetails->subdiv_id) && $userDetails->subdiv_id != 0) {
                    $draftQuery->where('he.subdiv_id', $userDetails->subdiv_id);
                }
            }

            $data = $draftQuery->first();
        }

       
        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'No applicant found for this flat.',
            ], 404);
        }

        /** -----------------------------------------
         *  🔹 Check VS/CS applications for same flat
         * ------------------------------------------
         */
        $uidValue = $data->uid ?? ($data->housing_existing_occupant_draft_id ?? 0);

        $existingVs = DB::table('housing_vs_application')
            ->where('occupation_flat', $flatId)
            ->exists();

        $existingCs = DB::table('housing_cs_application')
            ->where('occupation_flat', $flatId)
            ->exists();

        $alreadyApplied = false;

        if ($existingVs || $existingCs) {
            $vs = DB::table('housing_vs_application as hva')
                ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hva.online_application_id')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->where('hva.occupation_flat', $flatId)
                ->where('hoa.status', 'housingapprover_approved_1')
                ->where('haod.uid', $uidValue)
                ->exists();

            $cs = DB::table('housing_cs_application as hca')
                ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hca.online_application_id')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->where('hca.occupation_flat', $flatId)
                ->where('hoa.status', 'housingapprover_approved_1')
                ->where('haod.uid', $uidValue)
                ->exists();

            $alreadyApplied = $vs || $cs;
        }

        /** -----------------------------------------
         *  🔹 Encrypt UID
         * ------------------------------------------
         */
        
        $encryptedUid = Crypt::encryptString((string)$uidValue);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'already_applied' => $alreadyApplied,
            'encrypted_uid' => $encryptedUid,
        ]);


    }
    /**
     * Store VS/CS application
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_type' => 'required|in:VS,CS',
            'application_date' => 'required|date_format:d/m/Y',
            'applicant_name' => 'required|string|max:255',
            'applicant_father_name' => 'nullable|string|max:255',
            'permanent_street' => 'nullable|string|max:255',
            'permanent_city_town_village' => 'nullable|string|max:255',
            'permanent_post_office' => 'nullable|string|max:255',
            'permanent_district' => 'nullable|string|max:255',
            'permanent_pincode' => 'nullable|string|max:6',
            'present_street' => 'nullable|string|max:255',
            'present_city_town_village' => 'nullable|string|max:255',
            'present_post_office' => 'nullable|string|max:255',
            'present_district' => 'nullable|string|max:255',
            'present_pincode' => 'nullable|string|max:6',
            'mobile' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'dob' => 'nullable|date_format:d/m/Y',
            'gender' => 'required|in:M,F',
            'hrms_id' => 'nullable|string|max:10',
            'app_designation' => 'nullable|string|max:255',
            'pay_type_new' => 'required|in:new,old',
            'pay_band' => 'required|integer',
            'pay_in' => 'nullable|numeric|min:0',
            'app_posting_place' => 'nullable|string|max:255',
            'app_headquarter' => 'nullable|string|max:255',
            'doj' => 'nullable|date_format:d/m/Y',
            'dor' => 'required|date_format:d/m/Y',
            'office_name' => 'nullable|string|max:255',
            'office_street' => 'nullable|string|max:255',
            'office_city' => 'nullable|string|max:255',
            'office_post_office' => 'nullable|string|max:255',
            'office_district' => 'nullable|string|max:255',
            'office_pincode' => 'nullable|string|max:6',
            'office_phone_no' => 'nullable|string|max:15',
            'designation' => 'nullable|integer',
            'occupation_estate' => 'required|integer',
            'occupation_block' => 'required|integer',
            'occupation_flat' => 'required|integer',
            'possession_date' => 'required|date_format:d/m/Y',
            'license_no' => 'nullable|string|max:255',
            'physical_application_no' => 'nullable|string|max:255',
            'housing_applicant_id' => 'nullable|integer',
            'housing_hrms_id' => 'nullable|string',
            'housing_hidden_uid' => 'nullable|integer',
            'housing_hidden_uid_or_draft_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $housingHiddenHrmsId = $data['housing_hrms_id'] ?? '';
        $housingHiddenUid = $data['housing_hidden_uid'] ?? null;
        $housingHiddenUidOrDraftId = $data['housing_hidden_uid_or_draft_id'];

        DB::beginTransaction();
        try {
            // Handle user creation/update
            $uid = null;
            if (empty($housingHiddenHrmsId)) {
                // Create new user
                $username = $this->generateUsername($data['applicant_name'], $data['physical_application_no'] ?? '');
                
                if (!empty($data['hrms_id'])) {
                    $newUser = [
                        'name' => trim($data['hrms_id']),
                        'password' => Hash::make(trim($data['hrms_id'])),
                        'mail' => !empty($data['email']) ? trim($data['email']) : trim($data['hrms_id']) . '@gmail.com',
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s', time()),
                        // 'access' => time(),
                        // 'login' => time(),
                        // 'init' => !empty($data['email']) ? trim($data['email']) : trim($data['hrms_id']) . '@gmail.com',
                    ];
                } else {
                    $newUser = [
                        'name' => $username,
                        'password' => Hash::make($username),
                        'mail' => !empty($data['email']) ? trim($data['email']) : $username . '@gmail.com',
                        'status' => 1,
                        'created_at' => date('Y-m-d H:i:s', time()),
                        // 'access' => time(),
                        // 'login' => time(),
                        // 'init' => !empty($data['email']) ? trim($data['email']) : $username . '@gmail.com',
                    ];
                }

                $uid = DB::table('users')->insertGetId($newUser,'uid');

                // Handle draft to regular conversion if HRMS ID is provided
                if (!empty($data['hrms_id']) && is_numeric($housingHiddenUidOrDraftId)) {
                    $this->convertDraftToRegular($housingHiddenUidOrDraftId, $uid, $data);
                }
            } else {
                // Update existing user email
                if (!empty($data['email'])) {
                    DB::table('users')
                        ->where('name', $housingHiddenHrmsId)
                        ->update(['mail' => trim($data['email'])]);
                }
                $uid = $housingHiddenUid;
            }

            // Create/update housing applicant
            $applicantPersonalDetailArr = [
                'uid' => $uid,
                'applicant_name' => trim($data['applicant_name']),
                'guardian_name' => !empty($data['applicant_father_name']) ? trim($data['applicant_father_name']) : 'NA',
                'permanent_street' => !empty($data['permanent_street']) ? strtoupper(trim($data['permanent_street'])) : 'NA',
                'permanent_city_town_village' => !empty($data['permanent_city_town_village']) ? strtoupper(trim($data['permanent_city_town_village'])) : 'NA',
                'permanent_post_office' => !empty($data['permanent_post_office']) ? strtoupper(trim($data['permanent_post_office'])) : 'NA',
                'permanent_district' => !empty($data['permanent_district']) ? trim($data['permanent_district']) : '17',
                'permanent_pincode' => !empty($data['permanent_pincode']) ? trim($data['permanent_pincode']) : '700001',
                'present_street' => !empty($data['present_street']) ? strtoupper(trim($data['present_street'])) : 'NA',
                'present_city_town_village' => !empty($data['present_city_town_village']) ? strtoupper(trim($data['present_city_town_village'])) : 'NA',
                'present_post_office' => !empty($data['present_post_office']) ? strtoupper(trim($data['present_post_office'])) : 'NA',
                'present_district' => !empty($data['present_district']) ? trim($data['present_district']) : '17',
                'present_pincode' => !empty($data['present_pincode']) ? trim($data['present_pincode']) : '700001',
                'mobile_no' => !empty($data['mobile']) ? trim($data['mobile']) : '9999999999',
                'date_of_birth' => !empty($data['dob']) ? $this->convertDate($data['dob']) : '1900-01-01',
                'gender' => trim($data['gender']),
            ];

            $lastHousingApplicantId = DB::table('housing_applicant')->insertGetId($applicantPersonalDetailArr,'housing_applicant_id');

            // Assign user role
            DB::table('user_role')->insert([
                'uid' => $uid,
                'rid' => 4, // Applicant role
            ]);

            // Create official detail
            $appOffDetailArr = [
                'uid' => $uid,
                'hrms_id' => !empty($data['hrms_id']) ? trim($data['hrms_id']) : null,
                'applicant_designation' => !empty($data['app_designation']) ? strtoupper(trim($data['app_designation'])) : 'NA',
                'pay_band_id' => (int) $data['pay_band'],
                'pay_in_the_pay_band' => !empty($data['pay_in']) ? trim($data['pay_in']) : '1',
                'applicant_posting_place' => !empty($data['app_posting_place']) ? strtoupper(trim($data['app_posting_place'])) : 'NA',
                'applicant_headquarter' => !empty($data['app_headquarter']) ? strtoupper(trim($data['app_headquarter'])) : 'NA',
                'date_of_joining' => !empty($data['doj']) ? $this->convertDate($data['doj']) : '1900-01-01',
                'date_of_retirement' => $this->convertDate($data['dor']),
                'office_name' => !empty($data['office_name']) ? strtoupper(trim($data['office_name'])) : 'NA',
                'office_street' => !empty($data['office_street']) ? strtoupper(trim($data['office_street'])) : 'NA',
                'office_city_town_village' => !empty($data['office_city']) ? strtoupper(trim($data['office_city'])) : 'NA',
                'office_post_office' => !empty($data['office_post_office']) ? strtoupper(trim($data['office_post_office'])) : 'NA',
                'office_district' => !empty($data['office_district']) ? trim($data['office_district']) : '17',
                'office_pin_code' => !empty($data['office_pincode']) ? trim($data['office_pincode']) : '700001',
                'office_phone_no' => !empty($data['office_phone_no']) ? trim($data['office_phone_no']) : '033-22222222',
                'ddo_id' => !empty($data['designation']) ? (int) $data['designation'] : null,
                'housing_applicant_id' => $lastHousingApplicantId,
                'is_active' => (!empty($data['hrms_id']) || !empty($housingHiddenHrmsId)) ? 1 : 0,
            ];

            // If updating existing, deactivate old record
            if (!empty($housingHiddenHrmsId) && !empty($data['housing_applicant_id'])) {
                DB::table('housing_applicant_official_detail')
                    ->where('housing_applicant_id', $data['housing_applicant_id'])
                    ->update(['is_active' => 0]);
            }

            $applicantOfficialDetailId = DB::table('housing_applicant_official_detail')->insertGetId($appOffDetailArr,'applicant_official_detail_id');

            // Create online application
            $onlineAppArr = [
                'applicant_official_detail_id' => $applicantOfficialDetailId,
                'status' => 'housingapprover_approved_1',
                'is_backlog_applicant' => 1,
                'physical_application_no' => !empty($data['physical_application_no']) ? trim($data['physical_application_no']) : 'NA',
                'application_no' => trim($data['application_type']),
                'date_of_application' => $this->convertDate($data['application_date']),
            ];

            $lastOnlineApplicationId = DB::table('housing_online_application')->insertGetId($onlineAppArr,'online_application_id');

            // Get flat type ID from pay band
            $flatTypeId = DB::table('housing_pay_band_categories')
                ->where('pay_band_id', $data['pay_band'])
                ->value('flat_type_id');

            // Create VS or CS application
            $shiftingAppArr = [
                'online_application_id' => $lastOnlineApplicationId,
                'flat_type_id' => $flatTypeId,
                'occupation_estate' => (int) $data['occupation_estate'],
                'occupation_block' => (int) $data['occupation_block'],
                'occupation_flat' => (int) $data['occupation_flat'],
                'possession_date' => $this->convertDate($data['possession_date']),
                'license_no' => !empty($data['license_no']) ? trim($data['license_no']) : 'NA',
            ];

            if (trim($data['application_type']) == 'VS') {
                DB::table('housing_vs_application')->insert($shiftingAppArr);
            } else if (trim($data['application_type']) == 'CS') {
                DB::table('housing_cs_application')->insert($shiftingAppArr);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Application submitted successfully.',
                'data' => ['online_application_id' => $lastOnlineApplicationId],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to submit application: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get VS list (with HRMS)
     */
    public function vsListWithHrms(Request $request)
    {
        $rheName = $request->input('rhe_name', 0);
        $flatType = $request->input('flat_type', 0);

        $user = auth()->user();
        $userDetails = DB::table('users_details')->where('uid', $user->uid)->first();

        $query = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hva.occupation_flat')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hva.occupation_estate')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hva.occupation_block')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hva.flat_type_id')
            ->where('hoa.application_no', 'LIKE', 'VS%')
            ->where('hoa.is_backlog_applicant', 1)
            ->where('haod.is_active', 1)
            ->whereNotNull('haod.hrms_id');

        if ($userDetails && !empty($userDetails->division_id)) {
            if (!empty($userDetails->subdiv_id) && $userDetails->subdiv_id != 0) {
                $query->where('he.division_id', $userDetails->division_id)
                    ->where('he.subdiv_id', $userDetails->subdiv_id);
            } else {
                $query->where('he.division_id', $userDetails->division_id);
            }
        }

        if ($rheName != 0 && $flatType != 0) {
            $query->where('hf.estate_id', $rheName)
                ->where('hf.flat_type_id', $flatType);
        }

        $applications = $query->select([
            'ha.applicant_name',
            'ha.mobile_no',
            'ha.gender',
            'hoa.application_no',
            'hoa.date_of_application',
            'hoa.online_application_id',
            'haod.hrms_id',
            'he.estate_name',
            'hb.block_name',
            'hft.flat_type',
            'hf.flat_no',
            'hf.flat_id',
        ])
        ->orderBy('hoa.date_of_application', 'ASC')
        ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $applications->items(),
            'total' => $applications->total(),
            'per_page' => $applications->perPage(),
            'current_page' => $applications->currentPage(),
            'last_page' => $applications->lastPage(),
        ]);
    }

    /**
     * Get VS list (without HRMS)
     */
    public function vsListWithoutHrms(Request $request)
    {
        $rheName = $request->input('rhe_name', 0);
        $flatType = $request->input('flat_type', 0);

        $user = auth()->user();
        $userDetails = DB::table('users_details')->where('uid', $user->uid)->first();

        $query = DB::table('housing_existing_occupant_draft as heod')
            ->join('housing_vs_application as hva', 'hva.occupation_flat', '=', 'heod.flat_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'heod.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hva.occupation_estate')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hva.occupation_block')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hva.flat_type_id')
            ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hva.online_application_id')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join('users as u', 'u.uid', '=', 'haod.uid')
            ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
            ->where('hoa.application_no', 'LIKE', 'VS%')
            ->where('hoa.is_backlog_applicant', 1)
            ->whereNull('haod.hrms_id');

        if ($userDetails && !empty($userDetails->division_id)) {
            if (!empty($userDetails->subdiv_id) && $userDetails->subdiv_id != 0) {
                $query->where('he.division_id', $userDetails->division_id)
                    ->where('he.subdiv_id', $userDetails->subdiv_id);
            } else {
                $query->where('he.division_id', $userDetails->division_id);
            }
        }

        if ($rheName != 0 && $flatType != 0) {
            $query->where('hf.estate_id', $rheName)
                ->where('hf.flat_type_id', $flatType);
        }

        $applications = $query->select([
            'ha.applicant_name',
            'ha.mobile_no',
            'ha.gender',
            'hoa.application_no',
            'hoa.date_of_application',
            'hoa.online_application_id',
            'he.estate_name',
            'hb.block_name',
            'hft.flat_type',
            'hf.flat_no',
            'hf.flat_id',
        ])
        ->orderBy('hoa.date_of_application', 'ASC')
        ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $applications->items(),
            'total' => $applications->total(),
            'per_page' => $applications->perPage(),
            'current_page' => $applications->currentPage(),
            'last_page' => $applications->lastPage(),
        ]);
    }

    /**
     * Get CS list (with HRMS)
     */
    public function csListWithHrms(Request $request)
    {
        $rheName = $request->input('rhe_name', 0);
        $flatType = $request->input('flat_type', 0);

        $user = auth()->user();
        $userDetails = DB::table('users_details')->where('uid', $user->uid)->first();

        $query = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hca.occupation_flat')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hca.occupation_estate')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hca.occupation_block')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hca.flat_type_id')
            ->where('hoa.application_no', 'LIKE', 'CS%')
            ->where('hoa.is_backlog_applicant', 1)
            ->where('haod.is_active', 1)
            ->whereNotNull('haod.hrms_id');

        if ($userDetails && !empty($userDetails->division_id)) {
            if (!empty($userDetails->subdiv_id) && $userDetails->subdiv_id != 0) {
                $query->where('he.division_id', $userDetails->division_id)
                    ->where('he.subdiv_id', $userDetails->subdiv_id);
            } else {
                $query->where('he.division_id', $userDetails->division_id);
            }
        }

        if ($rheName != 0 && $flatType != 0) {
            $query->where('hf.estate_id', $rheName)
                ->where('hf.flat_type_id', $flatType);
        }

        $applications = $query->select([
            'ha.applicant_name',
            'ha.mobile_no',
            'ha.gender',
            'hoa.application_no',
            'hoa.date_of_application',
            'hoa.online_application_id',
            'haod.hrms_id',
            'he.estate_name',
            'hb.block_name',
            'hft.flat_type',
            'hf.flat_no',
            'hf.flat_id',
        ])
        ->orderBy('hoa.date_of_application', 'ASC')
        ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $applications->items(),
            'total' => $applications->total(),
            'per_page' => $applications->perPage(),
            'current_page' => $applications->currentPage(),
            'last_page' => $applications->lastPage(),
        ]);
    }

    /**
     * Get CS list (without HRMS)
     */
    public function csListWithoutHrms(Request $request)
    {
        $rheName = $request->input('rhe_name', 0);
        $flatType = $request->input('flat_type', 0);

        $user = auth()->user();
        $userDetails = DB::table('users_details')->where('uid', $user->uid)->first();

        $query = DB::table('housing_existing_occupant_draft as heod')
            ->join('housing_cs_application as hca', 'hca.occupation_flat', '=', 'heod.flat_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'heod.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hca.occupation_estate')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hca.occupation_block')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hca.flat_type_id')
            ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hca.online_application_id')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join('users as u', 'u.uid', '=', 'haod.uid')
            ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
            ->where('hoa.application_no', 'LIKE', 'CS%')
            ->where('hoa.is_backlog_applicant', 1)
            ->whereNull('haod.hrms_id');

        if ($userDetails && !empty($userDetails->division_id)) {
            if (!empty($userDetails->subdiv_id) && $userDetails->subdiv_id != 0) {
                $query->where('he.division_id', $userDetails->division_id)
                    ->where('he.subdiv_id', $userDetails->subdiv_id);
            } else {
                $query->where('he.division_id', $userDetails->division_id);
            }
        }

        if ($rheName != 0 && $flatType != 0) {
            $query->where('hf.estate_id', $rheName)
                ->where('hf.flat_type_id', $flatType);
        }

        $applications = $query->select([
            'ha.applicant_name',
            'ha.mobile_no',
            'ha.gender',
            'hoa.application_no',
            'hoa.date_of_application',
            'hoa.online_application_id',
            'he.estate_name',
            'hb.block_name',
            'hft.flat_type',
            'hf.flat_no',
            'hf.flat_id',
        ])
        ->orderBy('hoa.date_of_application', 'ASC')
        ->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $applications->items(),
            'total' => $applications->total(),
            'per_page' => $applications->perPage(),
            'current_page' => $applications->currentPage(),
            'last_page' => $applications->lastPage(),
        ]);
    }

    /**
     * Get VS/CS application details for edit
     */
    public function show($id)
    {
        // Get application type first
        $onlineApp = DB::table('housing_online_application')
            ->where('online_application_id', $id)
            ->first();

        if (!$onlineApp) {
            return response()->json(['status' => 'error', 'message' => 'Application not found.'], 404);
        }

        $isVs = strpos($onlineApp->application_no, 'VS') === 0;
        $isCs = strpos($onlineApp->application_no, 'CS') === 0;

        $application = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->leftJoin('users as u', 'u.uid', '=', 'haod.uid')
            ->where('hoa.online_application_id', $id)
            ->select([
                'hoa.*',
                'ha.*',
                'haod.*',
                'u.mail as email',
            ])
            ->first();

        if (!$application) {
            return response()->json(['status' => 'error', 'message' => 'Application not found.'], 404);
        }

        // Get VS or CS specific data
        if ($isVs) {
            $vsData = DB::table('housing_vs_application')
                ->where('online_application_id', $id)
                ->first();
            if ($vsData) {
                foreach ($vsData as $key => $value) {
                    $application->$key = $value;
                }
            }
        } elseif ($isCs) {
            $csData = DB::table('housing_cs_application')
                ->where('online_application_id', $id)
                ->first();
            if ($csData) {
                foreach ($csData as $key => $value) {
                    $application->$key = $value;
                }
            }
        }

        if (!$application) {
            return response()->json(['status' => 'error', 'message' => 'Application not found.'], 404);
        }

        // Format dates
        if (!empty($application->date_of_birth)) {
            $application->date_of_birth = Carbon::parse($application->date_of_birth)->format('d/m/Y');
        }
        if (!empty($application->date_of_joining)) {
            $application->date_of_joining = Carbon::parse($application->date_of_joining)->format('d/m/Y');
        }
        if (!empty($application->date_of_retirement)) {
            $application->date_of_retirement = Carbon::parse($application->date_of_retirement)->format('d/m/Y');
        }
        if (!empty($application->date_of_application)) {
            $application->date_of_application = Carbon::parse($application->date_of_application)->format('d/m/Y');
        }
        if (!empty($application->possession_date)) {
            $application->possession_date = Carbon::parse($application->possession_date)->format('d/m/Y');
        }

        return response()->json(['status' => 'success', 'data' => $application]);
    }

    /**
     * Update VS/CS application
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'application_type' => 'required|in:VS,CS',
            'application_date' => 'required|date_format:d/m/Y',
            'applicant_name' => 'required|string|max:255',
            'applicant_father_name' => 'nullable|string|max:255',
            'permanent_street' => 'nullable|string|max:255',
            'permanent_city_town_village' => 'nullable|string|max:255',
            'permanent_post_office' => 'nullable|string|max:255',
            'permanent_district' => 'nullable|string|max:255',
            'permanent_pincode' => 'nullable|string|max:6',
            'present_street' => 'nullable|string|max:255',
            'present_city_town_village' => 'nullable|string|max:255',
            'present_post_office' => 'nullable|string|max:255',
            'present_district' => 'nullable|string|max:255',
            'present_pincode' => 'nullable|string|max:6',
            'mobile' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'dob' => 'nullable|date_format:d/m/Y',
            'gender' => 'required|in:M,F',
            'hrms_id' => 'nullable|string|max:10',
            'app_designation' => 'nullable|string|max:255',
            'pay_type_new' => 'required|in:new,old',
            'pay_band' => 'required|integer',
            'pay_in' => 'nullable|numeric|min:0',
            'app_posting_place' => 'nullable|string|max:255',
            'app_headquarter' => 'nullable|string|max:255',
            'doj' => 'nullable|date_format:d/m/Y',
            'dor' => 'required|date_format:d/m/Y',
            'office_name' => 'nullable|string|max:255',
            'office_street' => 'nullable|string|max:255',
            'office_city' => 'nullable|string|max:255',
            'office_post_office' => 'nullable|string|max:255',
            'office_district' => 'nullable|string|max:255',
            'office_pincode' => 'nullable|string|max:6',
            'office_phone_no' => 'nullable|string|max:15',
            'designation' => 'nullable|integer',
            'occupation_estate' => 'required|integer',
            'occupation_block' => 'required|integer',
            'occupation_flat' => 'required|integer',
            'possession_date' => 'required|date_format:d/m/Y',
            'license_no' => 'nullable|string|max:255',
            'physical_application_no' => 'nullable|string|max:255',
            'housing_applicant_id' => 'nullable|integer',
            'housing_hrms_id' => 'nullable|string',
            'housing_hidden_uid' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $housingHiddenHrmsId = $data['housing_hrms_id'] ?? '';
        $housingHiddenUid = $data['housing_hidden_uid'] ?? null;
        $housingApplicantId = $data['housing_applicant_id'] ?? null;

        // Get existing application
        $existingApp = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->where('hoa.online_application_id', $id)
            ->select('hoa.*', 'haod.*')
            ->first();

        if (!$existingApp) {
            return response()->json(['status' => 'error', 'message' => 'Application not found.'], 404);
        }

        DB::beginTransaction();
        try {
            $uid = $housingHiddenUid ?? $existingApp->uid;

            // Update user email if provided
            if (!empty($data['email']) && !empty($housingHiddenHrmsId)) {
                DB::table('users')
                    ->where('name', $housingHiddenHrmsId)
                    ->update(['mail' => trim($data['email'])]);
            }

            // Update housing applicant
            if ($housingApplicantId) {
                $applicantPersonalDetailArr = [
                    'applicant_name' => trim($data['applicant_name']),
                    'guardian_name' => !empty($data['applicant_father_name']) ? trim($data['applicant_father_name']) : 'NA',
                    'permanent_street' => !empty($data['permanent_street']) ? strtoupper(trim($data['permanent_street'])) : 'NA',
                    'permanent_city_town_village' => !empty($data['permanent_city_town_village']) ? strtoupper(trim($data['permanent_city_town_village'])) : 'NA',
                    'permanent_post_office' => !empty($data['permanent_post_office']) ? strtoupper(trim($data['permanent_post_office'])) : 'NA',
                    'permanent_district' => !empty($data['permanent_district']) ? trim($data['permanent_district']) : '17',
                    'permanent_pincode' => !empty($data['permanent_pincode']) ? trim($data['permanent_pincode']) : '700001',
                    'present_street' => !empty($data['present_street']) ? strtoupper(trim($data['present_street'])) : 'NA',
                    'present_city_town_village' => !empty($data['present_city_town_village']) ? strtoupper(trim($data['present_city_town_village'])) : 'NA',
                    'present_post_office' => !empty($data['present_post_office']) ? strtoupper(trim($data['present_post_office'])) : 'NA',
                    'present_district' => !empty($data['present_district']) ? trim($data['present_district']) : '17',
                    'present_pincode' => !empty($data['present_pincode']) ? trim($data['present_pincode']) : '700001',
                    'mobile_no' => !empty($data['mobile']) ? trim($data['mobile']) : '9999999999',
                    'date_of_birth' => !empty($data['dob']) ? $this->convertDate($data['dob']) : '1900-01-01',
                    'gender' => trim($data['gender']),
                ];

                DB::table('housing_applicant')
                    ->where('housing_applicant_id', $housingApplicantId)
                    ->update($applicantPersonalDetailArr);
            }

            // Update official detail
            $appOffDetailArr = [
                'hrms_id' => !empty($data['hrms_id']) ? trim($data['hrms_id']) : null,
                'applicant_designation' => !empty($data['app_designation']) ? strtoupper(trim($data['app_designation'])) : 'NA',
                'pay_band_id' => (int) $data['pay_band'],
                'pay_in_the_pay_band' => !empty($data['pay_in']) ? trim($data['pay_in']) : '1',
                'applicant_posting_place' => !empty($data['app_posting_place']) ? strtoupper(trim($data['app_posting_place'])) : 'NA',
                'applicant_headquarter' => !empty($data['app_headquarter']) ? strtoupper(trim($data['app_headquarter'])) : 'NA',
                'date_of_joining' => !empty($data['doj']) ? $this->convertDate($data['doj']) : '1900-01-01',
                'date_of_retirement' => $this->convertDate($data['dor']),
                'office_name' => !empty($data['office_name']) ? strtoupper(trim($data['office_name'])) : 'NA',
                'office_street' => !empty($data['office_street']) ? strtoupper(trim($data['office_street'])) : 'NA',
                'office_city_town_village' => !empty($data['office_city']) ? strtoupper(trim($data['office_city'])) : 'NA',
                'office_post_office' => !empty($data['office_post_office']) ? strtoupper(trim($data['office_post_office'])) : 'NA',
                'office_district' => !empty($data['office_district']) ? trim($data['office_district']) : '17',
                'office_pin_code' => !empty($data['office_pincode']) ? trim($data['office_pincode']) : '700001',
                'office_phone_no' => !empty($data['office_phone_no']) ? trim($data['office_phone_no']) : '033-22222222',
                'ddo_id' => !empty($data['designation']) ? (int) $data['designation'] : null,
                'is_active' => (!empty($data['hrms_id']) || !empty($housingHiddenHrmsId)) ? 1 : 0,
            ];

            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $existingApp->applicant_official_detail_id)
                ->update($appOffDetailArr);

            // Update online application
            $onlineAppArr = [
                'physical_application_no' => !empty($data['physical_application_no']) ? trim($data['physical_application_no']) : 'NA',
                'application_no' => trim($data['application_type']),
                'date_of_application' => $this->convertDate($data['application_date']),
            ];

            DB::table('housing_online_application')
                ->where('online_application_id', $id)
                ->update($onlineAppArr);

            // Get flat type ID from pay band
            $flatTypeId = DB::table('housing_pay_band_categories')
                ->where('pay_band_id', $data['pay_band'])
                ->value('flat_type_id');

            // Update VS or CS application
            $shiftingAppArr = [
                'flat_type_id' => $flatTypeId,
                'occupation_estate' => (int) $data['occupation_estate'],
                'occupation_block' => (int) $data['occupation_block'],
                'occupation_flat' => (int) $data['occupation_flat'],
                'possession_date' => $this->convertDate($data['possession_date']),
                'license_no' => !empty($data['license_no']) ? trim($data['license_no']) : 'NA',
            ];

            if (trim($data['application_type']) == 'VS') {
                DB::table('housing_vs_application')
                    ->where('online_application_id', $id)
                    ->update($shiftingAppArr);
            } else if (trim($data['application_type']) == 'CS') {
                DB::table('housing_cs_application')
                    ->where('online_application_id', $id)
                    ->update($shiftingAppArr);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Application updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to update application: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper: Convert date from DD/MM/YYYY to YYYY-MM-DD
     */
    private function convertDate(?string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('d/m/Y', $dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Helper: Generate username
     */
    private function generateUsername(string $applicantName, string $physicalApplicationNo = ''): string
    {
        $wordCount = str_word_count(trim($applicantName));
        $pieces = explode(" ", trim($applicantName));
        $name = '';

        if ($wordCount < 2) {
            $name = strtolower(substr($pieces[0], 0, 3));
        } elseif ($wordCount == 2) {
            if (in_array(strtolower($pieces[0]), ['dr.', 'dr'])) {
                $name = strtolower(substr($pieces[1], 0, 3));
            } else {
                $name = strtolower(substr($pieces[0], 0, 3)) . strtolower(substr($pieces[1], 0, 3));
            }
        } else {
            if (in_array(strtolower($pieces[0]), ['dr.', 'dr'])) {
                $name = strtolower(substr($pieces[1], 0, 3)) . strtolower(substr($pieces[2], 0, 3));
            } else {
                $name = strtolower(substr($pieces[0], 0, 3)) . strtolower(substr($pieces[1], 0, 3));
            }
        }

        $physicalApplicationNoClean = preg_replace('/[^a-zA-Z0-9_]/', '_', $physicalApplicationNo);
        return str_replace(".", "", $name) . '_' . $physicalApplicationNoClean . '_' . rand(1, 100000);
    }

    /**
     * Helper: Convert draft to regular existing occupant
     */
    private function convertDraftToRegular($draftId, $uid, $data)
    {
        $draftData = DB::table('housing_existing_occupant_draft as heod')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'heod.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->where('heod.housing_existing_occupant_draft_id', $draftId)
            ->select([
                'heod.*',
                'hf.flat_id',
                'he.estate_id',
                'he.estate_name',
                'hb.block_id',
                'hb.block_name',
                'hft.flat_type_id',
                'hf.flat_no',
            ])
            ->first();

        if (!$draftData) {
            return;
        }

        // Create housing applicant
        $housingApplicantId = DB::table('housing_applicant')->insertGetId([
            'uid' => $uid,
            'applicant_name' => $draftData->applicant_name,
            'guardian_name' => $draftData->guardian_name ?? 'NA',
            'gender' => $draftData->gender ?? 'M',
            'date_of_birth' => $draftData->date_of_birth ?? '1900-01-01',
            'mobile_no' => $draftData->mobile_no ?? '9999999999',
            'permanent_street' => $draftData->permanent_street ?? 'NA',
            'permanent_city_town_village' => $draftData->permanent_city_town_village ?? 'NA',
            'permanent_post_office' => $draftData->permanent_post_office ?? 'NA',
            'permanent_district' => $draftData->permanent_district ?? '17',
            'permanent_pincode' => $draftData->permanent_pincode ?? '700001',
            'present_street' => $draftData->present_street ?? 'NA',
            'present_city_town_village' => $draftData->present_city_town_village ?? 'NA',
            'present_post_office' => $draftData->present_post_office ?? 'NA',
            'present_district' => $draftData->present_district ?? '17',
            'present_pincode' => $draftData->present_pincode ?? '700001',
        ], 'housing_applicant_id');

        // Create official detail
        $checkedDdo = (!empty($draftData->ddo_id) && $draftData->ddo_id != '') ? $draftData->ddo_id : 1263;
        $checkedPayBandId = (!empty($draftData->pay_band_id) && $draftData->pay_band_id != '') ? $draftData->pay_band_id : 10;

        $applicantOfficialDetailId = DB::table('housing_applicant_official_detail')->insertGetId([
            'uid' => $uid,
            'housing_applicant_id' => $housingApplicantId,
            'hrms_id' => $data['hrms_id'],
            'ddo_id' => $checkedDdo,
            'applicant_designation' => $draftData->applicant_designation ?? 'NA',
            'applicant_headquarter' => $draftData->applicant_headquarter ?? 'NA',
            'applicant_posting_place' => $draftData->applicant_posting_place ?? 'NA',
            'pay_band_id' => $checkedPayBandId,
            'pay_in_the_pay_band' => $draftData->pay_in_the_pay_band ?? '1',
            'date_of_joining' => $draftData->date_of_joining ?? '1900-01-01',
            'date_of_retirement' => $draftData->date_of_retirement ?? '1900-01-01',
            'office_name' => $draftData->office_name ?? 'NA',
            'office_street' => $draftData->office_street ?? 'NA',
            'office_city_town_village' => $draftData->office_city_town_village ?? 'NA',
            'office_post_office' => $draftData->office_post_office ?? 'NA',
            'office_pin_code' => $draftData->office_pin_code ?? '700001',
            'office_district' => $draftData->office_district ?? '17',
            'office_phone_no' => $draftData->office_phone_no ?? '033-22222222',
            'is_active' => 0,
        ],'applicant_official_detail_id');

        // Create online application
        $onlineApplicationId = DB::table('housing_online_application')->insertGetId([
            'applicant_official_detail_id' => $applicantOfficialDetailId,
            'status' => 'existing_occupant',
            'is_backlog_applicant' => 2,
        ],'online_application_id');

        DB::table('housing_online_application')
            ->where('online_application_id', $onlineApplicationId)
            ->update(['application_no' => 'EO-' . trim(date('dmY')) . '-' . $onlineApplicationId]);

        // Create flat occupant
        $flatOccupantId = DB::table('housing_flat_occupant')->insertGetId([
            'online_application_id' => $onlineApplicationId,
            'flat_id' => $draftData->flat_id,
            'allotment_date' => null,
        ],'flat_occupant_id');

        // Update flat status
        DB::table('housing_flat')
            ->where('flat_id', $draftData->flat_id)
            ->update(['flat_status_id' => 2]);

        // Create occupant license if exists
        if (!empty($draftData->license_no)) {
            DB::table('housing_occupant_license')->insert([
                'flat_occupant_id' => $flatOccupantId,
                'license_no' => $draftData->license_no,
                'license_issue_date' => $draftData->license_issue_date ?? null,
                'license_expiry_date' => $draftData->license_expiry_date ?? null,
                'authorised_or_not' => $draftData->authorised_or_not ?? 0,
            ]);
        }

        // Create new allotment application
        DB::table('housing_new_allotment_application')->insert([
            'online_application_id' => $onlineApplicationId,
        ]);
    }
}

