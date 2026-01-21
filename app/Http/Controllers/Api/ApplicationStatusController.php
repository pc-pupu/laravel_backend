<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\ProcessFlowService;

class ApplicationStatusController extends Controller
{
    /**
     * Get application status history by application number
     * GET /api/application-status/{application_no}
     */
    public function getStatusHistory(Request $request, $applicationNo)
    {
        try {
            $uid = $request->input('uid'); // Optional: for user-specific filtering

            $query = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->leftJoin('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
                ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->leftJoin('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
                ->leftJoin('housing_process_flow as hpf', 'hpf.online_application_id', '=', 'hoa.online_application_id')
                ->leftJoin('housing_allotment_status_master as hasm', 'hasm.short_code', '=', 'hpf.short_code')
                ->where('hoa.application_no', $applicationNo);

            if ($uid) {
                $query->where('haod.uid', $uid);
            }

            $statusHistory = $query->select(
                'hoa.online_application_id',
                'hoa.application_no',
                'haod.uid',
                'hoa.status',
                'hoa.date_of_application',
                'hoa.date_of_verified',
                'hoa.computer_serial_no',
                'hpf.short_code',
                'hpf.created_at',
                'hasm.status_description'
            )
            ->orderBy('hpf.created_at', 'ASC')
            ->get();

            if ($statusHistory->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No application found with the given application number',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $statusHistory,
            ]);

        } catch (\Exception $e) {
            Log::error('Application Status History Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch application status history',
            ], 500);
        }
    }

    /**
     * Search application by application number or HRMS ID
     * POST /api/application-status-check/search
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search_type' => 'required|in:1,2', // 1 = Application Number, 2 = HRMS ID
            'search_value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $searchType = $request->input('search_type');
            $searchValue = trim($request->input('search_value'));

            if ($searchType == 1) {
                // Search by application number
                $application = DB::table('housing_online_application as hoa')
                    ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                    ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                    ->join('housing_allotment_status_master as hasm', 'hasm.short_code', '=', 'hoa.status')
                    ->leftJoin('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
                    ->leftJoin('housing_occupant_license as hol', 'hol.license_application_id', '=', 'hla.license_application_id')
                    ->where('hoa.application_no', $searchValue)
                    ->select(
                        'ha.applicant_name',
                        'hoa.application_no',
                        'hoa.online_application_id',
                        'haod.applicant_official_detail_id',
                        'hoa.status',
                        'hoa.date_of_verified',
                        'hasm.status_description',
                        'hol.license_no',
                        'hol.possession_date',
                        'haod.uid',
                        'hol.release_date'
                    )
                    ->first();
            } else {
                // Search by HRMS ID
                if (!is_numeric($searchValue)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid HRMS ID. HRMS ID must be numeric.',
                    ], 422);
                }

                $application = DB::table('housing_online_application as hoa')
                    ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                    ->join('housing_applicant as ha', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
                    ->join('housing_allotment_status_master as hasm', 'hasm.short_code', '=', 'hoa.status')
                    ->leftJoin('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
                    ->leftJoin('housing_occupant_license as hol', 'hol.license_application_id', '=', 'hla.license_application_id')
                    ->where('haod.hrms_id', $searchValue)
                    ->select(
                        'ha.applicant_name',
                        'hoa.application_no',
                        'hoa.online_application_id',
                        'haod.applicant_official_detail_id',
                        'hoa.status',
                        'hoa.date_of_verified',
                        'hasm.status_description',
                        'hol.license_no',
                        'hol.possession_date',
                        'haod.uid',
                        'hol.release_date'
                    )
                    ->first();
            }

            if (!$application) {
                return response()->json([
                    'status' => 'error',
                    'message' => $searchType == 1 
                        ? 'Invalid Application Number. Check Application Number once again.' 
                        : 'Invalid HRMS ID. Check HRMS ID once again.',
                ], 404);
            }

            // Return in the format expected by frontend
            return response()->json([
                'status' => 'success',
                'data' => [
                    'application' => $application,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Application Status Check Search Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to search application',
            ], 500);
        }
    }

    /**
     * Get application detail for viewing
     * GET /api/application-status-check/{id}
     */
    public function getApplicationDetail(Request $request, $id)
    {
        try {
            $application = $this->fetchApplicationDetail($id);

            if (!$application) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Application not found',
                ], 404);
            }

            // Get estate preferences
            $estatePreferences = DB::table('housing_new_application_estate_preferences as hnaep')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hnaep.estate_id')
                ->where('hnaep.online_application_id', $id)
                ->whereNotNull('hnaep.estate_id')
                ->orderBy('hnaep.preference_order', 'ASC')
                ->select('he.estate_name', 'hnaep.preference_order')
                ->get();

            // Get allotment details if available
            $allotmentDetails = null;
            $status = $application->status ?? '';
            $allowedStatuses = [
                'allotted', 'housing_official_approved', 'housing_official_reject',
                'offer_letter_generate', 'applicant_acceptance', 'applicant_reject',
                'ddo_verified_2', 'ddo_rejected_2', 'housing_sup_approved_2',
                'housing_sup_reject_2', 'license_generate', 'existing_occupant',
                'applied', 'offer_letter_cancel', 'license_cancel'
            ];

            if (in_array($status, $allowedStatuses)) {
                $allotmentDetails = DB::table('housing_online_application as hoa')
                    ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                    ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                    ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                    ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                    ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
                    ->join('housing_block as hb', 'hb.block_id', '=', 'hf.block_id')
                    ->leftJoin('housing_license_application as hla', 'hla.online_application_id', '=', 'hoa.online_application_id')
                    ->leftJoin('housing_occupant_license as hol', 'hol.license_application_id', '=', 'hla.license_application_id')
                    ->where('hoa.online_application_id', $id)
                    ->select(
                        'he.estate_name',
                        'he.estate_address',
                        'hft.flat_type',
                        'hf.floor',
                        'hf.flat_no',
                        'hb.block_name',
                        'hol.possession_date',
                        'hol.license_no',
                    )
                    ->first();
            }

            // Format addresses
            $application->present_address = $this->formatAddress(
                $application->present_street ?? '',
                $application->present_city_town_village ?? '',
                $application->present_post_office ?? '',
                $application->present_district_name ?? '',
                $application->present_pincode ?? ''
            );

            $application->permanent_address = $this->formatAddress(
                $application->permanent_street ?? '',
                $application->permanent_city_town_village ?? '',
                $application->permanent_post_office ?? '',
                $application->permanent_district_name ?? '',
                $application->permanent_pincode ?? ''
            );

            $application->office_address = $this->formatAddress(
                $application->office_street ?? '',
                $application->office_city_town_village ?? '',
                $application->office_post_office ?? '',
                $application->office_district_name ?? '',
                $application->office_pin_code ?? ''
            );

            // Determine application type
            $applicationNo = $application->application_no ?? '';
            $application->application_type = $this->getApplicationType($applicationNo);

            // Format gender
            if (isset($application->gender)) {
                $application->gender = $application->gender === 'M' ? 'Male' : ($application->gender === 'F' ? 'Female' : $application->gender);
            }

            // Format pay band
            $application->pay_band_display = $this->formatPayBand(
                $application->flat_type ?? '',
                $application->scale_from ?? 0,
                $application->scale_to ?? 0
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'application' => $application,
                    'estate_preferences' => $estatePreferences,
                    'allotment_details' => $allotmentDetails,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Application Detail Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch application detail',
            ], 500);
        }
    }

    /**
     * Add possession date
     * POST /api/application-status-check/{id}/add-possession
     */
    public function addPossessionDate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'possession_date' => 'required|date_format:d/m/Y',
            'uid' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $possessionDate = \Carbon\Carbon::createFromFormat('d/m/Y', $request->input('possession_date'))->format('Y-m-d');
            $uid = $request->input('uid');

            // Get occupant license details
            $licenseData = DB::table('housing_occupant_license as hol')
                ->join('housing_license_application as hla', 'hla.license_application_id', '=', 'hol.license_application_id')
                ->join('housing_flat_occupant as hfo', 'hfo.flat_occupant_id', '=', 'hol.flat_occupant_id')
                ->where('hla.online_application_id', $id)
                ->select('hol.occupant_license_id', 'hfo.flat_id')
                ->first();

            if (!$licenseData) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'License data not found for this application',
                ], 404);
            }

            // Update application status
            DB::table('housing_online_application')
                ->where('online_application_id', $id)
                ->update([
                    'status' => 'flat_possession_taken',
                    'date_of_verified' => now(),
                ]);

            // Update occupant license
            DB::table('housing_occupant_license')
                ->where('occupant_license_id', $licenseData->occupant_license_id)
                ->update([
                    'possession_date' => $possessionDate,
                    'authorised_or_not' => 'authorised',
                ]);

            // Update flat status to occupied
            DB::table('housing_flat')
                ->where('flat_id', $licenseData->flat_id)
                ->update(['flat_status_id' => 2]); // 2 = Occupied

            // Insert into process flow
            ProcessFlowService::insertProcessFlow($id, 'flat_possession_taken', $uid);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Possession Date Added Successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Add Possession Date Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add possession date',
            ], 500);
        }
    }

    /**
     * Add flat release date
     * POST /api/application-status-check/{id}/add-release-date
     */
    public function addReleaseDate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'release_date' => 'required|date_format:d/m/Y',
            'uid' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $releaseDate = \Carbon\Carbon::createFromFormat('d/m/Y', $request->input('release_date'))->format('Y-m-d');
            $uid = $request->input('uid');

            // Get occupant license details
            $licenseData = DB::table('housing_occupant_license as hol')
                ->join('housing_license_application as hla', 'hla.license_application_id', '=', 'hol.license_application_id')
                ->where('hla.online_application_id', $id)
                ->select('hol.occupant_license_id')
                ->first();

            if (!$licenseData) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'License data not found for this application',
                ], 404);
            }

            // Update occupant license
            DB::table('housing_occupant_license')
                ->where('occupant_license_id', $licenseData->occupant_license_id)
                ->update(['release_date' => $releaseDate]);

            // Update application status
            DB::table('housing_online_application')
                ->where('online_application_id', $id)
                ->update([
                    'status' => 'flat_released',
                    'date_of_verified' => now(),
                ]);

            // Insert into process flow
            ProcessFlowService::insertProcessFlow($id, 'flat_released', $uid);

            // Deactivate official detail and free flat
            $officialDetail = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->leftJoin('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->where('hoa.online_application_id', $id)
                ->select('haod.applicant_official_detail_id', 'hfo.flat_id')
                ->first();

            if ($officialDetail) {
                DB::table('housing_applicant_official_detail')
                    ->where('applicant_official_detail_id', $officialDetail->applicant_official_detail_id)
                    ->update(['is_active' => 0]);

                if ($officialDetail->flat_id) {
                    DB::table('housing_flat')
                        ->where('flat_id', $officialDetail->flat_id)
                        ->update(['flat_status_id' => 9]); // 9 = Vacant due to release
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Release Date Added Successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Add Release Date Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add release date',
            ], 500);
        }
    }

    /**
     * Request license extension
     * POST /api/application-status-check/{id}/request-license-extension
     */
    public function requestLicenseExtension(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'extension_reason' => 'required|string|in:Reason_1,Reason_2',
            'extension_date' => 'required|date_format:d/m/Y',
            'uid' => 'required|integer',
            'official_detail_id' => 'required|integer',
            'document' => 'nullable|file|mimes:pdf|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $extensionDate = \Carbon\Carbon::createFromFormat('d/m/Y', $request->input('extension_date'))->format('Y-m-d');
            $uid = $request->input('uid');
            $officialDetailId = $request->input('official_detail_id');
            $extensionReason = $request->input('extension_reason');
            $departmentalUid = $request->input('departmental_uid', $uid);

            // Handle file upload if provided
            $fileId = null;
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('doc/' . $uid, $fileName, 'public');
                // In a real implementation, you'd save file metadata to a files table
                // For now, we'll store the path
                $fileId = $filePath;
            }

            // Update application status
            DB::table('housing_online_application')
                ->where('online_application_id', $id)
                ->update([
                    'status' => 'license_extended',
                    'date_of_verified' => now(),
                ]);

            // Activate official detail
            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $officialDetailId)
                ->update(['is_active' => 1]);

            // Insert into process flow
            ProcessFlowService::insertProcessFlow($id, 'license_extended', $uid);

            // Insert extension record
            DB::table('housing_license_offer_letter_extension')->insert([
                'uid' => $uid,
                'online_application_id' => $id,
                'status' => 'license_extended',
                'created_date' => now(),
                'extended_upto' => $extensionDate,
                'type_of_extension' => 'License',
                'reason_for_extension' => $extensionReason,
                'doc_fid' => $fileId,
                'departmental_uid' => $departmentalUid,
            ]);

            // Update flat occupant
            DB::table('housing_flat_occupant')
                ->where('online_application_id', $id)
                ->update([
                    'cancellation_extension_status' => 'license_extended',
                    'cancellation_extension_date' => now(),
                ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'License has been Extended!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Request License Extension Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to request license extension',
            ], 500);
        }
    }

    /**
     * Request offer letter extension
     * POST /api/application-status-check/{id}/request-offer-letter-extension
     */
    public function requestOfferLetterExtension(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'extension_reason' => 'required|string|in:Reason_1,Reason_2',
            'extension_date' => 'required|date_format:d/m/Y',
            'uid' => 'required|integer',
            'official_detail_id' => 'required|integer',
            'date_of_verified' => 'required|date',
            'document' => 'nullable|file|mimes:pdf|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $extensionDate = \Carbon\Carbon::createFromFormat('d/m/Y', $request->input('extension_date'))->format('Y-m-d');
            $uid = $request->input('uid');
            $officialDetailId = $request->input('official_detail_id');
            $extensionReason = $request->input('extension_reason');
            $departmentalUid = $request->input('departmental_uid', $uid);
            $dateOfVerified = $request->input('date_of_verified');

            // Validate extension date is after date_of_verified
            if (strtotime($extensionDate) <= strtotime($dateOfVerified)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Extension date must be after the offer letter generation date',
                ], 422);
            }

            // Handle file upload if provided
            $fileId = null;
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('doc/' . $uid, $fileName, 'public');
                $fileId = $filePath;
            }

            // Check extension count (max 2)
            $extensionCount = DB::table('housing_license_offer_letter_extension')
                ->where('online_application_id', $id)
                ->where('type_of_extension', 'Offer Letter')
                ->count();

            if ($extensionCount >= 2) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Maximum extension limit reached (2 extensions allowed)',
                ], 422);
            }

            // Update application status
            DB::table('housing_online_application')
                ->where('online_application_id', $id)
                ->update([
                    'status' => 'offer_letter_extended',
                    'date_of_verified' => now(),
                ]);

            // Activate official detail
            DB::table('housing_applicant_official_detail')
                ->where('applicant_official_detail_id', $officialDetailId)
                ->update(['is_active' => 1]);

            // Insert into process flow
            ProcessFlowService::insertProcessFlow($id, 'offer_letter_extended', $uid);

            // Insert extension record
            DB::table('housing_license_offer_letter_extension')->insert([
                'uid' => $uid,
                'online_application_id' => $id,
                'status' => 'offer_letter_extended',
                'created_date' => now(),
                'extended_upto' => $extensionDate,
                'type_of_extension' => 'Offer Letter',
                'reason_for_extension' => $extensionReason,
                'doc_fid' => $fileId,
                'departmental_uid' => $departmentalUid,
            ]);

            // Update flat occupant
            DB::table('housing_flat_occupant')
                ->where('online_application_id', $id)
                ->update([
                    'cancellation_extension_status' => 'offer_letter_extended',
                    'cancellation_extension_date' => now(),
                ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Offer Letter has been Extended!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Request Offer Letter Extension Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to request offer letter extension',
            ], 500);
        }
    }

    /**
     * Get extension count for an application
     * GET /api/application-status-check/{id}/extension-count
     */
    public function getExtensionCount(Request $request, $id)
    {
        try {
            $cancellationType = $request->input('cancellation_type'); // 'offer-letter' or 'license'

            $query = DB::table('housing_auto_cancellation')
                ->where('online_application_id', $id);

            if ($cancellationType) {
                $query->where('cancellation_type', $cancellationType);
            }

            $count = $query->count();

            return response()->json([
                'status' => 'success',
                'data' => ['count' => $count],
            ]);

        } catch (\Exception $e) {
            Log::error('Extension Count Error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get extension count',
            ], 500);
        }
    }

    /**
     * Fetch application detail
     */
    private function fetchApplicationDetail($onlineApplicationId)
    {
        $result = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->join('housing_district as hds', 'hds.district_code', '=', 'hd.district_code')
            ->join('housing_pay_band_categories as hpb', 'hpb.pay_band_id', '=', 'haod.pay_band_id')
            ->join('housing_applicant as ha', 'ha.uid', '=', 'haod.uid')
            ->join('users as u', 'u.uid', '=', 'haod.uid')
            ->join('housing_allotment_status_master as hasm', 'hasm.short_code', '=', 'hoa.status')
            ->leftJoin('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_flat_type as hft', 'hpb.flat_type_id', '=', 'hft.flat_type_id')
            ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_district as hds2', 'hds2.district_code', '=', 'ha.permanent_district')
            ->leftJoin('housing_district as hds3', 'hds3.district_code', '=', 'haod.office_district')
            ->where('hoa.online_application_id', $onlineApplicationId)
            ->select(
                'hoa.online_application_id',
                'hft.flat_type_id',
                'hft.flat_type',
                'hoa.application_no',
                'hd.district_code',
                'hd.ddo_designation',
                'hd.ddo_address',
                'hds.district_name',
                'haod.applicant_designation',
                'haod.applicant_headquarter',
                'haod.applicant_posting_place',
                'hpb.scale_from',
                'hpb.scale_to',
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
                'hoa.date_of_application',
                'hoa.date_of_verified',
                'ha.gender',
                'ha.permanent_street',
                'ha.permanent_city_town_village',
                'ha.permanent_post_office',
                'ha.permanent_pincode',
                'ha.guardian_name',
                'ha.applicant_name',
                'ha.present_street',
                'ha.present_city_town_village',
                'ha.present_post_office',
                'ha.present_pincode',
                'ha.date_of_birth',
                'u.mail',
                'hna.allotment_category',
                'hasm.status_description',
                'hds.district_name as present_district_name',
                'hds2.district_name as permanent_district_name',
                'hds3.district_name as office_district_name'
            )
            ->first();

        return $result;
    }

    /**
     * Format address
     */
    private function formatAddress($street, $city, $postOffice, $district, $pincode)
    {
        $parts = array_filter([
            $street,
            $city,
            $postOffice ? 'P.O- ' . $postOffice : '',
            $district,
            $pincode ? '-' . $pincode : '',
        ]);

        return !empty($parts) ? implode(', ', $parts) : 'Not Available';
    }

    /**
     * Get application type from application number
     */
    private function getApplicationType($applicationNo)
    {
        if (strpos($applicationNo, 'NA') !== false) {
            return 'New Application';
        } elseif (strpos($applicationNo, 'VS') !== false) {
            return 'Floor Shifting';
        } elseif (strpos($applicationNo, 'CS') !== false) {
            return 'Category Shifting';
        } elseif (strpos($applicationNo, 'PA') !== false) {
            return 'Physical Application';
        } elseif (strpos($applicationNo, 'EO') !== false) {
            return 'Existing Occupant Application';
        }

        return 'Unknown';
    }

    /**
     * Format pay band display
     */
    private function formatPayBand($flatType, $scaleFrom, $scaleTo)
    {
        if ($scaleFrom == 0 && $scaleTo != 0) {
            return $flatType . ' (Below Rs. ' . $scaleTo . '/-)';
        } elseif ($scaleFrom != 0 && $scaleTo == 0) {
            return $flatType . ' (Rs. ' . $scaleFrom . '/- and above)';
        } else {
            return $flatType . ' (Rs. ' . $scaleFrom . '/- to Rs. ' . $scaleTo . '/-)';
        }
    }
}

