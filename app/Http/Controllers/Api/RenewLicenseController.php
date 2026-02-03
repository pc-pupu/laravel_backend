<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RenewLicenseController extends Controller
{
    /**
     * Check for draft application status
     * GET /api/renew-license/check-draft
     */
    public function checkDraft(Request $request)
    {
        $uid = $request->input('uid');

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'User ID is required',
                'status_code' => 422
            ], 422);
        }

        try {
            $onlineApplicationId = $this->getMaxLicenseApplicationId($uid, ['draft']);

            if ($onlineApplicationId > 0) {
                return response()->json([
                    'status' => 'success',
                    'has_draft' => true,
                    'online_application_id' => $onlineApplicationId,
                    'status_code' => 200
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'has_draft' => false,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Renew License Check Draft Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check draft status',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get license and allotment details for renewal
     * GET /api/renew-license/license-details
     */
    public function getLicenseDetails(Request $request)
    {
        $uid = $request->input('uid');
        $onlineApplicationId = $request->input('online_application_id', 0);

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'User ID is required',
                'status_code' => 422
            ], 422);
        }

        try {
            $licenseDetails = null;
            $allotmentDetails = null;

            // Check if there's an existing renewal application
            $existingAppId = $this->getMaxLicenseApplicationId($uid, ['reject', 'cancel']);
            
            if ($existingAppId > 0) {
                // Fetch from license application
                $licenseDetails = $this->fetchLicenseFromApplication($uid, $existingAppId);
            } else {
                // Fetch from existing issued license
                $licenseDetails = $this->fetchExistingLicense($uid);
            }

            // Get RHE and allotment details from existing license
            if ($licenseDetails) {
                $allotmentDetails = $this->fetchAllotmentFromLicense($uid, $licenseDetails);
            }

            return response()->json([
                'status' => 'success',
                'license_data' => $licenseDetails,
                'allotment_data' => $allotmentDetails,
                'online_application_id' => $existingAppId,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Renew License Get Details Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch license details',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Store or update renew license application
     * POST /api/renew-license/store
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uid' => 'required|integer',
            'action' => 'required|in:draft,applied',
            'allotment_no' => 'required|string',
            'allotment_date' => 'required|date',
            'license_no' => 'required|string',
            'license_date' => 'required|date',
            'rhe_name' => 'required|string',
            'block_no' => 'required|string',
            'flat_no' => 'required|string',
            'online_application_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'status_code' => 422
            ], 422);
        }

        try {
            DB::beginTransaction();

            $uid = $request->input('uid');
            $action = $request->input('action');
            $onlineApplicationId = $request->input('online_application_id', 0);

            // Get common application data
            $commonAppData = $this->extractCommonApplicationData($request);

            if ($onlineApplicationId == 0) {
                // Create new application
                $onlineApplicationId = $this->createOnlineApplication($uid, $action, 'NL', $commonAppData);
                $this->createLicenseApplication($request, $onlineApplicationId, $uid);
            } else {
                // Update existing application
                $this->updateOnlineApplication($onlineApplicationId, $action, $commonAppData);
                $this->updateLicenseApplication($request, $onlineApplicationId, $uid);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $action == 'draft' ? 'Application saved as draft.' : 'You have successfully applied.',
                'online_application_id' => $onlineApplicationId,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Renew License Store Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save application',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get max license application ID
     */
    private function getMaxLicenseApplicationId($uid, $excludeStatuses)
    {
        $result = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
            ->where('haod.uid', $uid)
            ->where('hla.type_of_application', 'renew')
            ->whereNotIn('hoa.status', $excludeStatuses)
            ->max('hoa.online_application_id');

        return $result ?? 0;
    }

    /**
     * Fetch license from application
     */
    private function fetchLicenseFromApplication($uid, $onlineApplicationId)
    {
        return DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
            ->where('haod.uid', $uid)
            ->where('hoa.online_application_id', $onlineApplicationId)
            ->select(
                'hla.allotment_no',
                'hla.allotment_date',
                'hla.license_no',
                'hla.license_date',
                'hla.rhe_name',
                'hla.block_no',
                'hla.flat_no'
            )
            ->first();
    }

    /**
     * Fetch existing issued license
     */
    private function fetchExistingLicense($uid)
    {
        return DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_occupant_license as hol', 'hol.license_application_id', '=', 'hla.license_application_id')
            ->where('haod.uid', $uid)
            ->where('hoa.status', 'issued')
            ->where('hla.type_of_application', 'new')
            ->orderBy('hol.license_issue_date', 'desc')
            ->select(
                'hol.license_no',
                'hol.license_issue_date as license_date',
                'hla.allotment_no',
                'hla.allotment_date'
            )
            ->first();
    }

    /**
     * Fetch allotment details from license
     */
    private function fetchAllotmentFromLicense($uid, $licenseData)
    {
        // Get flat details from existing license
        return DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hla.allotment_flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->where('haod.uid', $uid)
            ->where('hoa.status', 'issued')
            ->where('hla.type_of_application', 'new')
            ->select(
                'he.estate_name as rhe_name',
                'hf.block_no',
                'hf.flat_no',
                'hla.allotment_no',
                'hla.allotment_date'
            )
            ->first();
    }

    /**
     * Extract common application data from request
     */
    private function extractCommonApplicationData(Request $request)
    {
        return [
            'applicant_name' => $request->input('applicant_name'),
            'gender' => $request->input('gender'),
            'date_of_birth' => $request->input('date_of_birth'),
            'mobile_no' => $request->input('mobile_no'),
            'email' => $request->input('email'),
            'office_name' => $request->input('office_name'),
            'district_code' => $request->input('district'),
            'ddo_id' => $request->input('ddo_id'),
            'pay_band' => $request->input('pay_band'),
            'pay_in' => $request->input('pay_in'),
            'grade_pay' => $request->input('grade_pay'),
            'date_of_retirement' => $request->input('date_of_retirement'),
        ];
    }

    /**
     * Create online application
     */
    private function createOnlineApplication($uid, $action, $appType, $commonData)
    {
        $applicantOfficialDetail = DB::table('housing_applicant_official_detail')
            ->where('uid', $uid)
            ->first();

        if (!$applicantOfficialDetail) {
            throw new \Exception('Applicant official detail not found');
        }

        $applicationNo = $this->generateApplicationNumber($appType);

        DB::table('housing_online_application')->insert([
            'applicant_official_detail_id' => $applicantOfficialDetail->applicant_official_detail_id,
            'application_no' => $applicationNo,
            'status' => $action,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get the max online application ID (matching CommonApplicationController pattern)
        return DB::table('housing_online_application')
            ->where('applicant_official_detail_id', $applicantOfficialDetail->applicant_official_detail_id)
            ->max('online_application_id');
    }

    /**
     * Update online application
     */
    private function updateOnlineApplication($onlineApplicationId, $action, $commonData)
    {
        DB::table('housing_online_application')
            ->where('online_application_id', $onlineApplicationId)
            ->update([
                'status' => $action,
                'updated_at' => now(),
            ]);
    }

    /**
     * Create license application
     */
    private function createLicenseApplication(Request $request, $onlineApplicationId, $uid)
    {
        $allotmentDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->input('allotment_date'))));
        $licenseDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->input('license_date'))));

        $licenseAppData = [
            'online_application_id' => $onlineApplicationId,
            'type_of_application' => 'renew',
            'allotment_no' => $request->input('allotment_no'),
            'allotment_date' => $allotmentDate,
            'license_no' => $request->input('license_no'),
            'license_date' => $licenseDate,
            'rhe_name' => $request->input('rhe_name'),
            'block_no' => $request->input('block_no'),
            'flat_no' => $request->input('flat_no'),
        ];

        DB::table('housing_license_application')->insert($licenseAppData);
    }

    /**
     * Update license application
     */
    private function updateLicenseApplication(Request $request, $onlineApplicationId, $uid)
    {
        $allotmentDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->input('allotment_date'))));
        $licenseDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->input('license_date'))));

        $licenseAppData = [
            'allotment_no' => $request->input('allotment_no'),
            'allotment_date' => $allotmentDate,
            'license_no' => $request->input('license_no'),
            'license_date' => $licenseDate,
            'rhe_name' => $request->input('rhe_name'),
            'block_no' => $request->input('block_no'),
            'flat_no' => $request->input('flat_no'),
        ];

        DB::table('housing_license_application')
            ->where('online_application_id', $onlineApplicationId)
            ->update($licenseAppData);
    }

    /**
     * Generate application number
     */
    private function generateApplicationNumber($appType)
    {
        $year = date('Y');
        $lastApp = DB::table('housing_online_application')
            ->where('application_no', 'like', $appType . $year . '%')
            ->orderBy('application_no', 'desc')
            ->first();

        if ($lastApp) {
            $lastNum = (int)substr($lastApp->application_no, -4);
            $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNum = '0001';
        }

        return $appType . $year . $newNum;
    }
}
