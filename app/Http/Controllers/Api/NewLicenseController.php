<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class NewLicenseController extends Controller
{
    /**
     * Check for draft application status
     * GET /api/new-license/check-draft
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
            Log::error('New License Check Draft Error', [
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
     * Get allotment details for new license
     * GET /api/new-license/allotment-details
     */
    public function getAllotmentDetails(Request $request)
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
            $allotmentDetails = null;

            // First check if there's an existing license application
            $existingAppId = $this->getMaxLicenseApplicationId($uid, ['reject', 'cancel']);
            
            if ($existingAppId > 0) {
                // Fetch from license application
                $allotmentDetails = $this->fetchAllotmentFromLicenseApplication($uid, $existingAppId);
            } else {
                // Fetch from flat occupant (old allotment)
                $oldAppId = $this->getMaxNewOnlineApplicationId($uid, ['reject', 'cancel']);
                if ($oldAppId > 0) {
                    $allotmentDetails = $this->fetchAllotmentFromFlatOccupant($uid, $oldAppId);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $allotmentDetails,
                'online_application_id' => $existingAppId,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('New License Get Allotment Details Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch allotment details',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Store or update new license application
     * POST /api/new-license/store
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uid' => 'required|integer',
            'action' => 'required|in:draft,applied',
            'allotment_no' => 'required|string',
            'allotment_date' => 'required|date',
            'allotment_district' => 'required|string',
            'allotment_estate' => 'required|string',
            'allotment_address' => 'nullable|string',
            'allotment_flat_id' => 'required|integer',
            'document' => 'nullable|file|mimes:pdf|max:1024', // 1 MB max
            'scaned_sign' => 'nullable|file|mimes:jpg,jpeg|max:50', // 50 KB max
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

            // Get common application data (from request)
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

            // Handle scanned signature for new allotment application
            if ($request->hasFile('scaned_sign')) {
                $this->updateScannedSignature($uid, $onlineApplicationId, $request->file('scaned_sign'));
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
            Log::error('New License Store Error', [
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
            ->where('hla.type_of_application', 'new')
            ->whereNotIn('hoa.status', $excludeStatuses)
            ->max('hoa.online_application_id');

        return $result ?? 0;
    }

    /**
     * Get max new online application ID
     */
    private function getMaxNewOnlineApplicationId($uid, $excludeStatuses)
    {
        $result = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('haod.uid', $uid)
            ->whereNotIn('hoa.status', $excludeStatuses)
            ->whereRaw("SUBSTRING(hoa.application_no, 1, 2) = 'NA'")
            ->max('hoa.online_application_id');

        return $result ?? 0;
    }

    /**
     * Fetch allotment from license application
     */
    private function fetchAllotmentFromLicenseApplication($uid, $onlineApplicationId)
    {
        return DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('file_managed as fm', function($join) {
                $join->on(DB::raw('CAST(fm.fid AS TEXT)'), '=', DB::raw('CAST(hla.document AS TEXT)'))
                     ->whereRaw('hla.document ~ \'^[0-9]+$\''); // Only join if document is numeric (fid)
            })
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hla.allotment_flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_district as hd', 'hd.district_code', '=', 'he.district_code')
            ->where('haod.uid', $uid)
            ->where('hoa.online_application_id', $onlineApplicationId)
            ->select(
                'hla.allotment_no',
                'hla.allotment_date',
                'hla.allotment_district',
                'hla.allotment_estate',
                'hla.allotment_address',
                'hla.allotment_flat_id',
                'hla.document',
                DB::raw('CASE 
                    WHEN hla.document ~ \'^[0-9]+$\' THEN fm.uri 
                    ELSE hla.document 
                END as document_uri'),
                'hd.district_name'
            )
            ->first();
    }

    /**
     * Fetch allotment from flat occupant
     */
    private function fetchAllotmentFromFlatOccupant($uid, $onlineApplicationId)
    {
        return DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_district as hd', 'hd.district_code', '=', 'he.district_code')
            ->where('haod.uid', $uid)
            ->where('hoa.online_application_id', $onlineApplicationId)
            ->where('hfo.accept_reject_status', 'Accept')
            ->select(
                'hfo.allotment_no',
                'hfo.allotment_date',
                'hd.district_name as allotment_district',
                'he.estate_name as allotment_estate',
                'he.estate_address as allotment_address',
                'hfo.flat_id as allotment_flat_id'
            )
            ->first();
    }

    /**
     * Extract common application data from request
     */
    private function extractCommonApplicationData(Request $request)
    {
        // This should match the common application fields
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
            // Add other common fields as needed
        ];
    }

    /**
     * Create online application
     */
    private function createOnlineApplication($uid, $action, $appType, $commonData)
    {
        // Get applicant official detail ID
        $applicantOfficialDetail = DB::table('housing_applicant_official_detail')
            ->where('uid', $uid)
            ->first();

        if (!$applicantOfficialDetail) {
            throw new \Exception('Applicant official detail not found');
        }

        // Generate application number
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

        $licenseAppData = [
            'online_application_id' => $onlineApplicationId,
            'type_of_application' => 'new',
            'allotment_no' => $request->input('allotment_no'),
            'allotment_date' => $allotmentDate,
            'allotment_district' => $request->input('allotment_district'),
            'allotment_estate' => $request->input('allotment_estate'),
            'allotment_address' => $request->input('allotment_address'),
            'allotment_flat_id' => $request->input('allotment_flat_id'),
            'document' => null,
        ];

        // Handle document upload
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $path = $file->store('documents/new-license', 'public');
            $licenseAppData['document'] = $path;
        }

        DB::table('housing_license_application')->insert($licenseAppData);
    }

    /**
     * Update license application
     */
    private function updateLicenseApplication(Request $request, $onlineApplicationId, $uid)
    {
        $allotmentDate = date('Y-m-d', strtotime(str_replace('/', '-', $request->input('allotment_date'))));

        $licenseAppData = [
            'allotment_no' => $request->input('allotment_no'),
            'allotment_date' => $allotmentDate,
            'allotment_district' => $request->input('allotment_district'),
            'allotment_estate' => $request->input('allotment_estate'),
            'allotment_address' => $request->input('allotment_address'),
            'allotment_flat_id' => $request->input('allotment_flat_id'),
        ];

        // Handle document upload
        if ($request->hasFile('document')) {
            // Delete old document if exists
            $oldLicense = DB::table('housing_license_application')
                ->where('online_application_id', $onlineApplicationId)
                ->first();
            
            if ($oldLicense && $oldLicense->document) {
                Storage::disk('public')->delete($oldLicense->document);
            }

            $file = $request->file('document');
            $path = $file->store('documents/new-license', 'public');
            $licenseAppData['document'] = $path;
        }

        DB::table('housing_license_application')
            ->where('online_application_id', $onlineApplicationId)
            ->update($licenseAppData);
    }

    /**
     * Update scanned signature in new allotment application
     */
    private function updateScannedSignature($uid, $onlineApplicationId, $file)
    {
        // Check if user has a new allotment application
        $newAllotmentApp = DB::table('housing_new_allotment_application')
            ->where('online_application_id', $onlineApplicationId)
            ->first();

        if ($newAllotmentApp) {
            // Delete old signature if exists
            if ($newAllotmentApp->scaned_sign) {
                Storage::disk('public')->delete($newAllotmentApp->scaned_sign);
            }

            $path = $file->store('documents/scanned-signatures', 'public');

            DB::table('housing_new_allotment_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update(['scaned_sign' => $path]);
        }
    }

    /**
     * Generate application number
     */
    private function generateApplicationNumber($appType)
    {
        // Implementation similar to Drupal
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
