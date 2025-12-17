<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\AuthEncryptionHelper;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    /**
     * Get Dashboard Data
     * GET /api/dashboard
     */
    public function index(Request $request)
    {
        try {
            $uid = $request->input('uid');
            $username = $request->input('username');
            $userType = $request->input('user_type'); // Cookie value

            if (!$uid || !$username) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing required parameters: uid, username',
                    'status_code' => 400
                ], 400);
            }

            // Get user role
            $userRole = DB::table('user_role')
                ->where('uid', $uid)
                ->orderBy('rid', 'ASC')
                ->value('rid');

            if (!$userRole) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User role not found',
                    'status_code' => 404
                ], 404);
            }

            $output = [];
            $output['user_role'] = $userRole;

            // Role-based dashboard content
            if (in_array($userRole, [4, 5])) {
                // Applicant Dashboard
                $output = array_merge($output, $this->getApplicantDashboardData($uid, $username, $userRole, $userType));
            } elseif (in_array($userRole, [6, 7, 8, 10, 11, 13, 17])) {
                // Admin Dashboard
                $output = array_merge($output, $this->getAdminDashboardData($uid, $username, $userRole));
            }

            return response()->json([
                'status' => 'success',
                'data' => $output,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Dashboard Data Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get Applicant Dashboard Data (Roles 4, 5)
     */
    private function getApplicantDashboardData($uid, $username, $userRole, $userType = null)
    {
        $output = [];

        // Role 4 specific logic
        if ($userRole == 4 && $userType !== 'new') {
            // Check HRMS tagging
            $hrmsTagging = DB::table('housing_user_tagging')
                ->select('flag')
                ->where('hrms_id', trim($username))
                ->first();

            if (!$hrmsTagging) {
                $onlineApp = DB::table('housing_applicant_official_detail')
                    ->where('is_active', 1)
                    ->where('hrms_id', $username)
                    ->first();

                if (!$onlineApp) {
                    return [
                        'redirect' => '/user-tagging',
                        'redirect_message' => null
                    ];
                }
            } else {
                if (in_array($hrmsTagging->flag, ['new', 'pending'])) {
                    return [
                        'redirect' => '/user-tagging',
                        'redirect_message' => 'Please wait for the departmental approval.'
                    ];
                }
            }
        }

        // Get user info
        $output['user_info'] = $this->getHRMSUserData($username);
        $output['user_info']['email'] = $output['user_info']['email'] ?? 
            DB::table('users')->where('uid', $uid)->value('mail') ?? 'N/A';

        // Get user status
        $userStatus = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->where('haod.uid', $uid)
            ->where('haod.is_active', 1)
            ->whereIn('hoa.status', ['offer_letter_cancel', 'license_cancel'])
            ->select('hoa.status')
            ->first();

        $output['user_status'] = $userStatus->status ?? '';

        // Get all application data
        $output['all-application-data'] = $this->getAllApplicationDetails($uid);

        // Get current status (offer/allotment)
        $output['fetch_current_status'] = DB::table('housing_process_flow as hpf')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hpf.online_application_id')
            ->where('hpf.uid', $uid)
            ->where('hpf.short_code', 'applicant_acceptance')
            ->orderByDesc('hpf.online_application_id')
            ->select('hpf.short_code', 'hpf.online_application_id', 'hfo.allotment_no')
            ->first();

        // Get license status
        $output['fetch_license_status'] = DB::table('housing_process_flow as hpf')
            ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hpf.online_application_id')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->where('hpf.short_code', 'license_generate')
            ->where('haod.is_active', 1)
            ->where('haod.uid', $uid)
            ->select('hpf.short_code', 'hpf.online_application_id')
            ->first();

        return $output;
    }

    /**
     * Get Admin Dashboard Data (Roles 6, 7, 8, 10, 11, 13, 17)
     */
    private function getAdminDashboardData($uid, $username, $userRole)
    {
        $output = [];

        $roleName = DB::table('roles')->where('id', $userRole)->value('name') ?? 'User';

        // Role 11: DDO
        if ($userRole == 11) {
            $ddoMapping = DB::table('housing_ddo_hrms_mapping')
                ->where('ddo_code', $username)
                ->where('is_active', 'Y')
                ->first();

            if (!empty($ddoMapping)) {
                $hrmsData = $this->getHRMSUserData($ddoMapping->hrms_id);
                $output['user_info'] = [
                    'applicantName' => $username . ' (' . ($hrmsData['applicantName'] ?? '') . ')',
                    'applicantDesignation' => $roleName . ' (' . ($hrmsData['applicantDesignation'] ?? '') . ')',
                    'email' => DB::table('users')->where('uid', $uid)->value('mail'),
                    'officeName' => $hrmsData['officeName'] ?? 'N/A',
                    'mobileNo' => $hrmsData['mobileNo'] ?? 'N/A',
                ];
            } else {
                $output['user_info'] = [
                    'applicantName' => $username,
                    'mobileNo' => 'N/A',
                    'applicantDesignation' => $roleName,
                    'email' => DB::table('users')->where('uid', $uid)->value('mail'),
                    'officeName' => 'Housing Department',
                ];
            }

            // Fetch application counts for DDO
            $output['new-apply'] = $this->applicationListFetch('new-apply', 'applied');
            $output['vs'] = $this->applicationListFetch('vs', 'applied');
            $output['cs'] = $this->applicationListFetch('cs', 'applied');
            $output['allotted-apply'] = $this->applicationListFetch('new-apply', 'applicant_acceptance');
            $output['allotted-vs'] = $this->applicationListFetch('vs', 'applicant_acceptance');
            $output['allotted-cs'] = $this->applicationListFetch('cs', 'applicant_acceptance');

        } elseif ($userRole == 10) {
            // Housing Supervisor
            $output['user_info'] = [
                'applicantName' => $username,
                'mobileNo' => 'N/A',
                'applicantDesignation' => $roleName,
                'email' => DB::table('users')->where('uid', $uid)->value('mail'),
                'officeName' => 'Housing Department',
            ];

            $output['new-apply'] = $this->applicationListFetch('new-apply', 'ddo_verified_1');
            $output['vs'] = $this->applicationListFetch('vs', 'ddo_verified_1');
            $output['cs'] = $this->applicationListFetch('cs', 'ddo_verified_1');
            $output['allotted-apply'] = $this->applicationListFetch('new-apply', 'ddo_verified_2');
            $output['allotted-vs'] = $this->applicationListFetch('vs', 'ddo_verified_2');
            $output['allotted-cs'] = $this->applicationListFetch('cs', 'ddo_verified_2');

        } elseif ($userRole == 13) {
            // Housing Approver
            $output['user_info'] = [
                'applicantName' => $username,
                'mobileNo' => 'N/A',
                'applicantDesignation' => $roleName,
                'email' => DB::table('users')->where('uid', $uid)->value('mail'),
                'officeName' => 'Housing Department',
            ];

            $output['new-apply'] = $this->applicationListFetch('new-apply', 'housing_sup_approved_1');
            $output['vs'] = $this->applicationListFetch('vs', 'housing_sup_approved_1');
            $output['cs'] = $this->applicationListFetch('cs', 'housing_sup_approved_1');
            $output['allotted-apply'] = $this->applicationListFetch('new-apply', 'housing_sup_approved_2');
            $output['allotted-vs'] = $this->applicationListFetch('vs', 'housing_sup_approved_2');
            $output['allotted-cs'] = $this->applicationListFetch('cs', 'housing_sup_approved_2');

        } elseif ($userRole == 6) {
            // Housing Official
            $output['user_info'] = [
                'applicantName' => $username,
                'mobileNo' => 'N/A',
                'applicantDesignation' => $roleName,
                'email' => DB::table('users')->where('uid', $uid)->value('mail'),
                'officeName' => 'Housing Department',
            ];

            $output['all-applications'] = $this->pendingAppListFetchSecy('allotted');
            $output['all-license'] = DB::table('housing_online_application')
                ->where('status', 'housingapprover_approved_2')->count();

            // Flat type counts
            $output['flatTypeCounts'] = $this->getFlatTypeCounts();

        } elseif (in_array($userRole, [7, 8])) {
            // Occupant Manager
            $output['user_info'] = [
                'applicantName' => $username,
                'mobileNo' => 'N/A',
                'applicantDesignation' => $roleName,
                'email' => DB::table('users')->where('uid', $uid)->value('mail'),
                'officeName' => 'Housing Department',
            ];

            $output['all-exsting-occupant'] = $this->occupantListFetch($uid);

            if ($userRole == 7) {
                $output['auto-cancellation'] = $this->autoCancellationApplicantListFetch($uid);
                $output['existing_occupant_data'] = $this->fetchWithoutHrmsDataCount();
            }

        } elseif ($userRole == 17) {
            // Special Recommendations
            $output['user_info'] = [
                'applicantName' => $username,
                'mobileNo' => 'N/A',
                'applicantDesignation' => $roleName,
                'email' => DB::table('users')->where('uid', $uid)->value('mail'),
                'officeName' => 'Housing Department',
            ];

            $output['all-applications'] = $this->pendingAppListFetchSecy('allotted');
            $output['special-recommendation-list-data'] = $this->fetchSpecialRecommendationListData();
        }

        return $output;
    }

    /**
     * Get All Application Details for Applicant
     */
    private function getAllApplicationDetails($uid)
    {
        return DB::table('housing_applicant_official_detail as haod')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_allotment_status_master as hasm', 'hasm.short_code', '=', 'hoa.status')
            ->leftJoin('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
            ->where('haod.uid', $uid)
            ->select(
                'hoa.application_no',
                'hoa.date_of_application',
                'hoa.online_application_id',
                'haod.applicant_designation',
                'ha.applicant_name',
                'hasm.status_description',
                'hnaa.extra_doc',
                'hnaa.extra_doc_path',
                'hnaa.allotment_category'
            )
            ->orderBy('hoa.status', 'ASC')
            ->get();
    }

    /**
     * Application List Fetch (matching Drupal application_list_fetch)
     * Returns count of applications
     */
    private function applicationListFetch($entity, $status)
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('hoa.status', $status);

        if ($entity == 'new-apply') {
            $query->join('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hna.flat_type_id');
        } elseif ($entity == 'vs') {
            $query->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hva.flat_type_id');
        } elseif ($entity == 'cs') {
            $query->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hca.flat_type_id');
        }

        return $query->count();
    }

    /**
     * Pending App List Fetch Secy (matching Drupal pending_app_list_fetch_secy)
     */
    private function pendingAppListFetchSecy($status)
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->leftJoin('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
            ->where('hoa.status', $status);

        return $query->count();
    }

    /**
     * Occupant List Fetch (matching Drupal occupant_list_fetch)
     */
    private function occupantListFetch($uid)
    {
        // Get user details
        $userDetails = DB::table('users_details')
            ->where('uid', $uid)
            ->first();

        if (!$userDetails || empty($userDetails->division_id) || empty($userDetails->subdiv_id)) {
            return 0;
        }

        $query = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->join('users as u', 'u.uid', '=', 'haod.uid')
            ->where('hoa.status', 'existing_occupant')
            ->where('he.division_id', $userDetails->division_id);

        if ($userDetails->subdiv_id != 0) {
            $query->where('he.subdiv_id', $userDetails->subdiv_id);
        }

        return $query->count();
    }

    /**
     * Auto Cancellation Applicant List Fetch (matching Drupal auto_cancellation_applicant_list_fetch)
     */
    private function autoCancellationApplicantListFetch($uid)
    {
        // Get user details
        $userDetails = DB::table('users_details')
            ->where('uid', $uid)
            ->first();

        if (!$userDetails || empty($userDetails->division_id) || empty($userDetails->subdiv_id)) {
            return 0;
        }

        $query = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->whereIn('hoa.status', ['offer_letter_cancel', 'license_cancel', 'offer_letter_extended', 'license_extended'])
            ->where('he.division_id', $userDetails->division_id);

        if ($userDetails->subdiv_id != 0) {
            $query->where('he.subdiv_id', $userDetails->subdiv_id);
        }

        return $query->count();
    }

    /**
     * Fetch Without HRMS Data Count (matching Drupal fetch_withouthrms_data_count)
     */
    private function fetchWithoutHrmsDataCount()
    {
        return DB::table('housing_existing_occupant_draft')->count();
    }

    /**
     * Fetch Special Recommendation List Data (matching Drupal fetch_special_recommendation_list_data)
     */
    private function fetchSpecialRecommendationListData()
    {
        return DB::table('housing_special_recommended')->count();
    }

    /**
     * Get flat type wise waiting list counts for Housing Official (Role 6)
     */
    private function getFlatTypeCounts()
    {
        $counts = [];
        
        // Flat types: 1=A, 2=B, 3=C, 4=D, 5=A+
        $flatTypes = [
            1 => 'A',
            2 => 'B',
            3 => 'C',
            4 => 'D',
            5 => 'A+'
        ];

        foreach ($flatTypes as $flatTypeId => $flatTypeName) {
            $counts[$flatTypeName] = DB::table('housing_applicant as ha')
                ->join('housing_applicant_official_detail as haod', 'haod.housing_applicant_id', '=', 'ha.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hnaa.flat_type_id', '=', 'hft.flat_type_id')
                ->where('hoa.status', 'housingapprover_approved_1')
                ->where('hft.flat_type_id', $flatTypeId)
                ->count();
        }

        return $counts;
    }

    /**
     * Fetch HRMS User Data
     * For local: Returns dummy data
     * For live: Fetches from HRMS API and decrypts response
     */
    private function getHRMSUserData($hrmsId)
    {
        // ========== LOCAL DEVELOPMENT - DUMMY DATA ==========
        if (config('app.env') === 'local' || config('app.env') === 'development') {
            return [
                'hrmsId' => $hrmsId,
                'applicantName' => 'PRADIP KUMAR HANSDA',
                'dateOfBirth' => '15/04/1980',
                'dateOfJoining' => '10/06/2005',
                'dateOfRetirement' => '30/04/2040',
                'mobileNo' => '7278587028',
                'gender' => 'Male',
                'applicantDesignation' => 'Upper Division Assistant',
                'officeName' => 'PANCHAYATS & RURAL DEVELOPMENT DEPARTMENT',
                'ddoId' => 'CAFPNA001',
                'permanentStreet' => 'Flat No R-5/1,Bidhan Abasan',
                'permanentCityTownVillage' => 'F B Block,Sector-III',
                'permanentPostOffice' => 'Bidhannagar',
                'permanentPincode' => '700106',
                'permanentDistrictCode' => '5',
                'permanentPresentSame' => 'Y',
                'presentStreet' => 'Flat No R-5/1,Bidhan Abasan',
                'presentCityTownVillage' => 'F B Block,Sector-III',
                'presentPostOffice' => 'Bidhannagar',
                'presentPincode' => '700106',
                'presentDistrictCode' => '5',
                'guardianName' => 'Sri Nabin Chandra Hansda',
                'applicantHeadquarter' => 'L1-DEPARTMENT',
                'gradePay' => '3600',
                'payBandId' => '3',
                'payScaleId' => '',
                'applicantPostingPlace' => 'JOINT ADMINISTRATIVE BUILDING, 6TH - 10TH FLOOR, BLOCK HC-7 SECTOR 3 Salt Lake City BIDHANNAGAR IB MARKET SO Bidhannagar South 24 Parganas( North ) West Bengal',
                'payInThePayBand' => '10600',
                'officeStreetCharacter' => 'JOINT ADMINISTRATIVE BUILDING, 6TH - 10TH FLOOR, BLOCK HC-7 SECTOR 3 Salt Lake City BIDHANNAGAR IB MARKET SO Bidhannagar South 24 Parganas( North ) West Bengal',
                'officeCityTownVillage' => 'Salt Lake City',
                'officePostOffice' => 'BIDHANNAGAR IB MARKET SO',
                'officePinCode' => '700106',
                'officeDistrict' => '5',
                'officePhoneNo' => '',
                'email' => '',
            ];
        }
        // ========== END LOCAL DEVELOPMENT ==========

        // ========== LIVE PRODUCTION - HRMS API CALL ==========
        try {
            $hrmsApiUrl = config('services.hrms.api_url', 'https://uat.wbifms.gov.in/hrms-External/housing/fetchEmployeeDetails');
            
            $requestData = [
                'req' => [
                    'hrmsId' => $hrmsId
                ]
            ];

            $response = Http::timeout(30)
                ->withOptions([
                    'verify' => false,
                ])
                ->post($hrmsApiUrl, $requestData);

            if (!$response->successful()) {
                Log::error('HRMS API Error', [
                    'hrms_id' => $hrmsId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return $this->getDefaultData($hrmsId);
            }

            $responseData = $response->json();

            if (!isset($responseData['resp']['status']) || 
                strtolower($responseData['resp']['status']) !== 's' ||
                empty($responseData['resp']['data'])) {
                Log::error('HRMS API Invalid Response', [
                    'hrms_id' => $hrmsId,
                    'response' => $responseData
                ]);
                return $this->getDefaultData($hrmsId);
            }

            $encryptedData = $responseData['resp']['data'];
            $decryptedData = AuthEncryptionHelper::decrypt($encryptedData);
            $userDataArray = json_decode($decryptedData, true);
            
            if (empty($userDataArray) || !is_array($userDataArray) || empty($userDataArray[0])) {
                Log::error('HRMS Data Decryption Error', [
                    'hrms_id' => $hrmsId,
                    'decrypted_data' => $decryptedData
                ]);
                return $this->getDefaultData($hrmsId);
            }

            return $userDataArray[0];

        } catch (\Exception $e) {
            Log::error('HRMS User Data Fetch Error', [
                'hrms_id' => $hrmsId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->getDefaultData($hrmsId);
        }
        // ========== END LIVE PRODUCTION ==========
    }

    /**
     * Get default/fallback data when HRMS API fails
     */
    private function getDefaultData($hrmsId)
    {
        return [
            'hrmsId' => $hrmsId,
            'applicantName' => $hrmsId,
            'email' => 'N/A',
            'applicantDesignation' => 'N/A',
            'officeName' => 'N/A',
            'mobileNo' => 'N/A',
        ];
    }
}
