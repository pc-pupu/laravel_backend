<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ExistingOccupantController extends Controller
{
    /**
     * List all existing occupants
     */
    public function index(Request $request)
    {
        $query = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->leftJoin('users as u', 'u.uid', '=', 'haod.uid')
            ->where('hoa.status', '=', 'existing_occupant')
            ->where('hoa.is_backlog_applicant', '=', 2);

        // Filter by HRMS ID presence
        // Note: null, 0, and blank are all considered as "null data" for hrms_id
        if ($request->filled('has_hrms')) {
            if ($request->input('has_hrms') == '1') {
                // Has HRMS ID: not null, not 0, not blank
                $query->whereNotNull('haod.hrms_id')
                    ->where('haod.hrms_id', '!=', 0)
                    ->whereRaw("CAST(haod.hrms_id AS TEXT) != ''")
                    ->whereRaw("TRIM(CAST(haod.hrms_id AS TEXT)) != ''");
            } else {
                // No HRMS ID: null, 0, or blank
                $query->where(function($q) {
                    $q->whereNull('haod.hrms_id')
                        ->orWhere('haod.hrms_id', 0)
                        ->orWhereRaw("CAST(haod.hrms_id AS TEXT) = ''")
                        ->orWhereRaw("TRIM(CAST(haod.hrms_id AS TEXT)) = ''");
                });
            }
        }

        // Filter by division/subdivision if user has access
        if ($request->filled('division_id')) {
            $query->where('he.division_id', $request->input('division_id'));
        }
        if ($request->filled('subdiv_id')) {
            $query->where('he.subdiv_id', $request->input('subdiv_id'));
        }

        // Search
        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(ha.applicant_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(haod.hrms_id) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(he.estate_name) LIKE ?', ["%{$search}%"]);
            });
        }

        $query->select([
            'hoa.online_application_id',
            'ha.applicant_name',
            'he.estate_name',
            'hft.flat_type',
            'haod.hrms_id',
            'u.status as user_status',
            'haod.uid',
            'hf.flat_id',
            'hf.flat_no',
            'hf.floor',
        ]);

        $query->orderBy('hf.flat_id', 'ASC');

        $perPage = (int) $request->input('per_page', 15);
        $occupants = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $occupants,
        ]);
    }

    /**
     * List occupants with HRMS ID
     */
    public function withHrms(Request $request)
    {
        $request->merge(['has_hrms' => '1']);
        return $this->index($request);
    }

    /**
     * List occupants without HRMS ID
     */
    public function withoutHrms(Request $request)
    {
        $request->merge(['has_hrms' => '0']);
        return $this->index($request);
    }

    /**
     * Get occupant by flat ID
     */
    public function getByFlat($flatId)
    {
        $occupant = DB::table('housing_flat_occupant as hfo')
            ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hfo.online_application_id')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->where('hfo.flat_id', $flatId)
            ->where('hoa.status', '=', 'existing_occupant')
            ->select('hfo.*', 'hoa.*', 'ha.*', 'haod.*')
            ->first();

        if (!$occupant) {
            return response()->json([
                'status' => 'error',
                'message' => 'No occupant found for this flat.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $occupant,
        ]);
    }

    /**
     * Get occupant details
     */
    public function show($id)
    {
        $occupant = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->leftJoin('housing_district as hdis', 'hdis.district_code', '=', 'hd.district_code')
            ->leftJoin('housing_occupant_license as hol', 'hol.flat_occupant_id', '=', 'hfo.flat_occupant_id')
            ->leftJoin('users as u', 'u.uid', '=', 'haod.uid')
            ->leftJoin('housing_district as hdis_present', 'hdis_present.district_code', '=', 'ha.present_district')
            ->leftJoin('housing_district as hdis_permanent', 'hdis_permanent.district_code', '=', 'ha.permanent_district')
            ->leftJoin('housing_district as hdis_office', 'hdis_office.district_code', '=', 'haod.office_district')
            ->where('haod.uid', $id)
            ->where('hoa.status', '=', 'existing_occupant')
            ->select([
                'ha.*',
                'haod.*',
                'hoa.application_no',
                'hoa.date_of_application',
                'hf.flat_no',
                'hf.floor',
                'he.estate_name',
                'hft.flat_type',
                'hb.block_name',
                'hd.ddo_designation',
                'hd.ddo_address',
                'hdis.district_name',
                'hol.license_issue_date',
                'hol.license_expiry_date',
                'hol.existing_occupant_license_no',
                'hol.authorised_or_not',
                'u.mail as email',
                'u.status as user_status',
                'hdis_present.district_name as present_district_name',
                'hdis_permanent.district_name as permanent_district_name',
                'hdis_office.district_name as office_district_name',
            ])
            ->first();

        if (!$occupant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Occupant not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $occupant,
        ]);
    }

    /**
     * Check if flat has occupant
     */
    public function checkFlatOccupancy($flatId)
    {
        $hasOccupant = DB::table('housing_flat_occupant')
            ->where('flat_id', $flatId)
            ->exists();

        $hasDraft = DB::table('housing_existing_occupant_draft')
            ->where('flat_id', $flatId)
            ->exists();

        return response()->json([
            'status' => 'success',
            'data' => [
                'has_occupant' => $hasOccupant,
                'has_draft' => $hasDraft,
                'is_available' => !$hasOccupant && !$hasDraft,
            ],
        ]);
    }

    /**
     * Get flat details for occupant entry
     */
    public function getFlatDetails($flatId)
    {
        $flat = DB::table('housing_flat as hf')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_district as hd', 'hd.district_code', '=', 'he.district_code')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->join('housing_flat_status as hfs', 'hfs.flat_status_id', '=', 'hf.flat_status_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->where('hf.flat_id', $flatId)
            ->select([
                'hf.flat_id',
                'hf.flat_no',
                'hf.estate_id',
                'hf.flat_type_id',
                'hf.block_id',
                'hf.floor',
                'hf.flat_status_id',
                'he.estate_name',
                'he.estate_address',
                'hd.district_name',
                'hb.block_name',
                'hfs.flat_status',
                'hft.flat_type',
            ])
            ->first();

        if (!$flat) {
            return response()->json([
                'status' => 'error',
                'message' => 'Flat not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $flat,
        ]);
    }

    /**
     * Create new existing occupant
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'flat_id' => 'required|integer|exists:housing_flat,flat_id',
            'occupant_name' => 'required|string|max:255',
            'occupant_father_name' => 'required|string|max:255',
            'hrms_id' => 'required|string|max:10',
            'email' => 'required|email',
            'mobile' => 'required|string|max:10',
            'dob' => 'required|string',
            'gender' => 'required|in:M,F',
            'occupant_designation' => 'required|string',
            'pay_band' => 'required|integer',
            'pay_in' => 'required|numeric',
            'doj' => 'required|string',
            'dor' => 'required|string',
            'ddo_id' => 'required|integer',
            'license_no' => 'required|string',
            'dol' => 'required|string', // Date of license
            'authorised_or_not' => 'required|string',
            'permanent_street' => 'required|string',
            'permanent_city_town_village' => 'required|string',
            'permanent_post_office' => 'required|string',
            'permanent_district' => 'required|string',
            'permanent_pincode' => 'required|string|max:6',
            'present_street' => 'required|string',
            'present_city_town_village' => 'required|string',
            'present_post_office' => 'required|string',
            'present_district' => 'required|string',
            'present_pincode' => 'required|string|max:6',
            'office_name' => 'required|string',
            'office_street' => 'required|string',
            'office_city' => 'required|string',
            'office_post_office' => 'required|string',
            'office_district' => 'required|string',
            'office_pincode' => 'required|string|max:6',
            'office_phone_no' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if flat already has occupant
        $hasOccupant = DB::table('housing_flat_occupant')
            ->where('flat_id', $request->input('flat_id'))
            ->exists();

        if ($hasOccupant) {
            return response()->json([
                'status' => 'error',
                'message' => 'This flat already has an occupant.',
            ], 422);
        }

        // Check if HRMS ID already exists
        if ($request->filled('hrms_id')) {
            $hrmsExists = DB::table('housing_applicant_official_detail')
                ->where('hrms_id', $request->input('hrms_id'))
                ->exists();

            if ($hrmsExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This Employee HRMS ID already exists.',
                ], 422);
            }
        }

        // Check if email already exists
        $emailExists = DB::table('users')
            ->where('mail', $request->input('email'))
            ->exists();

        if ($emailExists) {
            return response()->json([
                'status' => 'error',
                'message' => 'This email address already exists.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $username = trim($request->input('hrms_id'));
            
            // Check if user already exists
            $existingUser = DB::table('users')
                ->where('name', $username)
                ->first();

            if ($existingUser) {
                throw new \Exception('This user already exists.');
            }

            // 1. Create user
            $userId = DB::table('users')->insertGetId([
                'name' => $username,
                'password' => bcrypt($username), // Password same as username
                'password_old' => bcrypt($username),
                'mail' => strtolower(trim($request->input('email'))),
                'status' => 0, // Inactive until approved
                'new_pass_set' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'uid');

            // 2. Assign role (5 = existing occupant)
            DB::table('user_role')->insert([
                'uid' => $userId,
                'rid' => 5,
            ]);

            // 3. Insert into housing_applicant
            $dobFormatted = $this->formatDate($request->input('dob'));
            $applicantId = DB::table('housing_applicant')->insertGetId([
                'uid' => $userId,
                'applicant_name' => strtoupper(trim($request->input('occupant_name'))),
                'guardian_name' => strtoupper(trim($request->input('occupant_father_name'))),
                'date_of_birth' => $dobFormatted ?: '1900-01-01',
                'gender' => $request->input('gender'),
                'mobile_no' => trim($request->input('mobile')),
                'permanent_street' => strtoupper(trim($request->input('permanent_street'))),
                'permanent_city_town_village' => strtoupper(trim($request->input('permanent_city_town_village'))),
                'permanent_post_office' => strtoupper(trim($request->input('permanent_post_office'))),
                'permanent_district' => trim($request->input('permanent_district')),
                'permanent_pincode' => trim($request->input('permanent_pincode')),
                'present_street' => strtoupper(trim($request->input('present_street'))),
                'present_city_town_village' => strtoupper(trim($request->input('present_city_town_village'))),
                'present_post_office' => strtoupper(trim($request->input('present_post_office'))),
                'present_district' => trim($request->input('present_district')),
                'present_pincode' => trim($request->input('present_pincode')),
            ]);

            // 4. Insert into housing_applicant_official_detail
            $dojFormatted = $this->formatDate($request->input('doj'));
            $dorFormatted = $this->formatDate($request->input('dor'));
            
            $officialDetailId = DB::table('housing_applicant_official_detail')->insertGetId([
                'uid' => $userId,
                'housing_applicant_id' => $applicantId,
                'ddo_id' => $request->input('ddo_id'),
                'hrms_id' => trim($request->input('hrms_id')),
                'applicant_designation' => strtoupper(trim($request->input('occupant_designation'))),
                'applicant_posting_place' => strtoupper(trim($request->input('occupant_posting_place', ''))),
                'applicant_headquarter' => strtoupper(trim($request->input('occupant_headquarter', ''))),
                'pay_band_id' => $request->input('pay_band'),
                'pay_in_the_pay_band' => trim($request->input('pay_in')),
                'date_of_joining' => $dojFormatted,
                'date_of_retirement' => $dorFormatted,
                'office_name' => strtoupper(trim($request->input('office_name'))),
                'office_street' => strtoupper(trim($request->input('office_street'))),
                'office_city_town_village' => strtoupper(trim($request->input('office_city'))),
                'office_post_office' => strtoupper(trim($request->input('office_post_office'))),
                'office_district' => trim($request->input('office_district')),
                'office_pin_code' => trim($request->input('office_pincode')),
                'office_phone_no' => trim($request->input('office_phone_no', '')),
            ]);

            // 5. Insert into housing_online_application
            $onlineAppId = DB::table('housing_online_application')->insertGetId([
                'applicant_official_detail_id' => $officialDetailId,
                'status' => 'existing_occupant',
                'date_of_application' => null,
                'is_backlog_applicant' => 2,
            ]);

            // 6. Generate application number
            $applicationNo = 'EO-' . date('dmY') . '-' . $onlineAppId;
            DB::table('housing_online_application')
                ->where('online_application_id', $onlineAppId)
                ->update(['application_no' => $applicationNo]);

            // 7. Get flat_type_id from flat
            $flat = DB::table('housing_flat')
                ->where('flat_id', $request->input('flat_id'))
                ->first();

            if (!$flat) {
                throw new \Exception('Flat not found.');
            }

            // 8. Insert into housing_new_allotment_application
            DB::table('housing_new_allotment_application')->insert([
                'online_application_id' => $onlineAppId,
                'flat_type_id' => $flat->flat_type_id,
            ]);

            // 9. Update flat status to occupied (2)
            DB::table('housing_flat')
                ->where('flat_id', $request->input('flat_id'))
                ->update(['flat_status_id' => 2]);

            // 10. Insert into housing_flat_occupant
            $flatOccupantId = DB::table('housing_flat_occupant')->insertGetId([
                'online_application_id' => $onlineAppId,
                'flat_id' => $request->input('flat_id'),
                'allotment_date' => null,
            ]);

            // 11. Insert into housing_occupant_license
            $dolFormatted = $this->formatDate($request->input('dol'));
            $licenseExpiry = $dolFormatted ? date('Y-m-d', strtotime($dolFormatted . '+3 years -1 day')) : null;

            DB::table('housing_occupant_license')->insert([
                'flat_occupant_id' => $flatOccupantId,
                'license_issue_date' => $dolFormatted,
                'license_expiry_date' => $licenseExpiry,
                'existing_occupant_license_no' => trim($request->input('license_no')),
                'authorised_or_not' => trim($request->input('authorised_or_not')),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Occupant data inserted successfully.',
                'data' => [
                    'online_application_id' => $onlineAppId,
                    'application_no' => $applicationNo,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update existing occupant
     */
    public function update(Request $request, $id)
    {
        // Update logic - similar to store but for existing records
        // This is complex and should be implemented based on existing_occupant_edit_form_submit
        return response()->json([
            'status' => 'error',
            'message' => 'Update functionality to be fully implemented based on Drupal edit form logic.',
        ], 501);
    }

    /**
     * Delete existing occupant
     */
    public function destroy($id)
    {
        // Delete logic - remove from multiple tables
        // Based on rhe_wise_flat_occupant_delete function
        DB::beginTransaction();
        try {
            $occupant = DB::table('housing_flat_occupant as hfo')
                ->join('housing_online_application as hoa', 'hoa.online_application_id', '=', 'hfo.online_application_id')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->where('haod.uid', $id)
                ->select('hfo.flat_id', 'haod.uid')
                ->first();

            if (!$occupant) {
                throw new \Exception('Occupant not found.');
            }

            // Delete from multiple tables
            DB::table('housing_applicant')->where('uid', $occupant->uid)->delete();
            DB::table('housing_applicant_official_detail')->where('uid', $occupant->uid)->delete();
            DB::table('user_role')->where('uid', $occupant->uid)->delete();
            DB::table('users')->where('uid', $occupant->uid)->delete();

            // Update flat status back to available (1)
            DB::table('housing_flat')
                ->where('flat_id', $occupant->flat_id)
                ->update(['flat_status_id' => 1]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Occupant data removed successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format date from DD/MM/YYYY to YYYY-MM-DD
     */
    private function formatDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            $parts = explode('/', $dateString);
            if (count($parts) === 3) {
                return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get RHE list (filtered by user's division/subdivision if applicable)
     */
    public function getRheList(Request $request)
    {
        $query = DB::table('housing_estate as he')
            ->select('he.estate_id', 'he.estate_name', 'he.estate_address')
            ->orderBy('he.estate_name', 'ASC');

        // Filter by division/subdivision if provided
        if ($request->filled('division_id')) {
            $query->where('he.division_id', $request->input('division_id'));
        }
        if ($request->filled('subdiv_id')) {
            $query->where('he.subdiv_id', $request->input('subdiv_id'));
        }

        $estates = $query->get();

        $options = [];
        foreach ($estates as $estate) {
            $label = $estate->estate_name;
            if ($estate->estate_address) {
                $label .= ' | ' . $estate->estate_address;
            }
            $options[] = [
                'value' => $estate->estate_id,
                'label' => $label,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $options,
        ]);
    }

    /**
     * Get flat types for a specific RHE
     */
    public function getFlatTypes($rheId)
    {
        $flatTypes = DB::table('housing_flat as hf')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->where('hf.estate_id', $rheId)
            ->select('hf.flat_type_id', 'hft.flat_type')
            ->groupBy('hf.flat_type_id', 'hft.flat_type')
            ->orderBy('hft.flat_type', 'ASC')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $flatTypes,
        ]);
    }

    /**
     * Get blocks for a specific RHE and flat type
     */
    public function getBlocks($rheId, $flatTypeId)
    {
        $blocks = DB::table('housing_flat as hf')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
            ->where('hf.estate_id', $rheId)
            ->where('hf.flat_type_id', $flatTypeId)
            ->select('hf.block_id', 'hb.block_name')
            ->groupBy('hf.block_id', 'hb.block_name')
            ->orderBy('hb.block_name', 'ASC')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $blocks,
        ]);
    }

    /**
     * Get available flats for a specific RHE, flat type, and block
     */
    public function getAvailableFlats($rheId, $flatTypeId, $blockId)
    {
        $flats = DB::table('housing_flat as hf')
            ->leftJoin('housing_flat_occupant as hfo', 'hfo.flat_id', '=', 'hf.flat_id')
            ->leftJoin('housing_existing_occupant_draft as heod', 'heod.flat_id', '=', 'hf.flat_id')
            ->where('hf.estate_id', $rheId)
            ->where('hf.flat_type_id', $flatTypeId)
            ->where('hf.block_id', $blockId)
            ->whereNull('hfo.flat_id')
            ->whereNull('heod.flat_id')
            ->select('hf.flat_id', 'hf.flat_no')
            ->orderBy('hf.flat_id', 'ASC')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $flats,
        ]);
    }

    /**
     * Get district list
     */
    public function getDistricts()
    {
        $districts = DB::table('housing_district')
            ->select('district_code', 'district_name')
            ->orderBy('district_name', 'ASC')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $districts,
        ]);
    }

    /**
     * Get DDO list (filtered by district if provided)
     */
    public function getDdoList(Request $request)
    {
        $query = DB::table('housing_ddo as hd')
            ->join('housing_district as hdis', 'hdis.district_code', '=', 'hd.district_code')
            ->where('hd.is_active', 'Y')
            ->select('hd.ddo_id', 'hd.ddo_designation', 'hd.ddo_address', 'hdis.district_name');

        if ($request->filled('district_code')) {
            $query->where('hd.district_code', $request->input('district_code'));
        }

        $ddos = $query->orderBy('hd.ddo_designation', 'ASC')->get();

        return response()->json([
            'status' => 'success',
            'data' => $ddos,
        ]);
    }

    /**
     * Get pay band list (filtered by flat type if provided)
     */
    public function getPayBands(Request $request)
    {
        $query = DB::table('housing_pay_band as hpb');

        if ($request->filled('flat_type_id')) {
            $query->join('housing_pay_band_categories as hpbc', 'hpbc.pay_band_id', '=', 'hpb.pay_band_id')
                ->where('hpbc.flat_type_id', $request->input('flat_type_id'));
        }

        $payBands = $query->select('hpb.pay_band_id', 'hpb.payband', 'hpb.scale_from', 'hpb.scale_to', 'hpb.grade_pay_from', 'hpb.grade_pay_to')
            ->orderBy('hpb.scale_from', 'ASC')
            ->get();

        // Format pay band labels
        $formatted = [];
        foreach ($payBands as $pb) {
            if ($pb->scale_from == 0 && $pb->scale_to != 0) {
                $label = $pb->payband . ' (Up to Rs ' . $pb->scale_to . '/-)';
            } elseif ($pb->scale_from != 0 && $pb->scale_to == 0) {
                if ($pb->grade_pay_from == 0 && $pb->grade_pay_to != 0) {
                    $label = $pb->payband . ' (Rs ' . $pb->scale_from . '/- and above & GP Up to Rs. ' . $pb->grade_pay_to . '/-)';
                } elseif ($pb->grade_pay_from != 0 && $pb->grade_pay_to == 0) {
                    $label = $pb->payband . ' (Rs ' . $pb->scale_from . '/- and above & GP Rs. ' . $pb->grade_pay_from . '/- and above)';
                } else {
                    $label = $pb->payband . ' (Rs ' . $pb->scale_from . '/- and above)';
                }
            } else {
                $label = $pb->payband . ' (Rs ' . $pb->scale_from . '/- to Rs ' . $pb->scale_to . '/-)';
            }

            $formatted[] = [
                'value' => $pb->pay_band_id,
                'label' => $label,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }

    /**
     * List draft occupants (without HRMS ID)
     */
    public function listDrafts(Request $request)
    {
        $query = DB::table('housing_existing_occupant_draft as heod')
            ->join('housing_flat as hf', 'hf.flat_id', '=', 'heod.flat_id')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
            ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id');

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(heod.applicant_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(he.estate_name) LIKE ?', ["%{$search}%"]);
            });
        }

        $query->select([
            'heod.*',
            'hf.flat_no',
            'hf.floor',
            'he.estate_name',
            'hft.flat_type',
            'hb.block_name',
        ]);

        $query->orderBy('heod.housing_existing_occupant_draft_id', 'DESC');

        $perPage = (int) $request->input('per_page', 15);
        $drafts = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $drafts,
        ]);
    }
}

