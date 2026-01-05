<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CommonApplicationController extends Controller
{
    /**
     * Store common application data
     * This handles the submission of the common application form
     */
    public function store(Request $request)
    {
        $validator = $this->validateForm($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $uid = $request->input('uid');
            $appType = $request->input('app_type', 'NA'); // NA, VS, CS, etc.

            // Step 1: Update/Insert user email
            $this->updateUserEmail($uid, $request->input('email'));

            // Step 2: Insert/Update applicant personal details
            $housingApplicantId = $this->saveApplicantPersonalDetails($uid, $request->all());

            // Step 3: Insert/Update applicant official details
            $applicantOfficialDetailId = $this->saveApplicantOfficialDetails($uid, $request->all(), $housingApplicantId, $appType);

            // Step 4: Insert online application
            $onlineApplicationId = $this->saveOnlineApplication($applicantOfficialDetailId, $request->all(), $appType);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Application submitted successfully',
                'data' => [
                    'online_application_id' => $onlineApplicationId,
                    'housing_applicant_id' => $housingApplicantId,
                    'applicant_official_detail_id' => $applicantOfficialDetailId,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Common Application Store Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit application: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate form data
     */
    private function validateForm(Request $request)
    {
        return Validator::make($request->all(), [
            'uid' => 'required|integer',
            'applicant_name' => 'required|string|max:255|regex:/^[A-Z]+[a-zA-Z\s\.\(\)\&_]*$/',
            'applicant_father_name' => 'required|string|max:255|regex:/^[A-Z]+[a-zA-Z\s\.\(\)\&_]*$/',
            'mobile' => 'required|string|size:10|regex:/[6789][0-9]{9}/',
            'email' => 'required|email|max:255',
            'dob' => 'required|date_format:d/m/Y',
            'gender' => 'required|in:M,F',
            
            // Permanent Address
            'permanent_street' => 'required|string|max:500|regex:/^[a-zA-Z0-9][\.\-\/,()&:\w\s]+[a-zA-Z0-9]*$/',
            'permanent_city_town_village' => 'required|string|max:255|regex:/^[a-zA-Z0-9][\.\-\/,()&:\w\s]+[a-zA-Z0-9]*$/',
            'permanent_post_office' => 'required|string|max:255|regex:/^[a-zA-Z0-9][\.\-\/,()&:\w\s]+[a-zA-Z0-9]*$/',
            'permanent_district' => 'required|integer',
            'permanent_pincode' => 'required|string|size:6|regex:/^[0-9]{6}$/',
            
            // Present Address
            'present_street' => 'required|string|max:500|regex:/^[a-zA-Z0-9][\.\-\/,()&:\w\s]+[a-zA-Z0-9]*$/',
            'present_city_town_village' => 'required|string|max:255|regex:/^[a-zA-Z0-9][\.\-\/,()&:\w\s]+[a-zA-Z0-9]*$/',
            'present_post_office' => 'required|string|max:255|regex:/^[a-zA-Z0-9][\.\-\/,()&:\w\s]+[a-zA-Z0-9]*$/',
            'present_district' => 'required|integer',
            'present_pincode' => 'required|string|size:6|regex:/^[0-9]{6}$/',
            
            // Official Information
            'hrms_id' => 'required|string|size:10|regex:/[1-9][0-9]{9}/',
            'app_designation' => 'required|string|max:500|regex:/^[\w][-\/()\[\]\{\}\.\,\&\'\'\"\w\s]+$/',
            'pay_band' => 'required|integer',
            'pay_in' => 'required|numeric|min:1',
            'app_posting_place' => 'required|string|max:500',
            'app_headquarter' => 'required|string|max:255|regex:/^[a-zA-Z0-9][\.\-\/,()&:\w\s]+[a-zA-Z0-9]*$/',
            'doj' => 'required|date_format:d/m/Y',
            'dor' => 'required|date_format:d/m/Y',
            
            // Office Address
            'office_name' => 'required|string|max:500|regex:/^[\w][-\/()\[\]\{\}\.\,\&\'\'\"\w\s]+$/',
            'office_street' => 'required|string|max:500',
            'office_city' => 'required|string|max:255|regex:/^[a-zA-Z0-9][\.\-\/,()&:\w\s]+[a-zA-Z0-9]*$/',
            'office_post_office' => 'required|string|max:255|regex:/^[a-zA-Z0-9][\.\-\/,()&:\w\s]+[a-zA-Z0-9]*$/',
            'office_district' => 'required|integer',
            'office_pincode' => 'required|string|size:6|regex:/^[0-9]{6}$/',
            'office_phone_no' => 'required|string|max:15|regex:/[0-9]+$/',
            
            // DDO Details
            'district' => 'required|integer', // DDO District
            'designation' => 'required|integer', // DDO Designation ID
        ]);
    }

    /**
     * Update user email
     */
    private function updateUserEmail($uid, $email)
    {
        DB::table('users')
            ->where('uid', $uid)
            ->update(['mail' => strtolower(trim($email))]);
    }

    /**
     * Save applicant personal details
     */
    private function saveApplicantPersonalDetails($uid, $data)
    {
        $applicantData = [
            'uid' => $uid,
            'applicant_name' => strtoupper(trim($data['applicant_name'])),
            'guardian_name' => strtoupper(trim($data['applicant_father_name'])),
            'gender' => trim($data['gender']),
            'date_of_birth' => $this->convertDateFormat($data['dob']),
            'mobile_no' => trim($data['mobile']),
            'permanent_street' => strtoupper(trim($data['permanent_street'])),
            'permanent_city_town_village' => strtoupper(trim($data['permanent_city_town_village'])),
            'permanent_post_office' => strtoupper(trim($data['permanent_post_office'])),
            'permanent_district' => trim($data['permanent_district']),
            'permanent_pincode' => trim($data['permanent_pincode']),
            'permanent_present_same' => 0,
            'present_street' => strtoupper(trim($data['present_street'])),
            'present_city_town_village' => strtoupper(trim($data['present_city_town_village'])),
            'present_post_office' => strtoupper(trim($data['present_post_office'])),
            'present_district' => trim($data['present_district']),
            'present_pincode' => trim($data['present_pincode']),
        ];

        // Insert new record
        DB::table('housing_applicant')->insert($applicantData);

        // Get the max ID
        return DB::table('housing_applicant')
            ->where('uid', $uid)
            ->max('housing_applicant_id');
    }

    /**
     * Save applicant official details
     */
    private function saveApplicantOfficialDetails($uid, $data, $housingApplicantId, $appType)
    {
        // Check if user has existing official detail with online application
        $existingOfficialDetail = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('haod.uid', $uid)
            ->first();

        $officialData = [
            'uid' => $uid,
            'housing_applicant_id' => $housingApplicantId,
            'ddo_id' => trim($data['designation']),
            'hrms_id' => trim($data['hrms_id']),
            'applicant_designation' => strtoupper(trim($data['app_designation'])),
            'applicant_headquarter' => strtoupper(trim($data['app_headquarter'])),
            'applicant_posting_place' => strtoupper(trim($data['app_posting_place'])),
            'pay_band_id' => trim($data['pay_band']),
            'pay_in_the_pay_band' => trim($data['pay_in']),
            'date_of_joining' => $this->convertDateFormat($data['doj']),
            'date_of_retirement' => $this->convertDateFormat($data['dor']),
            'office_name' => strtoupper(trim($data['office_name'])),
            'office_street' => strtoupper(trim($data['office_street'])),
            'office_city_town_village' => strtoupper(trim($data['office_city'])),
            'office_post_office' => strtoupper(trim($data['office_post_office'])),
            'office_pin_code' => trim($data['office_pincode']),
            'office_district' => trim($data['office_district']),
            'office_phone_no' => trim($data['office_phone_no']),
            'is_active' => 1,
        ];

        if ($existingOfficialDetail) {
            // Deactivate previous record
            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $existingOfficialDetail->applicant_official_detail_id)
                ->update(['is_active' => 0]);

            // Insert new record
            DB::table('housing_applicant_official_detail')->insert($officialData);
        } else {
            // Insert new record
            DB::table('housing_applicant_official_detail')->insert($officialData);
        }

        // Get the max ID
        return DB::table('housing_applicant_official_detail')
            ->where('uid', $uid)
            ->max('applicant_official_detail_id');
    }

    /**
     * Save online application
     */
    private function saveOnlineApplication($applicantOfficialDetailId, $data, $appType)
    {
        $status = 'draft'; // Initial status
        $applicationNo = $appType;

        // For NA type, add reason prefix
        if ($appType == 'NA' && isset($data['reason'])) {
            $reasonMap = [
                'General' => 'GEN',
                'Single Earning Lady' => 'SEL',
                'Transfer' => 'TRN',
                'Recommended' => 'RCM',
                'Legal Heir' => 'LGH',
                'Physically Handicaped or Serious Illness' => 'PHI',
                'Judicial Officer On Transfer' => 'JOT',
            ];
            $reason = $reasonMap[$data['reason']] ?? 'GEN';
            $applicationNo = $appType . '-' . $reason;
        }

        // Calculate computer serial number for NA type
        $computerSerialNo = null;
        if ($appType == 'NA' && date('Y-m-d') >= '2025-08-28') {
            $checkNa = DB::table('housing_online_application')
                ->whereRaw("substring(application_no, 1, 2) = 'NA'")
                ->whereNotNull('computer_serial_no')
                ->exists();

            if (!$checkNa) {
                $computerSerialNo = '200001';
            } else {
                // Get max computer_serial_no (alphanumeric) - sort by numeric part, then alphabetic
                $maxSerial = DB::table('housing_online_application')
                    ->whereRaw("(substring(application_no, 1, 2) = 'NA' OR substring(application_no, 1, 2) = 'PA')")
                    ->whereNotNull('computer_serial_no')
                    ->orderByRaw("
                        LPAD(regexp_replace(computer_serial_no, '[^0-9]', '', 'g'), 10, '0') DESC,
                        regexp_replace(computer_serial_no, '[0-9]', '', 'g') DESC
                    ")
                    ->value('computer_serial_no');
                
                if ($maxSerial) {
                    // Extract numeric part and increment
                    $numPart = preg_replace('/[^0-9]/', '', $maxSerial);
                    $alphaPart = preg_replace('/[0-9]/', '', $maxSerial);
                    $nextNum = (int)$numPart + 1;
                    $computerSerialNo = $nextNum . $alphaPart;
                } else {
                    $computerSerialNo = '200001';
                }
            }
        }

        $onlineAppData = [
            'applicant_official_detail_id' => $applicantOfficialDetailId,
            'status' => $status,
            'application_no' => $applicationNo,
        ];

        if ($computerSerialNo) {
            $onlineAppData['computer_serial_no'] = $computerSerialNo;
        }

        DB::table('housing_online_application')->insert($onlineAppData);

        // Get the max online application ID
        return DB::table('housing_online_application')
            ->where('applicant_official_detail_id', $applicantOfficialDetailId)
            ->max('online_application_id');
    }

    /**
     * Convert date from d/m/Y to Y-m-d
     */
    private function convertDateFormat($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            $dateObj = Carbon::createFromFormat('d/m/Y', $date);
            return $dateObj->format('Y-m-d');
        } catch (\Exception $e) {
            Log::error('Date conversion error', ['date' => $date, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get applicant personal info for form pre-fill
     */
    public function getApplicantPersonalInfo(Request $request)
    {
        $uid = $request->input('uid');

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'UID is required',
            ], 422);
        }

        $applicant = DB::table('users as u')
            ->join('housing_applicant as ha', 'u.uid', '=', 'ha.uid')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->where('u.uid', $uid)
            ->where('haod.is_active', 1)
            ->select(
                'ha.*',
                'u.mail as email'
            )
            ->first();

        if (!$applicant) {
            return response()->json([
                'status' => 'success',
                'data' => null,
            ]);
        }

        // Convert date format from Y-m-d to d/m/Y
        if ($applicant->date_of_birth) {
            $applicant->dob = Carbon::createFromFormat('Y-m-d', $applicant->date_of_birth)->format('d/m/Y');
        }

        return response()->json([
            'status' => 'success',
            'data' => $applicant,
        ]);
    }

    /**
     * Get applicant official info for form pre-fill
     */
    public function getApplicantOfficialInfo(Request $request)
    {
        $uid = $request->input('uid');
        $onlineApplicationId = $request->input('online_application_id');

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'UID is required',
            ], 422);
        }

        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->where('haod.uid', $uid);

        if ($onlineApplicationId) {
            $query->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->where('hoa.online_application_id', $onlineApplicationId);
        } else {
            $query->where('haod.is_active', 1);
        }

        $official = $query->select(
            'haod.*',
            'hd.district_code',
            'hd.ddo_id',
            'hd.ddo_address'
        )->first();

        if (!$official) {
            return response()->json([
                'status' => 'success',
                'data' => null,
            ]);
        }

        // Convert date formats
        if ($official->date_of_joining) {
            $official->doj = Carbon::createFromFormat('Y-m-d', $official->date_of_joining)->format('d/m/Y');
        }
        if ($official->date_of_retirement) {
            $official->dor = Carbon::createFromFormat('Y-m-d', $official->date_of_retirement)->format('d/m/Y');
        }

        return response()->json([
            'status' => 'success',
            'data' => $official,
        ]);
    }
}

