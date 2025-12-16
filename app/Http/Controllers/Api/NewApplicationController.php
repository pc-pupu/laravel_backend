<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class NewApplicationController extends Controller
{
    /**
     * Check if user has a draft new application
     */
    public function checkDraftStatus(Request $request)
    {
        $uid = $request->input('uid');

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'UID is required',
            ], 422);
        }

        try {
            $result = DB::table('housing_new_allotment_application as hna')
                ->join('housing_online_application as hoa', 'hna.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->where('haod.uid', $uid)
                ->where('hoa.status', 'draft')
                ->select('hoa.status', 'hoa.online_application_id')
                ->orderBy('hoa.online_application_id', 'DESC')
                ->first();

            if ($result) {
                // Check if status is in rejected/cancelled list
                $rejectedStatuses = [
                    'offer_letter_cancel',
                    'license_cancel',
                    'ddo_rejected_1',
                    'housing_sup_reject_1',
                    'housing_approver_reject_1',
                    'housing_official_reject',
                    'applicant_reject',
                    'ddo_rejected_2',
                    'housing_sup_reject_2',
                    'housing_approver_reject_2',
                ];

                if (in_array($result->status, $rejectedStatuses)) {
                    return response()->json([
                        'status' => 'success',
                        'has_draft' => false,
                        'data' => null,
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'has_draft' => true,
                    'data' => [
                        'online_application_id' => $result->online_application_id,
                        'status' => $result->status,
                    ],
                ]);
            }

            return response()->json([
                'status' => 'success',
                'has_draft' => false,
                'data' => null,
            ]);

        } catch (\Exception $e) {
            Log::error('Check Draft Status Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check draft status',
            ], 500);
        }
    }

    /**
     * Get housing estate preferences based on pay band and treasury
     */
    public function getHousingEstatePreferences(Request $request)
    {
        $payBandId = $request->input('pay_band_id');
        $treasuryId = $request->input('treasury_id');
        $districtCode = $request->input('district_code', 17); // Default to 17

        if (!$payBandId || !$treasuryId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pay band ID and Treasury ID are required',
            ], 422);
        }

        try {
            $estates = DB::table('housing_estate as t1')
                ->join('housing_flat as t2', 't1.estate_id', '=', 't2.estate_id')
                ->join('housing_pay_band_categories as t3', 't2.flat_type_id', '=', 't3.flat_type_id')
                ->join('housing_treasury_estate_mapping as t4', 't1.estate_id', '=', 't4.estate_id')
                ->where('t1.district_code', $districtCode)
                ->where('t3.pay_band_id', $payBandId)
                ->where('t4.treasury_id', $treasuryId)
                ->where('t4.is_active', 1)
                ->select('t1.estate_id', 't1.estate_name')
                ->groupBy('t1.estate_id', 't1.estate_name')
                ->orderBy('t1.estate_name', 'ASC')
                ->get();

            $options = ['' => '- Select -'];
            foreach ($estates as $estate) {
                $options[$estate->estate_id] = $estate->estate_name;
            }

            return response()->json([
                'status' => 'success',
                'data' => $options,
            ]);

        } catch (\Exception $e) {
            Log::error('Get Housing Estate Preferences Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch housing estates',
            ], 500);
        }
    }

    /**
     * Store new application (extends common application)
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
            $action = $request->input('action', 'draft'); // 'draft' or 'applied'
            $onlineApplicationId = $request->input('online_application_id', 0);

            // Step 1: Save common application data
            if ($onlineApplicationId == 0) {
                // Create new application using common application logic
                $this->updateUserEmail($uid, $request->input('email'));
                $housingApplicantId = $this->saveApplicantPersonalDetails($uid, $request->all());
                $applicantOfficialDetailId = $this->saveApplicantOfficialDetails($uid, $request->all(), $housingApplicantId, 'NA');
                $onlineApplicationId = $this->saveOnlineApplication($applicantOfficialDetailId, $request->all(), 'NA', $action);
            } else {
                // Update existing application
                $this->updateUserEmail($uid, $request->input('email'));
                $this->updateApplicantPersonalDetails($uid, $request->all());
                $this->updateApplicantOfficialDetails($uid, $request->all(), $onlineApplicationId);
                $this->updateOnlineApplication($onlineApplicationId, $request->all(), $action);
            }

            // Step 2: Save new allotment application data
            $this->saveNewAllotmentApplication($onlineApplicationId, $request->all());

            // Step 3: Save estate preferences
            $this->saveEstatePreferences($onlineApplicationId, $request->all());

            // Step 4: Handle document upload
            $documentPath = null;
            if ($request->hasFile('extra_doc')) {
                $documentPath = $this->handleDocumentUpload($request->file('extra_doc'), $uid);
                // Update new allotment application with document path
                if ($documentPath) {
                    DB::table('housing_new_allotment_application')
                        ->where('online_application_id', $onlineApplicationId)
                        ->update(['extra_doc' => $documentPath]);
                }
            }

            // Step 5: Update application status
            if ($action == 'applied') {
                DB::table('housing_online_application')
                    ->where('online_application_id', $onlineApplicationId)
                    ->update(['status' => 'applied']);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $action == 'applied' ? 'Application submitted successfully' : 'Application saved as draft',
                'data' => [
                    'online_application_id' => $onlineApplicationId,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('New Application Store Error', [
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
        $rules = [
            'uid' => 'required|integer',
            'reason' => 'required|string',
            'rhe_flat_type' => 'required|string',
            'preference_selector' => 'nullable|in:0,1',
        ];

        // If preference selector is 1, first preference is required
        if ($request->input('preference_selector') == '1') {
            $rules['first_preference'] = 'required|integer';
            $rules['second_preference'] = 'nullable|integer';
            $rules['third_preference'] = 'nullable|integer';
            $rules['fourth_preference'] = 'nullable|integer';
            $rules['fifth_preference'] = 'nullable|integer';
        }

        // Document upload required for certain reasons
        $reason = $request->input('reason');
        $documentRequiredReasons = [
            'Transfer',
            'Legal Heir',
            'Physically Handicaped or Serious Illness',
            'Recommended',
            'Single Earning Lady',
        ];

        if (in_array($reason, $documentRequiredReasons)) {
            $rules['extra_doc'] = 'required|file|mimes:pdf|max:1024'; // 1MB max
        }

        return Validator::make($request->all(), $rules);
    }

    /**
     * Save new allotment application
     */
    private function saveNewAllotmentApplication($onlineApplicationId, $data)
    {
        // Get flat type ID
        $flatTypeId = DB::table('housing_flat_type')
            ->where('flat_type', trim($data['rhe_flat_type']))
            ->value('flat_type_id');

        if (!$flatTypeId) {
            throw new \Exception('Invalid flat type');
        }

        $newAllotmentData = [
            'online_application_id' => $onlineApplicationId,
            'allotment_category' => trim($data['reason']),
            'flat_type_id' => $flatTypeId,
            'extra_doc' => null, // Will be handled separately if file uploaded
        ];

        // Check if record exists
        $exists = DB::table('housing_new_allotment_application')
            ->where('online_application_id', $onlineApplicationId)
            ->exists();

        if ($exists) {
            DB::table('housing_new_allotment_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update($newAllotmentData);
        } else {
            DB::table('housing_new_allotment_application')
                ->insert($newAllotmentData);
        }
    }

    /**
     * Save estate preferences
     */
    private function saveEstatePreferences($onlineApplicationId, $data)
    {
        // Delete existing preferences
        DB::table('housing_new_application_estate_preferences')
            ->where('online_application_id', $onlineApplicationId)
            ->delete();

        $preferenceSelector = $data['preference_selector'] ?? '0';

        if ($preferenceSelector == '1') {
            // Save preferences
            $preferences = [
                1 => $data['first_preference'] ?? null,
                2 => $data['second_preference'] ?? null,
                3 => $data['third_preference'] ?? null,
                4 => $data['fourth_preference'] ?? null,
                5 => $data['fifth_preference'] ?? null,
            ];

            $insertData = [];
            foreach ($preferences as $order => $estateId) {
                $insertData[] = [
                    'estate_id' => $estateId,
                    'preference_order' => $order,
                    'online_application_id' => $onlineApplicationId,
                    'created' => Carbon::now(),
                ];
            }

            if (!empty($insertData)) {
                DB::table('housing_new_application_estate_preferences')
                    ->insert($insertData);
            }
        } else {
            // Insert NULL preferences
            $insertData = [];
            for ($i = 1; $i <= 5; $i++) {
                $insertData[] = [
                    'estate_id' => null,
                    'preference_order' => $i,
                    'online_application_id' => $onlineApplicationId,
                    'created' => Carbon::now(),
                ];
            }
            DB::table('housing_new_application_estate_preferences')
                ->insert($insertData);
        }
    }

    /**
     * Handle document upload
     */
    private function handleDocumentUpload($file, $uid)
    {
        // Validate file
        if (!$file->isValid()) {
            throw new \Exception('Invalid file uploaded');
        }

        // Validate file type (PDF only)
        if ($file->getClientOriginalExtension() !== 'pdf' && 
            $file->getMimeType() !== 'application/pdf') {
            throw new \Exception('Only PDF files are allowed');
        }

        // Validate file size (1MB max)
        if ($file->getSize() > 1048576) {
            throw new \Exception('File size exceeds 1MB limit');
        }

        $filename = 'extra_doc_' . $uid . '_' . time() . '.pdf';
        $path = $file->storeAs('documents/extra_doc', $filename, 'public');
        
        return $path;
    }

    /**
     * Get new application data for editing
     */
    public function getApplicationData(Request $request)
    {
        $uid = $request->input('uid');
        $onlineApplicationId = $request->input('online_application_id');

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'UID is required',
            ], 422);
        }

        try {
            // Get new allotment application data
            $newAppData = DB::table('housing_new_allotment_application as hnaa')
                ->where('hnaa.online_application_id', $onlineApplicationId)
                ->select(
                    'hnaa.allotment_category',
                    'hnaa.flat_type_id',
                    'hnaa.extra_doc'
                )
                ->first();

            if (!$newAppData) {
                return response()->json([
                    'status' => 'success',
                    'data' => null,
                ]);
            }

            // Get flat type name
            $flatType = DB::table('housing_flat_type')
                ->where('flat_type_id', $newAppData->flat_type_id)
                ->value('flat_type');

            // Get estate preferences
            $preferences = DB::table('housing_new_application_estate_preferences')
                ->where('online_application_id', $onlineApplicationId)
                ->orderBy('preference_order', 'ASC')
                ->get();

            $preferenceData = [];
            foreach ($preferences as $pref) {
                if ($pref->estate_id) {
                    $preferenceData['preference_' . $pref->preference_order] = $pref->estate_id;
                }
            }

            // Determine if preferences were selected
            $hasPreferences = !empty(array_filter(array_column($preferences->toArray(), 'estate_id')));
            $preferenceSelector = $hasPreferences ? '1' : '0';

            return response()->json([
                'status' => 'success',
                'data' => [
                    'allotment_category' => $newAppData->allotment_category,
                    'rhe_flat_type' => $flatType,
                    'preference_selector' => $preferenceSelector,
                    'preferences' => $preferenceData,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get Application Data Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch application data',
            ], 500);
        }
    }

    /**
     * Get flat type based on pay band and basic pay
     */
    public function getFlatTypeByPayBand(Request $request)
    {
        $payBandId = $request->input('pay_band_id');
        $basicPay = $request->input('basic_pay');

        if (!$payBandId || !$basicPay) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pay band ID and basic pay are required',
            ], 422);
        }

        try {
            $query = DB::table('housing_pay_band_categories as t1')
                ->join('housing_flat_type as t2', 't1.flat_type_id', '=', 't2.flat_type_id')
                ->where('t1.pay_band_id', $payBandId)
                ->where('t1.flag', 'new');

            if ($basicPay > 95099) {
                $query->where('t1.scale_from', '>=', 95100)
                    ->whereNull('t1.scale_to');
            } else {
                $query->where('t1.scale_from', '<=', $basicPay)
                    ->where('t1.scale_to', '>', $basicPay);
            }

            $result = $query->select('t1.pay_band_id', 't2.flat_type')
                ->first();

            if ($result) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'pay_band_id' => $result->pay_band_id,
                        'flat_type' => $result->flat_type,
                    ],
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'No flat type found for the given pay band and basic pay',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Get Flat Type Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch flat type',
            ], 500);
        }
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

        DB::table('housing_applicant')->insert($applicantData);

        return DB::table('housing_applicant')
            ->where('uid', $uid)
            ->max('housing_applicant_id');
    }

    /**
     * Update applicant personal details
     */
    private function updateApplicantPersonalDetails($uid, $data)
    {
        $applicantData = [
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
            'present_street' => strtoupper(trim($data['present_street'])),
            'present_city_town_village' => strtoupper(trim($data['present_city_town_village'])),
            'present_post_office' => strtoupper(trim($data['present_post_office'])),
            'present_district' => trim($data['present_district']),
            'present_pincode' => trim($data['present_pincode']),
        ];

        // Get the latest housing_applicant_id for this user
        $housingApplicantId = DB::table('housing_applicant')
            ->where('uid', $uid)
            ->max('housing_applicant_id');

        if ($housingApplicantId) {
            DB::table('housing_applicant')
                ->where('housing_applicant_id', $housingApplicantId)
                ->update($applicantData);
        }
    }

    /**
     * Save applicant official details
     */
    private function saveApplicantOfficialDetails($uid, $data, $housingApplicantId, $appType)
    {
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
            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $existingOfficialDetail->applicant_official_detail_id)
                ->update(['is_active' => 0]);
            DB::table('housing_applicant_official_detail')->insert($officialData);
        } else {
            DB::table('housing_applicant_official_detail')->insert($officialData);
        }

        return DB::table('housing_applicant_official_detail')
            ->where('uid', $uid)
            ->max('applicant_official_detail_id');
    }

    /**
     * Update applicant official details
     */
    private function updateApplicantOfficialDetails($uid, $data, $onlineApplicationId)
    {
        $officialDetailId = DB::table('housing_online_application')
            ->where('online_application_id', $onlineApplicationId)
            ->value('applicant_official_detail_id');

        if ($officialDetailId) {
            $officialData = [
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
            ];

            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $officialDetailId)
                ->update($officialData);
        }
    }

    /**
     * Save online application
     */
    private function saveOnlineApplication($applicantOfficialDetailId, $data, $appType, $status = 'draft')
    {
        $applicationNo = $appType;

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

        $computerSerialNo = null;
        if ($appType == 'NA' && date('Y-m-d') >= '2025-08-28') {
            $checkNa = DB::table('housing_online_application')
                ->whereRaw("substring(application_no, 1, 2) = 'NA'")
                ->whereNotNull('computer_serial_no')
                ->exists();

            if (!$checkNa) {
                $computerSerialNo = '200001';
            } else {
                $maxSerial = DB::table('housing_online_application')
                    ->whereRaw("(substring(application_no, 1, 2) = 'NA' OR substring(application_no, 1, 2) = 'PA')")
                    ->whereNotNull('computer_serial_no')
                    ->selectRaw("max(to_number(computer_serial_no, '9999999999')) as no")
                    ->value('no');
                $computerSerialNo = ($maxSerial ?? 200000) + 1;
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

        return DB::table('housing_online_application')
            ->where('applicant_official_detail_id', $applicantOfficialDetailId)
            ->max('online_application_id');
    }

    /**
     * Update online application
     */
    private function updateOnlineApplication($onlineApplicationId, $data, $status)
    {
        $applicationNo = 'NA';

        if (isset($data['reason'])) {
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
            $applicationNo = 'NA-' . $reason;
        }

        DB::table('housing_online_application')
            ->where('online_application_id', $onlineApplicationId)
            ->update([
                'status' => $status,
                'application_no' => $applicationNo,
            ]);
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
}

