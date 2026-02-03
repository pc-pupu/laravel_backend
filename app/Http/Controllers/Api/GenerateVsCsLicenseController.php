<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateVsCsLicenseController extends Controller
{
    /**
     * Get VS/CS license list (for admin/official)
     * GET /api/generate-vs-cs-license/list
     */
    public function index(Request $request)
    {
        $licenseType = $request->input('type'); // 'vs' or 'cs'
        $uid = $request->input('uid');

        if (!in_array($licenseType, ['vs', 'cs'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid license type. Must be vs or cs.',
                'status_code' => 422
            ], 422);
        }

        try {
            $status = ['verified', 'issued'];

            $query = DB::table('housing_license_application as hla')
                ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hla.online_application_id')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->join('housing_flat_occupant as hfo', 'hfo.allotment_no', '=', 'hla.allotment_no')
                ->whereIn('hoa.status', $status)
                ->where('hla.type_of_application', $licenseType);

            if ($uid) {
                $query->join('users as u', 'u.uid', '=', 'haod.uid')
                    ->where('u.uid', $uid);
            }

            $licenses = $query->select(
                'hla.online_application_id',
                'hla.license_application_id',
                'hla.allotment_no',
                'hla.allotment_date',
                'hla.allotment_district',
                'hla.allotment_estate',
                'hla.allotment_address',
                'hfo.flat_occupant_id',
                'hoa.status'
            )
            ->orderBy('hla.online_application_id', 'ASC')
            ->get();

            // Get license details for each
            foreach ($licenses as $license) {
                $licenseDetails = $this->getLicenseDetails($licenseType, $license->online_application_id, $license->flat_occupant_id, $license->license_application_id);
                $license->license_details = $licenseDetails;
                $license->has_uploaded_licence = !empty($licenseDetails->uploaded_licence);
            }

            return response()->json([
                'status' => 'success',
                'data' => $licenses,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('VS/CS License List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'type' => $licenseType
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch license list',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Generate VS/CS License (update status to 'issued' and create license record)
     * POST /api/generate-vs-cs-license/generate
     */
    public function generate(Request $request)
    {
        $request->validate([
            'online_application_id' => 'required|integer',
            'flat_occupant_id' => 'required|integer',
            'license_application_id' => 'required|integer',
            'type' => 'required|in:vs,cs'
        ]);

        $onlineApplicationId = $request->online_application_id;
        $flatOccupantId = $request->flat_occupant_id;
        $licenseApplicationId = $request->license_application_id;
        $licenseType = $request->type;

        try {
            DB::beginTransaction();

            // Update application status to 'issued'
            DB::table('housing_online_application')
                ->where('online_application_id', $onlineApplicationId)
                ->update(['status' => 'issued']);

            // Generate license number
            $licensePrefix = $licenseType === 'vs' ? 'IVSL-' : 'ICSL-';
            $licenseNo = $licensePrefix . $licenseApplicationId;

            // Calculate dates
            $licenseIssueDate = date('Y-m-d');
            $licenseExpiryDate = date('Y-m-d', strtotime($licenseIssueDate . '+3 years -1 day'));

            // Check if license already exists
            $existingLicense = DB::table('housing_occupant_license')
                ->where('flat_occupant_id', $flatOccupantId)
                ->where('license_application_id', $licenseApplicationId)
                ->first();

            if ($existingLicense) {
                // Update existing license
                DB::table('housing_occupant_license')
                    ->where('occupant_license_id', $existingLicense->occupant_license_id)
                    ->update([
                        'license_issue_date' => $licenseIssueDate,
                        'license_expiry_date' => $licenseExpiryDate,
                        'license_no' => $licenseNo
                    ]);
            } else {
                // Insert new license
                DB::table('housing_occupant_license')->insert([
                    'flat_occupant_id' => $flatOccupantId,
                    'license_application_id' => $licenseApplicationId,
                    'license_issue_date' => $licenseIssueDate,
                    'license_expiry_date' => $licenseExpiryDate,
                    'license_no' => $licenseNo
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'License generated successfully.',
                'data' => [
                    'license_no' => $licenseNo,
                    'license_issue_date' => $licenseIssueDate,
                    'license_expiry_date' => $licenseExpiryDate
                ],
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Generate VS/CS License Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate license',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get license details
     */
    private function getLicenseDetails($licenseType, $onlineApplicationId, $flatOccupantId, $licenseApplicationId)
    {
        $query = DB::table('housing_occupant_license as hol')
            ->join('housing_license_application as hla', 'hla.license_application_id', '=', 'hol.license_application_id')
            ->join('housing_flat_occupant as hfo', 'hfo.flat_occupant_id', '=', 'hol.flat_occupant_id')
            ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hla.online_application_id')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->leftJoin('housing_district as hd', 'hd.district_code', '=', 'he.district_code')
            ->leftJoin('housing_ddo as hddo', 'hddo.ddo_id', '=', 'haod.ddo_id')
            ->where('hoa.online_application_id', $onlineApplicationId)
            ->where('hfo.flat_occupant_id', $flatOccupantId)
            ->where('hla.license_application_id', $licenseApplicationId)
            ->select(
                'hol.occupant_license_id',
                'hol.license_no',
                'hol.license_issue_date',
                'hol.license_expiry_date',
                'hol.uploaded_licence',
                'ha.applicant_name',
                'ha.gender',
                'haod.applicant_designation',
                'haod.date_of_retirement',
                'haod.office_name',
                'haod.uid',
                'hddo.ddo_designation',
                'hddo.ddo_address',
                'hf.flat_no',
                'hla.allotment_district',
                'hla.allotment_estate',
                'hla.allotment_address',
                'hla.type_of_application'
            )
            ->first();

        return $query;
    }

    /**
     * Get license details for PDF/download
     * GET /api/generate-vs-cs-license/details
     */
    public function getLicenseDetailsForPdf(Request $request)
    {
        $request->validate([
            'online_application_id' => 'required|integer',
            'flat_occupant_id' => 'required|integer',
            'license_application_id' => 'required|integer',
            'type' => 'required|in:vs,cs'
        ]);

        try {
            $licenseDetails = $this->getLicenseDetails(
                $request->type,
                $request->online_application_id,
                $request->flat_occupant_id,
                $request->license_application_id
            );

            if (!$licenseDetails) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'License details not found',
                    'status_code' => 404
                ], 404);
            }

            // Format dates
            $issueDate = $licenseDetails->license_issue_date 
                ? date('d/m/Y', strtotime($licenseDetails->license_issue_date)) 
                : '';
            $expiryDate = $licenseDetails->license_expiry_date 
                ? date('d/m/Y', strtotime($licenseDetails->license_expiry_date)) 
                : '';
            $retirementDate = $licenseDetails->date_of_retirement 
                ? date('d/m/Y', strtotime($licenseDetails->date_of_retirement)) 
                : '';

            // Determine gender prefix
            $genderPrefix = ($licenseDetails->gender == 'M') ? 'Sri.' : (($licenseDetails->gender == 'F') ? 'Smt.' : '');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'license_no' => $licenseDetails->license_no,
                    'license_issue_date' => $issueDate,
                    'license_expiry_date' => $expiryDate,
                    'applicant_name' => $licenseDetails->applicant_name,
                    'applicant_designation' => $licenseDetails->applicant_designation,
                    'gender_prefix' => $genderPrefix,
                    'flat_no' => $licenseDetails->flat_no,
                    'allotment_estate' => $licenseDetails->allotment_estate,
                    'allotment_address' => $licenseDetails->allotment_address,
                    'allotment_district' => $licenseDetails->allotment_district,
                    'date_of_retirement' => $retirementDate,
                    'office_name' => $licenseDetails->office_name,
                    'ddo_designation' => $licenseDetails->ddo_designation,
                    'ddo_address' => $licenseDetails->ddo_address,
                    'uploaded_licence' => $licenseDetails->uploaded_licence,
                ],
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get License Details Error', [
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
     * Upload signed license
     * POST /api/generate-vs-cs-license/upload-signed
     */
    public function uploadSignedLicense(Request $request)
    {
        $request->validate([
            'occupant_license_id' => 'required|integer',
            'license_no' => 'required|string',
            'type' => 'required|in:vs,cs',
            'signed_licence_file' => 'required|file|mimes:pdf|max:1024' // 1MB max
        ]);

        try {
            $occupantLicenseId = $request->occupant_license_id;
            $licenseType = $request->type;

            // Verify license exists and type matches
            $license = DB::table('housing_occupant_license as hol')
                ->join('housing_license_application as hla', 'hla.license_application_id', '=', 'hol.license_application_id')
                ->where('hol.occupant_license_id', $occupantLicenseId)
                ->where('hla.type_of_application', $licenseType)
                ->select('hol.occupant_license_id', 'hol.uploaded_licence', 'hla.online_application_id')
                ->first();

            if (!$license) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'License not found or type mismatch',
                    'status_code' => 404
                ], 404);
            }

            if (!empty($license->uploaded_licence)) {
                // Delete old file if exists
                $oldPath = $license->uploaded_licence;
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Upload new file
            $file = $request->file('signed_licence_file');
            $fileName = $request->license_no . '_Signed_' . ($licenseType === 'vs' ? 'Floor' : 'Category') . '_Shifting_Licence.pdf';
            $path = $file->storeAs('signed_licence', $fileName, 'public');

            // Update database
            DB::table('housing_occupant_license')
                ->where('occupant_license_id', $occupantLicenseId)
                ->update(['uploaded_licence' => $path]);

            // Get user email for notification
            $userData = DB::table('housing_occupant_license as hol')
                ->join('housing_license_application as hla', 'hla.license_application_id', '=', 'hol.license_application_id')
                ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hla.online_application_id')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->join('users as u', 'u.uid', '=', 'haod.uid')
                ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
                ->where('hol.occupant_license_id', $occupantLicenseId)
                ->select('u.mail as email', 'ha.applicant_name')
                ->first();

            // Send email notification (if email exists)
            if ($userData && !empty($userData->email)) {
                // TODO: Implement email sending
                Log::info('License uploaded notification', [
                    'email' => $userData->email,
                    'license_no' => $request->license_no
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Signed ' . ($licenseType === 'vs' ? 'Floor' : 'Category') . ' Shifting Licence uploaded successfully.',
                'data' => [
                    'file_path' => $path
                ],
                'status_code' => 200
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'status_code' => 422
            ], 422);
        } catch (\Exception $e) {
            Log::error('Upload Signed License Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload signed license',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Download signed license
     * GET /api/generate-vs-cs-license/download-signed/{occupant_license_id}
     */
    public function downloadSignedLicense($occupantLicenseId)
    {
        try {
            $license = DB::table('housing_occupant_license')
                ->where('occupant_license_id', $occupantLicenseId)
                ->select('uploaded_licence', 'license_no')
                ->first();

            if (!$license || empty($license->uploaded_licence)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Signed license file not found',
                    'status_code' => 404
                ], 404);
            }

            $filePath = $license->uploaded_licence;

            // Check if it's a file path or a Drupal file URI
            if (strpos($filePath, 'public://') === 0) {
                // Drupal file URI - convert to Laravel storage path
                $filePath = str_replace('public://', '', $filePath);
            }

            if (!Storage::disk('public')->exists($filePath)) {
                // Try in storage/app/public
                $fullPath = storage_path('app/public/' . $filePath);
                if (!file_exists($fullPath)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'File not found on server',
                        'status_code' => 404
                    ], 404);
                }
                return response()->download($fullPath, $license->license_no . '_Licence_Copy.pdf');
            }

            return Storage::disk('public')->download($filePath, $license->license_no . '_Licence_Copy.pdf');

        } catch (\Exception $e) {
            Log::error('Download Signed License Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to download signed license',
                'status_code' => 500
            ], 500);
        }
    }
}
