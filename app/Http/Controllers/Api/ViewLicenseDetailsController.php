<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViewLicenseDetailsController extends Controller
{
    /**
     * Get license details for a user by license type
     * GET /api/view-license-details/list
     */
    public function index(Request $request)
    {
        $licenseType = $request->input('license_type'); // 'new', 'vs', 'cs'
        $uid = $request->input('uid');

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'User ID is required',
                'status_code' => 422
            ], 422);
        }

        if (!in_array($licenseType, ['new', 'vs', 'cs'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid license type',
                'status_code' => 422
            ], 422);
        }

        try {
            // Fetch online application IDs for the user
            $applicationIds = DB::table('users as u')
                ->join('housing_applicant_official_detail as haod', 'haod.uid', '=', 'u.uid')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_occupant as hfo', 'hfo.allotment_no', '=', 'hla.allotment_no')
                ->where('u.uid', $uid)
                ->where('hoa.status', 'issued')
                ->where('hla.type_of_application', $licenseType)
                ->select(
                    'hoa.online_application_id',
                    'hfo.flat_occupant_id',
                    'hla.license_application_id'
                )
                ->get();

            if ($applicationIds->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [],
                    'status_code' => 200
                ], 200);
            }

            // Get license details for each application
            $licenseDetails = [];
            foreach ($applicationIds as $appId) {
                $details = $this->getIndividualLicenseDetails(
                    $licenseType,
                    $appId->online_application_id,
                    $appId->flat_occupant_id,
                    $appId->license_application_id
                );

                if ($details) {
                    $licenseDetails[] = $details;
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $licenseDetails,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('View License Details Error', [
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
     * Get individual license details
     */
    private function getIndividualLicenseDetails($licenseType, $onlineApplicationId, $flatOccupantId, $licenseApplicationId)
    {
        $query = DB::table('housing_occupant_license as hol')
            ->join('housing_license_application as hla', 'hla.license_application_id', '=', 'hol.license_application_id')
            ->join('housing_flat_occupant as hfo', 'hfo.allotment_no', '=', 'hla.allotment_no')
            ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hla.online_application_id')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->join('housing_district as hd', 'hd.district_code', '=', 'he.district_code')
            ->join('housing_ddo as hddo', 'hddo.ddo_id', '=', 'haod.ddo_id')
            ->where('hoa.status', 'issued')
            ->where('hla.type_of_application', $licenseType)
            ->where('hoa.online_application_id', $onlineApplicationId)
            ->where('hfo.flat_occupant_id', $flatOccupantId)
            ->where('hla.license_application_id', $licenseApplicationId)
            ->select(
                'hoa.application_no',
                'hol.license_no',
                'hol.license_issue_date',
                'hol.license_expiry_date',
                'hol.occupant_license_id',
                'ha.applicant_name',
                'ha.gender',
                'haod.applicant_designation',
                'haod.date_of_retirement',
                'haod.office_name',
                'hddo.ddo_designation',
                'hddo.ddo_address',
                'hf.flat_no',
                'hla.allotment_district',
                'hla.allotment_estate',
                'hla.allotment_address',
                'hla.type_of_application',
                'hoa.online_application_id',
                'hfo.flat_occupant_id',
                'hla.license_application_id'
            )
            ->first();

        return $query;
    }

    /**
     * Get license details for PDF download
     * GET /api/view-license-details/details
     */
    public function getLicenseDetailsForPdf(Request $request)
    {
        $request->validate([
            'license_type' => 'required|in:new,vs,cs',
            'online_application_id' => 'required|integer',
            'flat_occupant_id' => 'required|integer',
            'license_application_id' => 'required|integer'
        ]);

        try {
            $details = $this->getIndividualLicenseDetails(
                $request->license_type,
                $request->online_application_id,
                $request->flat_occupant_id,
                $request->license_application_id
            );

            if (!$details) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'License details not found',
                    'status_code' => 404
                ], 404);
            }

            // Format dates
            $issueDate = $details->license_issue_date 
                ? date('d/m/Y', strtotime($details->license_issue_date)) 
                : '';
            $expiryDate = $details->license_expiry_date 
                ? date('d/m/Y', strtotime($details->license_expiry_date)) 
                : '';
            $retirementDate = $details->date_of_retirement 
                ? date('d/m/Y', strtotime($details->date_of_retirement)) 
                : '';

            // Determine gender prefix
            $genderPrefix = ($details->gender == 'M') ? 'Sri.' : (($details->gender == 'F') ? 'Smt.' : '');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'license_no' => $details->license_no,
                    'license_issue_date' => $issueDate,
                    'license_expiry_date' => $expiryDate,
                    'applicant_name' => $details->applicant_name,
                    'applicant_designation' => $details->applicant_designation,
                    'gender_prefix' => $genderPrefix,
                    'flat_no' => $details->flat_no,
                    'allotment_estate' => $details->allotment_estate,
                    'allotment_address' => $details->allotment_address,
                    'allotment_district' => $details->allotment_district,
                    'date_of_retirement' => $retirementDate,
                    'office_name' => $details->office_name,
                    'ddo_designation' => $details->ddo_designation,
                    'ddo_address' => $details->ddo_address,
                ],
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get License Details for PDF Error', [
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
}
