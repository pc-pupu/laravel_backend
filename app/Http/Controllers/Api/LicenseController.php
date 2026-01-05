<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LicenseController extends Controller
{
    /**
     * Generate license for an application
     * POST /api/license/generate
     */
    public function generate(Request $request)
    {
        $applicationId = $request->input('online_application_id');
        $uid = $request->input('uid');

        if (!$applicationId || !$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Application ID and UID are required',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Get allotment details
            $allotmentDetails = DB::table('housing_online_application as hoa')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                ->join('housing_district as hd', 'hd.district_code', '=', 'he.district_code')
                ->where('hoa.online_application_id', $applicationId)
                ->where('haod.is_active', 1)
                ->select(
                    'hoa.application_no',
                    'hfo.allotment_no',
                    'hfo.allotment_date',
                    'hfo.flat_id',
                    'hfo.flat_occupant_id',
                    'he.estate_name',
                    'he.estate_address',
                    'hd.district_name'
                )
                ->first();

            if (!$allotmentDetails) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Allotment details not found',
                ], 404);
            }

            // Determine application type
            $applicationType = $this->getApplicationType($allotmentDetails->application_no);

            // Create license application
            $licenseAppId = DB::table('housing_license_application')->insertGetId([
                'online_application_id' => $applicationId,
                'type_of_application' => $applicationType,
                'allotment_no' => $allotmentDetails->allotment_no,
                'allotment_date' => $allotmentDetails->allotment_date,
                'allotment_district' => $allotmentDetails->district_name,
                'allotment_estate' => $allotmentDetails->estate_name,
                'allotment_address' => $allotmentDetails->estate_address,
                'allotment_flat_id' => $allotmentDetails->flat_id,
            ]);

            // Generate license number
            $licenseNo = $this->generateLicenseNumber($applicationType, $licenseAppId);

            // Create occupant license
            $licenseIssueDate = date('Y-m-d');
            $licenseExpiryDate = date('Y-m-d', strtotime($licenseIssueDate . '+3 years -1 day'));

            DB::table('housing_occupant_license')->insert([
                'flat_occupant_id' => $allotmentDetails->flat_occupant_id,
                'license_application_id' => $licenseAppId,
                'license_issue_date' => $licenseIssueDate,
                'license_expiry_date' => $licenseExpiryDate,
                'license_no' => $licenseNo,
            ]);

            // Update application status
            DB::table('housing_online_application')
                ->where('online_application_id', $applicationId)
                ->update(['status' => 'license_generate']);

            // Get status data
            $statusData = DB::table('housing_allotment_status_master')
                ->where('short_code', 'license_generate')
                ->first();

            // Insert into process flow
            DB::table('housing_process_flow')->insert([
                'online_application_id' => $applicationId,
                'status_id' => $statusData->status_id,
                'created_at' => now(),
                'uid' => $uid,
                'short_code' => 'license_generate',
                'status_weight' => $statusData->weight,
            ]);

            DB::commit();

            // Send notification
            $this->sendLicenseGenerationNotification($applicationId);

            return response()->json([
                'status' => 'success',
                'message' => "License {$licenseNo} is generated for Application No.={$allotmentDetails->application_no}",
                'data' => [
                    'license_no' => $licenseNo,
                    'license_application_id' => $licenseAppId,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('License Generation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate license',
            ], 500);
        }
    }

    /**
     * Get license list
     * GET /api/license/list
     */
    public function list(Request $request)
    {
        $userRole = $request->input('user_role');
        $ddoCode = $request->input('ddo_code');

        try {
            $query = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
                ->join('housing_allotment_status_master as hsm', 'hsm.short_code', '=', 'hoa.status')
                ->whereIn('hoa.status', ['license_generate', 'flat_possession_taken', 'license_extended', 'flat_released']);

            // Filter by DDO code for DDO role
            if ($userRole == 11 && $ddoCode) {
                $query->where('hd.ddo_code', $ddoCode);
            }

            $licenses = $query->select(
                'ha.applicant_name',
                'hoa.online_application_id',
                'hoa.application_no',
                'hoa.date_of_application',
                'hoa.date_of_verified',
                'hoa.computer_serial_no',
                'hoa.is_backlog_applicant',
                'hoa.uploaded_app_form',
                'hoa.status'
            )
            ->orderBy('hoa.online_application_id', 'ASC')
            ->get();

            return response()->json([
                'status' => 'success',
                'data' => $licenses,
            ]);

        } catch (\Exception $e) {
            Log::error('License List Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch license list',
            ], 500);
        }
    }

    /**
     * Get flat possession taken list (for DDO)
     * GET /api/license/flat-possession-taken
     */
    public function flatPossessionTaken(Request $request)
    {
        $userRole = $request->input('user_role');
        $ddoCode = $request->input('ddo_code');

        try {
            $query = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_occupant_license as hol', 'hol.license_application_id', '=', 'hla.license_application_id')
                ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
                ->join('housing_allotment_status_master as hsm', 'hsm.short_code', '=', 'hoa.status')
                ->where('hoa.status', 'flat_possession_taken');

            if ($userRole == 11 && $ddoCode) {
                $query->where('hd.ddo_code', $ddoCode);
            }

            $list = $query->select(
                'hoa.online_application_id',
                'ha.applicant_name',
                'hoa.application_no',
                'hol.possession_date'
            )
            ->orderBy('hoa.online_application_id', 'ASC')
            ->get();

            return response()->json([
                'status' => 'success',
                'data' => $list,
            ]);

        } catch (\Exception $e) {
            Log::error('Flat Possession Taken List Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch list',
            ], 500);
        }
    }

    /**
     * Get flat released list (for DDO)
     * GET /api/license/flat-released
     */
    public function flatReleased(Request $request)
    {
        $userRole = $request->input('user_role');
        $ddoCode = $request->input('ddo_code');

        try {
            $query = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_occupant_license as hol', 'hol.license_application_id', '=', 'hla.license_application_id')
                ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
                ->join('housing_allotment_status_master as hsm', 'hsm.short_code', '=', 'hoa.status')
                ->where('hoa.status', 'flat_released');

            if ($userRole == 11 && $ddoCode) {
                $query->where('hd.ddo_code', $ddoCode);
            }

            $list = $query->select(
                'hoa.online_application_id',
                'ha.applicant_name',
                'hoa.application_no',
                'hol.possession_date'
            )
            ->orderBy('hoa.online_application_id', 'ASC')
            ->get();

            return response()->json([
                'status' => 'success',
                'data' => $list,
            ]);

        } catch (\Exception $e) {
            Log::error('Flat Released List Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch list',
            ], 500);
        }
    }

    /**
     * Get application type from application number
     */
    private function getApplicationType($applicationNo)
    {
        if (strpos($applicationNo, 'NA') !== false) {
            return 'New';
        } elseif (strpos($applicationNo, 'VS') !== false) {
            return 'Vertical Shifting';
        } elseif (strpos($applicationNo, 'CS') !== false) {
            return 'Category Shifting';
        } elseif (strpos($applicationNo, 'PA') !== false) {
            return 'Physical Application';
        } elseif (strpos($applicationNo, 'EO') !== false) {
            return 'Existing Occupant';
        }

        return 'New';
    }

    /**
     * Generate license number
     */
    private function generateLicenseNumber($applicationType, $licenseAppId)
    {
        $prefixMap = [
            'New' => 'INL-',
            'Vertical Shifting' => 'IVSL-',
            'Category Shifting' => 'ICSL-',
            'Physical Application' => 'IPAL-',
        ];

        $prefix = $prefixMap[$applicationType] ?? 'INL-';
        return $prefix . $licenseAppId;
    }

    /**
     * Send license generation notification
     */
    private function sendLicenseGenerationNotification($applicationId)
    {
        // This would typically call a notification service
        Log::info('License generated notification', [
            'application_id' => $applicationId,
        ]);
    }
}

