<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CategoryShiftingController extends Controller
{
    /**
     * Check if user has a draft CS application
     */
    public function checkDraftStatus(Request $request)
    {
        $uid = $request->input('uid');

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'UID is required',
            ], 422);
        }

        try {
            $result = DB::table('housing_cs_application as hca')
                ->join('housing_online_application as hoa', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                ->where('haod.uid', $uid)
                ->where('hoa.status', 'draft')
                ->select('hoa.status', 'hoa.online_application_id')
                ->orderBy('hoa.online_application_id', 'DESC')
                ->first();

            if ($result) {
                // Check if status is in rejected/cancelled list
                $rejectedStatuses = ['offer_letter_cancel', 'license_cancel'];
                if (in_array($result->status, $rejectedStatuses)) {
                    return response()->json([
                        'status' => 'success',
                        'has_draft' => false,
                        'redirect_to' => '/cs',
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'has_draft' => true,
                    'online_application_id' => $result->online_application_id,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'has_draft' => false,
            ]);

        } catch (\Exception $e) {
            Log::error('Check CS Draft Status Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get CS application data for editing
     */
    public function getApplicationData(Request $request)
    {
        $uid = $request->input('uid');
        $onlineApplicationId = $request->input('online_application_id');

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'UID is required',
            ], 422);
        }

        try {
            // Get max CS application ID (excluding rejected/cancelled)
            $maxCsAppId = $this->getMaxCsApplicationId($uid, ['reject', 'cancel']);

            if ($maxCsAppId == 0) {
                // Check max online application ID
                $maxOnlineAppId = $this->getMaxOnlineApplicationId($uid, ['reject', 'cancel']);
                if ($maxOnlineAppId == 0) {
                    return response()->json([
                        'status' => 'success',
                        'data' => null,
                        'is_first_time' => true,
                    ]);
                }
            } else {
                $onlineApplicationId = $maxCsAppId;
            }

            // Get CS application data
            $csData = DB::table('housing_cs_application')
                ->where('online_application_id', $onlineApplicationId)
                ->first();

            if (!$csData) {
                return response()->json([
                    'status' => 'success',
                    'data' => null,
                ]);
            }

            // Format possession date
            $possessionDate = $csData->possession_date 
                ? Carbon::parse($csData->possession_date)->format('d/m/Y')
                : '';

            return response()->json([
                'status' => 'success',
                'data' => [
                    'online_cs_id' => $onlineApplicationId,
                    'occupation_estate' => $csData->occupation_estate,
                    'occupation_block' => $csData->occupation_block,
                    'occupation_flat' => $csData->occupation_flat,
                    'possession_date' => $possessionDate,
                    'file_licence' => $csData->file_licence,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get CS Application Data Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get current occupation details for user
     */
    public function getCurrentOccupation(Request $request)
    {
        $uid = $request->input('uid');

        if (!$uid) {
            return response()->json([
                'status' => 'error',
                'message' => 'UID is required',
            ], 422);
        }

        try {
            $occupation = DB::table('housing_applicant_official_detail as haod')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_estate as he', 'hf.estate_id', '=', 'he.estate_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->join('housing_block as hb', 'hf.block_id', '=', 'hb.block_id')
                ->where('haod.uid', $uid)
                ->where('haod.is_active', 1)
                ->select(
                    'hf.flat_id',
                    'hft.flat_type_id',
                    'he.estate_id',
                    'hb.block_id',
                    'hb.block_name',
                    'he.estate_name',
                    'hf.flat_no',
                    'hfo.flat_occupant_id'
                )
                ->first();

            if (!$occupation || empty($occupation->flat_no)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No current occupation found. Please apply for new allotment first.',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'estate_id' => $occupation->estate_id,
                    'estate_name' => $occupation->estate_name,
                    'block_id' => $occupation->block_id,
                    'block_name' => $occupation->block_name,
                    'flat_id' => $occupation->flat_id,
                    'flat_no' => $occupation->flat_no,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get Current Occupation Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store CS application (draft or applied)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uid' => 'required|integer',
            'action' => 'required|in:draft,applied',
            'online_cs_id' => 'nullable|integer',
            'cs_occupation_estate' => 'required|integer',
            'cs_occupation_block' => 'required|integer',
            'cs_occupation_flat' => 'required|integer',
            'cs_possession_date' => 'required|date_format:d/m/Y',
            'cs_file_licence' => 'required|file|mimes:pdf|max:1024', // 1MB max
            // Common application fields (from common_application module)
            'pay_band' => 'required|integer',
            'district' => 'required|integer',
            'designation' => 'required|integer',
            'pay_in' => 'required|numeric',
            'grade_pay' => 'nullable|numeric',
            // ... other common fields
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

            $uid = $request->input('uid');
            $action = $request->input('action');
            $onlineCsId = $request->input('online_cs_id', 0);

            // Get flat type from pay band
            $payBandData = DB::table('housing_pay_band_categories')
                ->where('pay_band_id', $request->input('pay_band'))
                ->first();

            if (!$payBandData) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid pay band',
                ], 422);
            }

            // Check if estate has the flat type
            $estateFlatTypes = DB::table('housing_flat')
                ->where('estate_id', $request->input('cs_occupation_estate'))
                ->distinct()
                ->pluck('flat_type_id')
                ->toArray();

            if (!in_array($payBandData->flat_type_id, $estateFlatTypes)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Existing estate does not have the flat type as per your pay scale. Please apply for new allotment.',
                ], 422);
            }

            // Handle file upload
            $fileLicence = null;
            if ($request->hasFile('cs_file_licence')) {
                $file = $request->file('cs_file_licence');
                $fileName = 'cs_licence_' . $uid . '_' . time() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('documents/cs', $fileName, 'public');
                $fileLicence = $filePath;
            }

            // Convert possession date
            $possessionDate = Carbon::createFromFormat('d/m/Y', $request->input('cs_possession_date'))->format('Y-m-d');

            // Validate possession date
            $currentDate = Carbon::now();
            $possessionDateObj = Carbon::parse($possessionDate);

            if ($possessionDateObj->gt($currentDate)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Possession date cannot be after current date.',
                ], 422);
            }

            // Check license issue date if exists
            $licenseData = $this->getCurrentOccupationLicense($uid);
            if ($licenseData && $possessionDateObj->lt(Carbon::parse($licenseData->license_issue_date))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Possession date cannot be before licence issue date.',
                ], 422);
            }

            if ($onlineCsId == 0) {
                // Create new application
                $onlineApplicationId = $this->createOnlineApplication($uid, $action, 'CS', $request);
                $this->createCsApplication($onlineApplicationId, $request, $payBandData->flat_type_id, $possessionDate, $fileLicence);
            } else {
                // Update existing application
                $this->updateOnlineApplication($onlineCsId, $action, $request);
                $this->updateCsApplication($onlineCsId, $request, $payBandData->flat_type_id, $possessionDate, $fileLicence);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $action == 'draft' ? 'Application saved as draft.' : 'You have successfully applied.',
                'online_application_id' => $onlineCsId == 0 ? $onlineApplicationId : $onlineCsId,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Store CS Application Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Helper: Get max CS application ID
     */
    private function getMaxCsApplicationId($uid, $excludeStatuses = [])
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_cs_application as hca', 'hoa.online_application_id', '=', 'hca.online_application_id')
            ->where('haod.uid', $uid)
            ->selectRaw('COALESCE(MAX(hoa.online_application_id), 0) as id');

        if (!empty($excludeStatuses)) {
            $query->whereNotIn('hoa.status', $excludeStatuses);
        }

        $result = $query->first();
        return $result->id ?? 0;
    }

    /**
     * Helper: Get max online application ID
     */
    private function getMaxOnlineApplicationId($uid, $excludeStatuses = [])
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('haod.uid', $uid)
            ->selectRaw('COALESCE(MAX(hoa.online_application_id), 0) as id');

        if (!empty($excludeStatuses)) {
            $query->whereNotIn('hoa.status', $excludeStatuses);
        }

        $result = $query->first();
        return $result->id ?? 0;
    }

    /**
     * Helper: Get current occupation license
     */
    private function getCurrentOccupationLicense($uid)
    {
        return DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_occupant_license as hol', 'hol.flat_occupant_id', '=', 'hfo.flat_occupant_id')
            ->join('housing_license_application as hla', 'hla.license_application_id', '=', 'hol.license_application_id')
            ->where('haod.uid', $uid)
            ->select('hla.license_issue_date')
            ->first();
    }

    /**
     * Helper: Create online application
     */
    private function createOnlineApplication($uid, $action, $appType, $request)
    {
        // This should use CommonApplicationController logic
        // For now, simplified version
        $applicantDetail = DB::table('housing_applicant_official_detail')
            ->where('uid', $uid)
            ->where('is_active', 1)
            ->first();

        if (!$applicantDetail) {
            throw new \Exception('Applicant official detail not found');
        }

        $onlineApplicationId = DB::table('housing_online_application')->insertGetId([
            'applicant_official_detail_id' => $applicantDetail->applicant_official_detail_id,
            'status' => $action,
            'date_of_application' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $onlineApplicationId;
    }

    /**
     * Helper: Update online application
     */
    private function updateOnlineApplication($onlineApplicationId, $action, $request)
    {
        DB::table('housing_online_application')
            ->where('online_application_id', $onlineApplicationId)
            ->update([
                'status' => $action,
                'updated_at' => now(),
            ]);
    }

    /**
     * Helper: Create CS application
     */
    private function createCsApplication($onlineApplicationId, $request, $flatTypeId, $possessionDate, $fileLicence)
    {
        DB::table('housing_cs_application')->insert([
            'online_application_id' => $onlineApplicationId,
            'flat_type_id' => $flatTypeId,
            'occupation_estate' => $request->input('cs_occupation_estate'),
            'occupation_block' => $request->input('cs_occupation_block'),
            'occupation_flat' => $request->input('cs_occupation_flat'),
            'possession_date' => $possessionDate,
            'file_licence' => $fileLicence,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Helper: Update CS application
     */
    private function updateCsApplication($onlineApplicationId, $request, $flatTypeId, $possessionDate, $fileLicence)
    {
        $updateData = [
            'flat_type_id' => $flatTypeId,
            'occupation_estate' => $request->input('cs_occupation_estate'),
            'occupation_block' => $request->input('cs_occupation_block'),
            'occupation_flat' => $request->input('cs_occupation_flat'),
            'possession_date' => $possessionDate,
            'updated_at' => now(),
        ];

        if ($fileLicence) {
            // Delete old file if exists
            $oldApp = DB::table('housing_cs_application')
                ->where('online_application_id', $onlineApplicationId)
                ->first();
            
            if ($oldApp && $oldApp->file_licence) {
                Storage::disk('public')->delete($oldApp->file_licence);
            }

            $updateData['file_licence'] = $fileLicence;
        }

        DB::table('housing_cs_application')
            ->where('online_application_id', $onlineApplicationId)
            ->update($updateData);
    }
}

