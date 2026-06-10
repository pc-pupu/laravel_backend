<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateLicenseController extends Controller
{
    /**
     * Get list of applications ready for license generation
     * GET /api/generate-license/list
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
                // ->join('housing_allotment_status_master as hsm', 'hsm.short_code', '=', 'hoa.status')
                ->join(
                    DB::raw('(
                        SELECT DISTINCT ON (short_code)
                            short_code,
                            status_id,
                            status_description,
                            applicant_show_status
                        FROM housing_allotment_status_master
                        ORDER BY short_code, status_id
                    ) as hsm'),
                    'hsm.short_code',
                    '=',
                    'hoa.status'
                )
                ->where('hoa.status', 'housingapprover_approved_2')
                ->where('haod.is_active', 1)
                ->select(
                    'ha.applicant_name',
                    'hoa.online_application_id',
                    'hd.district_code',
                    'hd.ddo_designation',
                    'hd.ddo_address',
                    'haod.applicant_designation',
                    'haod.applicant_headquarter',
                    'haod.applicant_posting_place',
                    'haod.uid',
                    'haod.pay_in_the_pay_band',
                    'haod.grade_pay',
                    'haod.gpf_no',
                    'haod.date_of_joining',
                    'haod.date_of_retirement',
                    'haod.office_name',
                    'haod.office_street',
                    'haod.office_city_town_village',
                    'haod.office_post_office',
                    'haod.office_pin_code',
                    'haod.office_district',
                    'haod.office_phone_no',
                    'haod.hrms_id',
                    'hoa.status',
                    'hoa.application_no',
                    'hoa.date_of_application',
                    'hoa.date_of_verified',
                    'hoa.computer_serial_no',
                    'hoa.is_backlog_applicant',
                    'hoa.uploaded_app_form'
                )
                ->orderBy('hoa.online_application_id', 'ASC');

            $applications = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $applications
            ]);

        } catch (\Exception $e) {
            Log::error('Get Generate License List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch applications',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Generate license for an application
     * POST /api/generate-license
     */
    public function generate(Request $request)
    {
        try {
            $request->validate([
                'online_application_id' => 'required|integer',
                'uid' => 'required|integer'
            ]);

            $appId = $request->online_application_id;
            $userId = $request->uid;

            DB::beginTransaction();

            // Fetch allotment details
            $allotmentDetails = DB::table('housing_online_application as hoa')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                ->join('housing_district as hd', 'hd.district_code', '=', 'he.district_code')
                ->where('hoa.online_application_id', $appId)
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
                    'status_code' => 404
                ], 404);
            }

            // Determine application type from application number
            $applicationType = 'New';
            if (strpos($allotmentDetails->application_no, 'VS') !== false) {
                $applicationType = 'Vertical Shifting';
            } elseif (strpos($allotmentDetails->application_no, 'CS') !== false) {
                $applicationType = 'Category Shifting';
            } elseif (strpos($allotmentDetails->application_no, 'PA') !== false) {
                $applicationType = 'Physical Application';
            } elseif (strpos($allotmentDetails->application_no, 'EO') !== false) {
                $applicationType = 'Existing Occupant';
            }

            // Insert into housing_license_application
            $licenseAppId = DB::table('housing_license_application')->insertGetId([
                'online_application_id' => $appId,
                'type_of_application' => $applicationType,
                'allotment_no' => $allotmentDetails->allotment_no,
                'allotment_date' => $allotmentDetails->allotment_date,
                'allotment_district' => $allotmentDetails->district_name,
                'allotment_estate' => $allotmentDetails->estate_name,
                'allotment_address' => $allotmentDetails->estate_address,
                'allotment_flat_id' => $allotmentDetails->flat_id,
            ], 'license_application_id');

            // Generate license number based on application type
            $licenseNo = '';
            switch ($applicationType) {
                case 'New':
                    $licenseNo = 'INL-' . $licenseAppId;
                    break;
                case 'Vertical Shifting':
                    $licenseNo = 'IVSL-' . $licenseAppId;
                    break;
                case 'Category Shifting':
                    $licenseNo = 'ICSL-' . $licenseAppId;
                    break;
                case 'Physical Application':
                    $licenseNo = 'IPAL-' . $licenseAppId;
                    break;
                default:
                    $licenseNo = 'INL-' . $licenseAppId;
            }

            // Insert into housing_occupant_license
            $licenseIssueDate = now()->format('Y-m-d');
            $licenseExpiryDate = now()->addYears(3)->subDay()->format('Y-m-d');

            DB::table('housing_occupant_license')->insert([
                'flat_occupant_id' => $allotmentDetails->flat_occupant_id,
                'license_application_id' => $licenseAppId,
                'license_issue_date' => $licenseIssueDate,
                'license_expiry_date' => $licenseExpiryDate,
                'license_no' => $licenseNo,
            ]);

            // Update application status to license_generate
            DB::table('housing_online_application')
                ->where('online_application_id', $appId)
                ->update(['status' => 'license_generate']);

            // Get status ID for license_generate
            $status = DB::table('housing_allotment_status_master')
                ->where('short_code', 'license_generate')
                ->first();

            if ($status) {
                // Insert into process flow
                DB::table('housing_process_flow')->insert([
                    'online_application_id' => $appId,
                    'status_id' => $status->status_id,
                    'created_at' => now(),
                    'uid' => $userId,
                    'short_code' => 'license_generate'
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "License {$licenseNo} is generated for Application No.={$allotmentDetails->application_no}",
                'data' => [
                    'license_no' => $licenseNo,
                    'application_no' => $allotmentDetails->application_no,
                    'license_issue_date' => $licenseIssueDate,
                    'license_expiry_date' => $licenseExpiryDate
                ],
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Generate License Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'License is not generated. Please try Again.',
                'status_code' => 500
            ], 500);
        }
    }
}
