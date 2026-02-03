<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LicenseListController extends Controller
{
    /**
     * Get licensee list by type
     * GET /api/license-list/list
     */
    public function index(Request $request)
    {
        $licenseeType = $request->input('licensee_type'); // 1=New, 2=VS, 3=CS
        $rheId = $request->input('rhe_id'); // For RHE wise list

        try {
            if ($rheId) {
                // RHE Wise Licensee List
                $query = DB::table('housing_applicant_official_detail as haod')
                    ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
                    ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                    ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
                    ->join('housing_occupant_license as hol', 'hol.license_application_id', '=', 'hla.license_application_id')
                    ->join('housing_flat_occupant as hfo', 'hfo.flat_occupant_id', '=', 'hol.flat_occupant_id')
                    ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                    ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                    ->where('hoa.status', 'issued')
                    ->where('he.estate_id', $rheId)
                    ->select(
                        'ha.applicant_name',
                        'hoa.online_application_id',
                        'hoa.application_no',
                        'hla.type_of_application',
                        'hol.occupant_license_id',
                        'hol.license_no',
                        'hol.license_issue_date',
                        'hol.license_expiry_date',
                        'hol.uploaded_licence',
                        'he.estate_name'
                    )
                    ->orderBy('hoa.online_application_id', 'ASC');
            } else {
                // Regular Licensee List by Type
                $query = DB::table('housing_applicant_official_detail as haod')
                    ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
                    ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                    ->join('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
                    ->join('housing_occupant_license as hol', 'hol.license_application_id', '=', 'hla.license_application_id')
                    ->join('housing_flat_occupant as hfo', 'hfo.flat_occupant_id', '=', 'hol.flat_occupant_id')
                    ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                    ->where('hoa.status', 'issued')
                    ->select(
                        'ha.applicant_name',
                        'hoa.online_application_id',
                        'hoa.application_no',
                        'hla.type_of_application',
                        'hol.license_no',
                        'hol.license_issue_date',
                        'hol.license_expiry_date'
                    )
                    ->orderBy('hoa.online_application_id', 'ASC');

                // Filter by licensee type
                if ($licenseeType == 1) {
                    $query->where('hla.type_of_application', 'new');
                } elseif ($licenseeType == 2) {
                    $query->where('hla.type_of_application', 'vs');
                } elseif ($licenseeType == 3) {
                    $query->where('hla.type_of_application', 'cs');
                }
            }

            $licenses = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $licenses,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('License List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch license list',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get RHE list for dropdown
     * GET /api/license-list/rhe-list
     */
    public function getRheList(Request $request)
    {
        $userRole = $request->input('user_role');
        $uid = $request->input('uid');

        try {
            if ($userRole == 7 || $userRole == 8) {
                // Division/Sub-division users - get specific RHEs
                $rhes = DB::table('housing_estate as he')
                    ->join('housing_estate_treasury_mapping as hetm', 'hetm.estate_id', '=', 'he.estate_id')
                    ->join('housing_applicant_official_detail as haod', function($join) use ($uid) {
                        $join->on('haod.ddo_id', '=', 'hetm.ddo_id')
                             ->where('haod.uid', '=', $uid);
                    })
                    ->select('he.estate_id', 'he.estate_name')
                    ->distinct()
                    ->orderBy('he.estate_name', 'ASC')
                    ->get();
            } else {
                // Admin - get all RHEs
                $rhes = DB::table('housing_estate')
                    ->select('estate_id', 'estate_name')
                    ->orderBy('estate_name', 'ASC')
                    ->get();
            }

            return response()->json([
                'status' => 'success',
                'data' => $rhes,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('RHE List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch RHE list',
                'status_code' => 500
            ], 500);
        }
    }
}
