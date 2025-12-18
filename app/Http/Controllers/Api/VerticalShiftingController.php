<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class VerticalShiftingController extends Controller
{
    /**
     * Check if user has a draft VS application
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
            $result = DB::table('housing_vs_application as hva')
                ->join('housing_online_application as hoa', 'hva.online_application_id', '=', 'hoa.online_application_id')
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
                        'redirect_to' => '/vs',
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
            Log::error('Check VS Draft Status Error', [
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
     * Get VS application data for editing
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
            // Get max VS application ID (excluding rejected/cancelled)
            $maxVsAppId = $this->getMaxVsApplicationId($uid, ['reject', 'cancel']);

            if ($maxVsAppId == 0) {
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
                $onlineApplicationId = $maxVsAppId;
            }

            // Get VS application data
            $vsData = DB::table('housing_vs_application')
                ->where('online_application_id', $onlineApplicationId)
                ->first();

            if (!$vsData) {
                return response()->json([
                    'status' => 'success',
                    'data' => null,
                ]);
            }

            // Format possession date
            $possessionDate = $vsData->possession_date 
                ? Carbon::parse($vsData->possession_date)->format('d/m/Y')
                : '';

            return response()->json([
                'status' => 'success',
                'data' => [
                    'online_vs_id' => $onlineApplicationId,
                    'occupation_estate' => $vsData->occupation_estate,
                    'occupation_block' => $vsData->occupation_block,
                    'occupation_flat' => $vsData->occupation_flat,
                    'possession_date' => $possessionDate,
                    'file_licence' => $vsData->file_licence,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get VS Application Data Error', [
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
     * Store VS application (draft or applied)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uid' => 'required|integer',
            'action' => 'required|in:draft,applied',
            'online_vs_id' => 'nullable|integer',
            'occupation_estate' => 'required|integer',
            'occupation_block' => 'required|integer',
            'occupation_flat' => 'required|integer',
            'possession_date' => 'required|date_format:d/m/Y',
            'file_licence' => 'required|file|mimes:pdf|max:1024', // 1MB max
            'vs_scanned_sign' => 'nullable|file|mimes:jpg,jpeg|max:50', // 50KB max
            // Common application fields
            'pay_band' => 'required|integer',
            'district' => 'required|integer',
            'designation' => 'required|integer',
            'pay_in' => 'required|numeric',
            'grade_pay' => 'nullable|numeric',
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
            $onlineVsId = $request->input('online_vs_id', 0);

            // Get flat type from pay band
            $payBandData = DB::table('housing_pay_band')
                ->where('pay_band_id', $request->input('pay_band'))
                ->first();

            if (!$payBandData) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid pay band',
                ], 422);
            }

            // Handle file uploads
            $fileLicence = null;
            if ($request->hasFile('file_licence')) {
                $file = $request->file('file_licence');
                $fileName = 'vs_licence_' . $uid . '_' . time() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('documents/vs', $fileName, 'public');
                $fileLicence = $filePath;
            }

            $scannedSign = null;
            if ($request->hasFile('vs_scanned_sign')) {
                $file = $request->file('vs_scanned_sign');
                $fileName = 'vs_sign_' . $uid . '_' . time() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('documents/vs', $fileName, 'public');
                $scannedSign = $filePath;
            }

            // Convert possession date
            $possessionDate = Carbon::createFromFormat('d/m/Y', $request->input('possession_date'))->format('Y-m-d');

            // Validate possession date
            $currentDate = Carbon::now();
            $possessionDateObj = Carbon::parse($possessionDate);

            if ($possessionDateObj->gt($currentDate)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Possession date cannot be after current date.',
                ], 422);
            }

            // Check license issue date and 3-year requirement
            $licenseData = $this->getCurrentOccupationLicense($uid);
            if ($licenseData) {
                $licenseDate = Carbon::parse($licenseData->license_issue_date);
                
                if ($possessionDateObj->lt($licenseDate)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Possession date cannot be before licence issue date.',
                    ], 422);
                }

                // Check if user has occupied for at least 3 years
                $yearsDiff = $possessionDateObj->diffInYears($licenseDate);
                if ($yearsDiff < 3) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'You are not eligible for shifting because shifting criteria not fulfilling. You must have occupied for at least 3 years.',
                    ], 422);
                }
            } else {
                if ($possessionDateObj->gt($currentDate)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Possession date cannot be after current date.',
                    ], 422);
                }
            }

            if ($onlineVsId == 0) {
                // Create new application
                $onlineApplicationId = $this->createOnlineApplication($uid, $action, 'VS', $request);
                $this->createVsApplication($onlineApplicationId, $request, $payBandData->flat_type_id, $possessionDate, $fileLicence, $scannedSign);
            } else {
                // Update existing application
                $this->updateOnlineApplication($onlineVsId, $action, $request);
                $this->updateVsApplication($onlineVsId, $request, $payBandData->flat_type_id, $possessionDate, $fileLicence, $scannedSign);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $action == 'draft' ? 'Application saved as draft.' : 'You have successfully applied.',
                'online_application_id' => $onlineVsId == 0 ? $onlineApplicationId : $onlineVsId,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Store VS Application Error', [
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
     * Helper: Get max VS application ID
     */
    private function getMaxVsApplicationId($uid, $excludeStatuses = [])
    {
        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_vs_application as hva', 'hoa.online_application_id', '=', 'hva.online_application_id')
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
     * Helper: Create VS application
     */
    private function createVsApplication($onlineApplicationId, $request, $flatTypeId, $possessionDate, $fileLicence, $scannedSign)
    {
        DB::table('housing_vs_application')->insert([
            'online_application_id' => $onlineApplicationId,
            'flat_type_id' => $flatTypeId,
            'occupation_estate' => $request->input('occupation_estate'),
            'occupation_block' => $request->input('occupation_block'),
            'occupation_flat' => $request->input('occupation_flat'),
            'possession_date' => $possessionDate,
            'file_licence' => $fileLicence,
            'scanned_sign' => $scannedSign,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Helper: Update VS application
     */
    private function updateVsApplication($onlineApplicationId, $request, $flatTypeId, $possessionDate, $fileLicence, $scannedSign)
    {
        $updateData = [
            'flat_type_id' => $flatTypeId,
            'occupation_estate' => $request->input('occupation_estate'),
            'occupation_block' => $request->input('occupation_block'),
            'occupation_flat' => $request->input('occupation_flat'),
            'possession_date' => $possessionDate,
            'updated_at' => now(),
        ];

        if ($fileLicence) {
            // Delete old file if exists
            $oldApp = DB::table('housing_vs_application')
                ->where('online_application_id', $onlineApplicationId)
                ->first();
            
            if ($oldApp && $oldApp->file_licence) {
                Storage::disk('public')->delete($oldApp->file_licence);
            }

            $updateData['file_licence'] = $fileLicence;
        }

        if ($scannedSign) {
            // Delete old file if exists
            $oldApp = DB::table('housing_vs_application')
                ->where('online_application_id', $onlineApplicationId)
                ->first();
            
            if ($oldApp && $oldApp->scanned_sign) {
                Storage::disk('public')->delete($oldApp->scanned_sign);
            }

            $updateData['scanned_sign'] = $scannedSign;
        }

        DB::table('housing_vs_application')
            ->where('online_application_id', $onlineApplicationId)
            ->update($updateData);
    }
}

