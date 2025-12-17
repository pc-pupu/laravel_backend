<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExistingApplicantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExistingApplicantController extends Controller
{
    /**
     * List all existing applicants (legacy/physical applicants)
     */
    public function index(Request $request)
    {
        
        $query = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('hoa.application_no', 'LIKE', 'PA%')
            ->where('hoa.is_backlog_applicant', '=', 1);

        // Filter by HRMS ID presence
        // Note: null, 0, and blank are all considered as "null data" for hrms_id
        if ($request->filled('has_hrms')) {
            if ($request->input('has_hrms') == '1') {
                // Has HRMS ID: not null, not 0, not blank
                $query->whereNotNull('haod.hrms_id')
                    ->where('haod.hrms_id', '!=', 0)
                    ->whereRaw("CAST(haod.hrms_id AS TEXT) != ''")
                    ->whereRaw("TRIM(CAST(haod.hrms_id AS TEXT)) != ''");
            } else {
                // No HRMS ID: null, 0, or blank
                $query->where(function($q) {
                    $q->whereNull('haod.hrms_id')
                        ->orWhere('haod.hrms_id', 0)
                        ->orWhereRaw("CAST(haod.hrms_id AS TEXT) = ''")
                        ->orWhereRaw("TRIM(CAST(haod.hrms_id AS TEXT)) = ''");
                });
            }
        }
        

        // Search
        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(ha.applicant_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(hoa.application_no) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(hoa.computer_serial_no) LIKE ?', ["%{$search}%"]);
            });
        }

        $query->select([
            'ha.housing_applicant_id',
            'ha.applicant_name',
            'ha.guardian_name',
            'ha.mobile_no',
            'ha.gender',
            'ha.present_street',
            'ha.present_city_town_village',
            'ha.present_post_office',
            'ha.present_pincode',
            'ha.present_district',
            'ha.permanent_street',
            'ha.permanent_city_town_village',
            'ha.permanent_post_office',
            'ha.permanent_pincode',
            'ha.permanent_district',
            'hoa.application_no',
            'hoa.date_of_application',
            'hoa.computer_serial_no',
            'hoa.online_application_id',
            'hoa.physical_application_no',
            'haod.hrms_id',
            'haod.uid'
        ]);

        // Order by computer serial no (cast to integer)
        $query->orderByRaw("
        CAST(regexp_replace(hoa.computer_serial_no, '[^0-9]', '', 'g') AS INTEGER) ASC,
        regexp_replace(hoa.computer_serial_no, '[0-9]', '', 'g') ASC
        ");
        $perPage = (int) $request->input('per_page', 15);
        
        try {
            $applicants = $query->paginate($perPage);

                return response()->json([
                    'status' => 'success',
                    'data' => $applicants,
                ]);
        } catch (\Exception $e) {
            \Log::error('Pagination error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }

    }

    /**
     * List applicants with HRMS ID
     */
    public function withHrms(Request $request)
    {
        $request->merge(['has_hrms' => '1']);
        return $this->index($request);
    }

    /**
     * List applicants without HRMS ID
     */
    public function withoutHrms(Request $request)
    {
        $request->merge(['has_hrms' => '0']);
        return $this->index($request);
    }

    /**
     * Search by physical application no or computer serial no
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search_type' => 'required|in:physical_app_no,computer_serial_no',
            'search_value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid search parameters.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = DB::table('housing_online_application as hoa')
            ->select('hoa.application_no', 'hoa.online_application_id');

        if ($request->input('search_type') === 'physical_app_no') {
            $query->where('hoa.physical_application_no', $request->input('search_value'));
        } else {
            $query->where('hoa.computer_serial_no', $request->input('search_value'));
        }

        $result = $query->first();

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'No matching application found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Get applicant details
     */
    public function show($id)
    {
       
        $applicant = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->leftJoin('users as u', 'u.uid', '=', 'haod.uid')
            ->leftJoin('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->leftJoin('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
            ->where('hoa.online_application_id', $id)
            ->where('hoa.application_no', 'LIKE', 'PA%')
            ->first();

        if (!$applicant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Applicant not found.',
            ], 404);
        }

        // Format dates from YYYY-MM-DD to DD/MM/YYYY
        if (!empty($applicant->date_of_birth)) {
            $applicant->dob = Carbon::parse($applicant->date_of_birth)->format('d/m/Y');
        }
        if (!empty($applicant->date_of_joining)) {
            $applicant->doj = Carbon::parse($applicant->date_of_joining)->format('d/m/Y');
        }
        if (!empty($applicant->date_of_retirement)) {
            $applicant->dor = Carbon::parse($applicant->date_of_retirement)->format('d/m/Y');
        }
        if (!empty($applicant->date_of_application)) {
            $applicant->doa = Carbon::parse($applicant->date_of_application)->format('d/m/Y');
        }

        // Get RHE flat type from pay band
        if (!empty($applicant->pay_band_id)) {
            $flatType = DB::table('housing_pay_band_categories as hpbc')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hpbc.flat_type_id')
                ->where('hpbc.pay_band_id', $applicant->pay_band_id)
                ->value('hft.flat_type');
            $applicant->rhe_flat_type = $flatType ?? '';
        }

        return response()->json([
            'status' => 'success',
            'data' => $applicant,
        ]);
    }

    /**
     * Create new existing applicant
     * Based on Drupal existing_applicant_form_submit function
     */
    public function store(Request $request)
    {
        // Validation rules based on Drupal form validation
        $validator = Validator::make($request->all(), [
            'applicant_name' => 'required|string|max:255',
            'applicant_father_name' => 'required|string|max:255',
            'dob' => 'required|date_format:d/m/Y',
            'gender' => 'required|in:M,F',
            'mobile' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'hrms_id' => 'nullable|string|max:10',
            'app_designation' => 'required|string|max:255',
            'app_posting_place' => 'required|string|max:255',
            'pay_band_type' => 'required|in:old,new',
            'pay_band' => 'required|integer',
            'pay_in' => 'required|numeric|min:0',
            'app_headquarter' => 'nullable|string|max:255',
            'doj' => 'nullable|date_format:d/m/Y',
            'dor' => 'required|date_format:d/m/Y',
            'office_name' => 'required|string|max:255',
            'office_street' => 'required|string|max:255',
            'office_city' => 'required|string|max:255',
            'office_post_office' => 'nullable|string|max:255',
            'office_district' => 'required|string',
            'office_pincode' => 'required|string|max:6',
            'office_phone_no' => 'nullable|string|max:15',
            'ddo_district' => 'nullable|string',
            'designation' => 'nullable|integer',
            'rhe_flat_type' => 'required|string',
            'reason' => 'required|string',
            'doa' => 'required|date_format:d/m/Y',
            'computer_serial_no' => 'required|string',
            'confirm_computer_serial_no' => 'required|string|same:computer_serial_no',
            'physical_application_no' => 'required|string|max:255',
            'remarks' => 'nullable|string',
            // Address fields
            'permanent_street' => 'nullable|string|max:255',
            'permanent_city_town_village' => 'nullable|string|max:255',
            'permanent_post_office' => 'nullable|string|max:255',
            'permanent_district' => 'nullable|string',
            'permanent_pincode' => 'nullable|string|max:6',
            'present_street' => 'nullable|string|max:255',
            'present_city_town_village' => 'nullable|string|max:255',
            'present_post_office' => 'nullable|string|max:255',
            'present_district' => 'nullable|string',
            'present_pincode' => 'nullable|string|max:6',
            'extra_doc' => 'nullable|file|mimes:pdf|max:1024', // 1 MB max, PDF only
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->all();
        $computerSerialNo = trim($data['computer_serial_no']);
        $confirmComputerSerialNo = trim($data['confirm_computer_serial_no']);
        $physicalApplicationNo = $data['physical_application_no'];

        // Validate computer serial no match
        if ($computerSerialNo !== $confirmComputerSerialNo) {
            return response()->json([
                'status' => 'error',
                'message' => 'The Computer Serial No. and Confirm Computer Serial No. do not match. Please enter same.',
            ], 422);
        }

        // Check if computer serial no already exists
        if (!ExistingApplicantService::isComputerSerialNoUnique($computerSerialNo)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This Computer Serial No. is already Exist.',
            ], 422);
        }

        // Check if physical application no already exists
        if (!ExistingApplicantService::isPhysicalApplicationNoUnique($physicalApplicationNo)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This Physical Application No. is already Exist.',
            ], 422);
        }

        // Check mobile uniqueness
        if (!empty($data['mobile'])) {
            $mobileExists = DB::table('housing_applicant')
                ->where('mobile_no', trim($data['mobile']))
                ->exists();
            
            if ($mobileExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This mobile no. already exist.',
                    'errors' => ['mobile' => ['This mobile no. already exist.']],
                ], 422);
            }
        }

        // Check email uniqueness
        if (!empty($data['email'])) {
            $emailExists = DB::table('users')
                ->where('mail', trim($data['email']))
                ->exists();
            
            if ($emailExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This email address already exist.',
                    'errors' => ['email' => ['This email address already exist.']],
                ], 422);
            }
        }

        // Check HRMS ID uniqueness
        if (!empty($data['hrms_id'])) {
            $hrmsExists = DB::table('housing_applicant_official_detail')
                ->where('hrms_id', trim($data['hrms_id']))
                ->exists();
            
            if ($hrmsExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This Employee HRMS ID already exist.',
                    'errors' => ['hrms_id' => ['This Employee HRMS ID already exist.']],
                ], 422);
            }
        }
\Log::info('beginTransaction');
        DB::beginTransaction();
        try {
            
            // Generate username
            $username = ExistingApplicantService::generateUsername(
                $data['applicant_name'],
                $physicalApplicationNo,
                $computerSerialNo
            );
\Log::info('User Data Output', ['userdata' => $username]);
            $loginName = !empty($data['hrms_id']) ? trim($data['hrms_id']) : $username;

            // Create user
            $userData = [
                'name' => $loginName,
                'password' => Hash::make($loginName),
                'password_old' => Hash::make($loginName),
                'mail' => !empty($data['email']) ? trim($data['email']) : null,
                'status' => 1,
                'new_pass_set' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            $uid = DB::table('users')->insertGetId($userData, 'uid');

            // Insert into housing_applicant
            $applicantData = [
                'uid' => $uid,
                'applicant_name' => trim($data['applicant_name']),
                'guardian_name' => trim($data['applicant_father_name']),
                'date_of_birth' => ExistingApplicantService::convertDate($data['dob']),
                'gender' => trim($data['gender']),
                'mobile_no' => !empty($data['mobile']) ? trim($data['mobile']) : null,
                'permanent_street' => !empty($data['permanent_street']) ? strtoupper(trim($data['permanent_street'])) : null,
                'permanent_city_town_village' => !empty($data['permanent_city_town_village']) ? strtoupper(trim($data['permanent_city_town_village'])) : null,
                'permanent_post_office' => !empty($data['permanent_post_office']) ? strtoupper(trim($data['permanent_post_office'])) : null,
                'permanent_district' => !empty($data['permanent_district']) ? trim($data['permanent_district']) : null,
                'permanent_pincode' => !empty($data['permanent_pincode']) ? trim($data['permanent_pincode']) : null,
                'present_street' => !empty($data['present_street']) ? strtoupper(trim($data['present_street'])) : null,
                'present_city_town_village' => !empty($data['present_city_town_village']) ? strtoupper(trim($data['present_city_town_village'])) : null,
                'present_post_office' => !empty($data['present_post_office']) ? strtoupper(trim($data['present_post_office'])) : null,
                'present_district' => !empty($data['present_district']) ? trim($data['present_district']) : null,
                'present_pincode' => !empty($data['present_pincode']) ? trim($data['present_pincode']) : null,
            ];

            // \Log::info('Applicant Data Output', ['applicantdata' => $applicantData]);

            $housingApplicantId = DB::table('housing_applicant')->insertGetId($applicantData,'housing_applicant_id');

            // Assign user role (role ID 4 based on Drupal code)
            DB::table('user_role')->insert([
                'uid' => $uid,
                'rid' => 4,
            ]);

            // Insert into housing_applicant_official_detail
            $officialDetailData = [
                'uid' => $uid,
                'housing_applicant_id' => $housingApplicantId,
                'hrms_id' => !empty($data['hrms_id']) ? trim($data['hrms_id']) : null,
                'applicant_designation' => strtoupper(trim($data['app_designation'])),
                'pay_band_id' => (int) $data['pay_band'],
                'pay_in_the_pay_band' => trim($data['pay_in']),
                'applicant_posting_place' => strtoupper(trim($data['app_posting_place'])),
                'applicant_headquarter' => !empty($data['app_headquarter']) ? strtoupper(trim($data['app_headquarter'])) : null,
                'date_of_joining' => !empty($data['doj']) ? ExistingApplicantService::convertDate($data['doj']) : null,
                'date_of_retirement' => ExistingApplicantService::convertDate($data['dor']),
                'office_name' => strtoupper(trim($data['office_name'])),
                'office_street' => strtoupper(trim($data['office_street'])),
                'office_city_town_village' => strtoupper(trim($data['office_city'])),
                'office_post_office' => !empty($data['office_post_office']) ? strtoupper(trim($data['office_post_office'])) : null,
                'office_district' => trim($data['office_district']),
                'office_pin_code' => trim($data['office_pincode']),
                'office_phone_no' => !empty($data['office_phone_no']) ? trim($data['office_phone_no']) : null,
                'ddo_id' => !empty($data['designation']) ? (int) $data['designation'] : null,
            ];

            $applicantOfficialDetailId = DB::table('housing_applicant_official_detail')->insertGetId($officialDetailData,'applicant_official_detail_id');

            // Insert into housing_online_application
            $onlineAppData = [
                'applicant_official_detail_id' => $applicantOfficialDetailId,
                'status' => 'housingapprover_approved_1',
                'date_of_verified' => date('Y-m-d'),
                'date_of_application' => ExistingApplicantService::convertDate($data['doa']),
                'is_backlog_applicant' => 1,
                'computer_serial_no' => $computerSerialNo,
                'physical_application_no' => $physicalApplicationNo,
                'remarks' => !empty($data['remarks']) ? $data['remarks'] : null,
            ];

            $onlineApplicationId = DB::table('housing_online_application')->insertGetId($onlineAppData,'online_application_id');

            // Update application number
            $doaParts = explode('/', $data['doa']);
            $applicationNo = 'PA-' . implode('', $doaParts) . '-' . $onlineApplicationId;
            DB::table('housing_online_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update(['application_no' => $applicationNo]);

            // Insert into housing_new_allotment_application
            $flatTypeId = ExistingApplicantService::getFlatTypeId(trim($data['rhe_flat_type']));
            DB::table('housing_new_allotment_application')->insert([
                'online_application_id' => $onlineApplicationId,
                'flat_type_id' => $flatTypeId,
                'allotment_category' => trim($data['reason']),
            ]);

            // Handle file upload if provided
            if ($request->hasFile('extra_doc')) {
                $file = $request->file('extra_doc');
                $directory = 'uploads/doc/extra_doc';
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->storeAs($directory, $filename, 'public');
                // File is stored but not linked to database table in Drupal either
                // Can be added to a file reference table if needed
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data inserted successfully.',
                'data' => [
                    'online_application_id' => $onlineApplicationId,
                    'application_no' => $applicationNo,
                ],
            ]);
        } catch (\Exception $e) {
            // \Log::info('test',[$e]);
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create applicant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update existing applicant
     * Based on Drupal existing_applicant_edit_form_submit function
     */
    public function update(Request $request, $id)
    {
        // Validation rules (similar to store but some fields may be readonly)
        $validator = Validator::make($request->all(), [
            'applicant_name' => 'required|string|max:255',
            'applicant_father_name' => 'required|string|max:255',
            'dob' => 'required|date_format:d/m/Y',
            'gender' => 'required|in:M,F',
            'mobile' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'hrms_id' => 'nullable|string|max:10',
            'app_designation' => 'required|string|max:255',
            'app_posting_place' => 'required|string|max:255',
            'pay_band_type' => 'required|in:old,new',
            'pay_band' => 'required|integer',
            'pay_in' => 'required|numeric|min:0',
            'app_headquarter' => 'nullable|string|max:255',
            'doj' => 'nullable|date_format:d/m/Y',
            'dor' => 'required|date_format:d/m/Y',
            'office_name' => 'required|string|max:255',
            'office_street' => 'required|string|max:255',
            'office_city' => 'required|string|max:255',
            'office_post_office' => 'nullable|string|max:255',
            'office_district' => 'required|string',
            'office_pincode' => 'required|string|max:6',
            'office_phone_no' => 'nullable|string|max:15',
            'district' => 'nullable|string',
            'designation' => 'nullable|integer',
            'rhe_flat_type' => 'required|string',
            'reason' => 'required|string',
            'doa' => 'required|date_format:d/m/Y',
            'computer_serial_no' => 'required|string',
            'confirm_computer_serial_no' => 'required|string|same:computer_serial_no',
            'physical_application_no' => 'required|string|max:255',
            'remarks' => 'nullable|string',
            // Address fields
            'permanent_street' => 'nullable|string|max:255',
            'permanent_city_town_village' => 'nullable|string|max:255',
            'permanent_post_office' => 'nullable|string|max:255',
            'permanent_district' => 'nullable|string',
            'permanent_pincode' => 'nullable|string|max:6',
            'present_street' => 'nullable|string|max:255',
            'present_city_town_village' => 'nullable|string|max:255',
            'present_post_office' => 'nullable|string|max:255',
            'present_district' => 'nullable|string',
            'present_pincode' => 'nullable|string|max:6',
            // Hidden fields for update
            'app_uid' => 'required|integer',
            'housing_applicant_id' => 'required|integer',
            'housing_official_detail_id' => 'required|integer',
            'housing_online_application_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->all();
        $computerSerialNo = trim($data['computer_serial_no']);
        $confirmComputerSerialNo = trim($data['confirm_computer_serial_no']);

        // Validate computer serial no match
        if ($computerSerialNo !== $confirmComputerSerialNo) {
            return response()->json([
                'status' => 'error',
                'message' => 'The Computer Serial No. and Confirm Computer Serial No. do not match. Please enter same.',
            ], 422);
        }

        // Check mobile uniqueness (excluding current applicant)
        if (!empty($data['mobile'])) {
            $mobileExists = DB::table('housing_applicant')
                ->where('mobile_no', trim($data['mobile']))
                ->where('housing_applicant_id', '!=', $data['housing_applicant_id'])
                ->exists();
            
            if ($mobileExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This mobile no. already exist.',
                    'errors' => ['mobile' => ['This mobile no. already exist.']],
                ], 422);
            }
        }

        // Check email uniqueness (excluding current user)
        if (!empty($data['email'])) {
            $emailExists = DB::table('users')
                ->where('mail', trim($data['email']))
                ->where('uid', '!=', $data['app_uid'])
                ->exists();
            
            if ($emailExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This email address already exist.',
                    'errors' => ['email' => ['This email address already exist.']],
                ], 422);
            }
        }

        // Check HRMS ID uniqueness (excluding current applicant)
        if (!empty($data['hrms_id'])) {
            $hrmsExists = DB::table('housing_applicant_official_detail')
                ->where('hrms_id', trim($data['hrms_id']))
                ->where('applicant_official_detail_id', '!=', $data['housing_official_detail_id'])
                ->exists();
            
            if ($hrmsExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This Employee HRMS ID already exist.',
                    'errors' => ['hrms_id' => ['This Employee HRMS ID already exist.']],
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $uid = $data['app_uid'];
            $housingApplicantId = $data['housing_applicant_id'];
            $officialDetailId = $data['housing_official_detail_id'];
            $onlineApplicationId = $data['housing_online_application_id'];

            // Generate username (for reference, not used if HRMS ID exists)
            $username = ExistingApplicantService::generateUsername(
                $data['applicant_name'],
                $data['physical_application_no'],
                $computerSerialNo
            );

            $loginName = !empty($data['hrms_id']) ? trim($data['hrms_id']) : $username;

            // Update user
            $userData = [
                'name' => $loginName,
                'password' => Hash::make($loginName),
                'password_old' => Hash::make($loginName),
                'mail' => !empty($data['email']) ? trim($data['email']) : null,
                'new_pass_set' => 1,
                'updated_at' => Carbon::now(),
            ];

            DB::table('users')
                ->where('uid', $uid)
                ->update($userData);

            // Update housing_applicant
            $applicantData = [
                'applicant_name' => trim($data['applicant_name']),
                'guardian_name' => trim($data['applicant_father_name']),
                'date_of_birth' => ExistingApplicantService::convertDate($data['dob']),
                'gender' => trim($data['gender']),
                'mobile_no' => !empty($data['mobile']) ? trim($data['mobile']) : null,
                'permanent_street' => !empty($data['permanent_street']) ? strtoupper(trim($data['permanent_street'])) : null,
                'permanent_city_town_village' => !empty($data['permanent_city_town_village']) ? strtoupper(trim($data['permanent_city_town_village'])) : null,
                'permanent_post_office' => !empty($data['permanent_post_office']) ? strtoupper(trim($data['permanent_post_office'])) : null,
                'permanent_district' => !empty($data['permanent_district']) ? trim($data['permanent_district']) : null,
                'permanent_pincode' => !empty($data['permanent_pincode']) ? trim($data['permanent_pincode']) : null,
                'present_street' => !empty($data['present_street']) ? strtoupper(trim($data['present_street'])) : null,
                'present_city_town_village' => !empty($data['present_city_town_village']) ? strtoupper(trim($data['present_city_town_village'])) : null,
                'present_post_office' => !empty($data['present_post_office']) ? strtoupper(trim($data['present_post_office'])) : null,
                'present_district' => !empty($data['present_district']) ? trim($data['present_district']) : null,
                'present_pincode' => !empty($data['present_pincode']) ? trim($data['present_pincode']) : null,
            ];

            DB::table('housing_applicant')
                ->where('housing_applicant_id', $housingApplicantId)
                ->update($applicantData);

            // Update housing_applicant_official_detail
            $officialDetailData = [
                'hrms_id' => !empty($data['hrms_id']) ? trim($data['hrms_id']) : null,
                'applicant_designation' => strtoupper(trim($data['app_designation'])),
                'pay_band_id' => (int) $data['pay_band'],
                'pay_in_the_pay_band' => trim($data['pay_in']),
                'applicant_posting_place' => strtoupper(trim($data['app_posting_place'])),
                'applicant_headquarter' => !empty($data['app_headquarter']) ? strtoupper(trim($data['app_headquarter'])) : null,
                'date_of_joining' => !empty($data['doj']) ? ExistingApplicantService::convertDate($data['doj']) : null,
                'date_of_retirement' => ExistingApplicantService::convertDate($data['dor']),
                'office_name' => strtoupper(trim($data['office_name'])),
                'office_street' => strtoupper(trim($data['office_street'])),
                'office_city_town_village' => strtoupper(trim($data['office_city'])),
                'office_post_office' => !empty($data['office_post_office']) ? strtoupper(trim($data['office_post_office'])) : null,
                'office_district' => trim($data['office_district']),
                'office_pin_code' => trim($data['office_pincode']),
                'office_phone_no' => !empty($data['office_phone_no']) ? trim($data['office_phone_no']) : null,
                'ddo_id' => !empty($data['designation']) ? (int) $data['designation'] : null,
            ];

            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $officialDetailId)
                ->update($officialDetailData);

            // Update housing_online_application
            $onlineAppData = [
                'status' => 'housingapprover_approved_1',
                'date_of_verified' => date('Y-m-d'),
                'date_of_application' => ExistingApplicantService::convertDate($data['doa']),
                'is_backlog_applicant' => 1,
                'computer_serial_no' => $computerSerialNo,
                'physical_application_no' => $data['physical_application_no'],
                'remarks' => !empty($data['remarks']) ? $data['remarks'] : null,
            ];

            DB::table('housing_online_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update($onlineAppData);

            // Update housing_new_allotment_application
            $flatTypeId = ExistingApplicantService::getFlatTypeId(trim($data['rhe_flat_type']));
            DB::table('housing_new_allotment_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update([
                    'flat_type_id' => $flatTypeId,
                    'allotment_category' => trim($data['reason']),
                ]);

            // Handle file upload if provided
            if ($request->hasFile('extra_doc')) {
                $file = $request->file('extra_doc');
                $directory = 'doc/extra_doc';
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->storeAs($directory, $filename, 'public');
                // File is stored but not linked to database table in Drupal either
                // Can be added to a file reference table if needed
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data Updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update applicant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Accept declaration for HRMS ID update
     */
    public function acceptDeclaration(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'hrms_id' => 'required|string',
            'uid' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid parameters.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update HRMS ID and UID in housing_applicant_official_detail
        // Update UID in housing_applicant
        // Deactivate old user account
        
        DB::beginTransaction();
        try {
            $officialDetail = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->where('hoa.online_application_id', $id)
                ->select('haod.applicant_official_detail_id', 'haod.housing_applicant_id', 'haod.uid as old_uid')
                ->first();

            if (!$officialDetail) {
                throw new \Exception('Applicant not found.');
            }

            // Update official detail
            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $officialDetail->applicant_official_detail_id)
                ->update([
                    'hrms_id' => $request->input('hrms_id'),
                    'uid' => $request->input('uid'),
                ]);

            // Update applicant
            DB::table('housing_applicant')
                ->where('housing_applicant_id', $officialDetail->housing_applicant_id)
                ->update(['uid' => $request->input('uid')]);

            // Deactivate old user
            DB::table('users')
                ->where('uid', $officialDetail->old_uid)
                ->update(['status' => 0]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'HRMS ID updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

