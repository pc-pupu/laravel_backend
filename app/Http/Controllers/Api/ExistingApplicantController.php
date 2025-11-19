<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ExistingApplicantController extends Controller
{
    /**
     * List all existing applicants (legacy/physical applicants)
     */
    public function index(Request $request)
    {
        $query = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('hoa.application_no', 'LIKE', 'PA%')
            ->where('hoa.is_backlog_applicant', '=', 1);

        // Filter by HRMS ID presence
        if ($request->filled('has_hrms')) {
            if ($request->input('has_hrms') == '1') {
                $query->whereNotNull('haod.hrms_id');
            } else {
                $query->whereNull('haod.hrms_id');
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(ha.applicant_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(hoa.application_no) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(hoa.computer_serial_no) LIKE ?', ["%{$search}%"]);
            });
        }

        $query->select([
            'ha.housing_applicant_id',
            'ha.applicant_name',
            'ha.guardian_name',
            'ha.mobile_no',
            'ha.gender',
            'ha.present_street',
            'ha.present_city_town_village',
            'ha.present_post_office',
            'ha.present_pincode',
            'ha.present_district',
            'ha.permanent_street',
            'ha.permanent_city_town_village',
            'ha.permanent_post_office',
            'ha.permanent_pincode',
            'ha.permanent_district',
            'hoa.application_no',
            'hoa.date_of_application',
            'hoa.computer_serial_no',
            'hoa.online_application_id',
            'hoa.physical_application_no',
            'haod.hrms_id',
            'haod.uid'
        ]);

        // Order by computer serial no (cast to integer)
        $query->orderByRaw('CAST(hoa.computer_serial_no AS UNSIGNED) ASC');

        $perPage = (int) $request->input('per_page', 15);
        $applicants = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $applicants,
        ]);
    }

    /**
     * List applicants with HRMS ID
     */
    public function withHrms(Request $request)
    {
        $request->merge(['has_hrms' => '1']);
        return $this->index($request);
    }

    /**
     * List applicants without HRMS ID
     */
    public function withoutHrms(Request $request)
    {
        $request->merge(['has_hrms' => '0']);
        return $this->index($request);
    }

    /**
     * Search by physical application no or computer serial no
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search_type' => 'required|in:physical_app_no,computer_serial_no',
            'search_value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid search parameters.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = DB::table('housing_online_application as hoa')
            ->select('hoa.application_no', 'hoa.online_application_id');

        if ($request->input('search_type') === 'physical_app_no') {
            $query->where('hoa.physical_application_no', $request->input('search_value'));
        } else {
            $query->where('hoa.computer_serial_no', $request->input('search_value'));
        }

        $result = $query->first();

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'No matching application found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Get applicant details
     */
    public function show($id)
    {
        $applicant = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->leftJoin('users as u', 'u.uid', '=', 'haod.uid')
            ->where('hoa.online_application_id', $id)
            ->where('hoa.application_no', 'LIKE', 'PA%')
            ->select([
                'ha.*',
                'haod.*',
                'hoa.*',
                'u.mail as email',
                'u.status as user_status'
            ])
            ->first();

        if (!$applicant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Applicant not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $applicant,
        ]);
    }

    /**
     * Create new existing applicant
     */
    public function store(Request $request)
    {
        // This is a complex operation that involves multiple tables
        // For now, return a placeholder - full implementation will require
        // creating user, housing_applicant, housing_applicant_official_detail,
        // housing_online_application, and housing_new_allotment_application records
        
        return response()->json([
            'status' => 'error',
            'message' => 'Create functionality to be implemented based on Drupal form submission logic.',
        ], 501);
    }

    /**
     * Update existing applicant
     */
    public function update(Request $request, $id)
    {
        // Update logic similar to existing_applicant_edit_form_submit in Drupal
        // This involves updating multiple tables
        
        return response()->json([
            'status' => 'error',
            'message' => 'Update functionality to be implemented based on Drupal edit form logic.',
        ], 501);
    }

    /**
     * Accept declaration for HRMS ID update
     */
    public function acceptDeclaration(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'hrms_id' => 'required|string',
            'uid' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid parameters.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update HRMS ID and UID in housing_applicant_official_detail
        // Update UID in housing_applicant
        // Deactivate old user account
        
        DB::beginTransaction();
        try {
            $officialDetail = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->where('hoa.online_application_id', $id)
                ->select('haod.applicant_official_detail_id', 'haod.housing_applicant_id', 'haod.uid as old_uid')
                ->first();

            if (!$officialDetail) {
                throw new \Exception('Applicant not found.');
            }

            // Update official detail
            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $officialDetail->applicant_official_detail_id)
                ->update([
                    'hrms_id' => $request->input('hrms_id'),
                    'uid' => $request->input('uid'),
                ]);

            // Update applicant
            DB::table('housing_applicant')
                ->where('housing_applicant_id', $officialDetail->housing_applicant_id)
                ->update(['uid' => $request->input('uid')]);

            // Deactivate old user
            DB::table('users')
                ->where('uid', $officialDetail->old_uid)
                ->update(['status' => 0]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'HRMS ID updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

