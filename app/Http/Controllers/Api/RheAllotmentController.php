<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\RheAllotmentService;

class RheAllotmentController extends Controller
{
    /**
     * Get flat types for dropdown
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFlatTypes()
    {
        \Log::info('Fetching flat types for RHE Allotment');
        try {
            $flatTypes = DB::table('housing_flat_type')
                ->orderBy('flat_type', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->flat_type_id,
                        'label' => $item->flat_type
                    ];
                })
                ->values() // Reset array keys
                ->toArray(); // Convert to array

            \Log::info('Flat types fetched', ['count' => count($flatTypes)]);

            return response()->json([
                'status' => 'success',
                'data' => $flatTypes
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Flat Types Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch flat types',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get vacancy and applicant report
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showVacancy(Request $request)
    {
        try {
            $request->validate([
                'allotment_type' => 'required|integer',
                'district_code' => 'nullable|integer'
            ]);

            $allotmentType = $request->input('allotment_type');
            $districtCode = $request->input('district_code', 17); // Default to 17 (Kolkata)

            // Get estate-wise vacancy
            $allVacancy = RheAllotmentService::getEstatewiseVacancy($allotmentType, $districtCode);

            // Build report data
            $reportData = [];
            $totalRecord = $allVacancy->count();
            $counter = 1;
            $applicantNew = 0;
            $rowspan = 0;

            foreach ($allVacancy as $record) {
                $applicantVs = RheAllotmentService::getNoOfApplicantVs($allotmentType, $record->estate_id);
                $applicantCs = RheAllotmentService::getNoOfApplicantCs($allotmentType, $record->estate_id);

                if ($counter == 1) {
                    $applicantNew = RheAllotmentService::getNoOfApplicantNew($allotmentType);
                    $rowspan = $totalRecord;
                }

                $reportData[] = [
                    'estate_id' => $record->estate_id,
                    'estate_name' => $record->estate_name,
                    'floor_0' => $record->floor_0 ?? 0,
                    'floor_1' => $record->floor_1 ?? 0,
                    'floor_2' => $record->floor_2 ?? 0,
                    'floor_3' => $record->floor_3 ?? 0,
                    'floor_4' => $record->floor_4 ?? 0,
                    'floor_5' => $record->floor_5 ?? 0,
                    'floor_6' => $record->floor_6 ?? 0,
                    'floor_7' => $record->floor_7 ?? 0,
                    'floor_8' => $record->floor_8 ?? 0,
                    'floor_9' => $record->floor_9 ?? 0,
                    'floor_top' => $record->floor_top ?? 0,
                    'applicant_vs' => $applicantVs,
                    'applicant_cs' => $applicantCs,
                    'applicant_new' => ($counter == 1) ? $applicantNew : null,
                    'rowspan' => ($counter == 1) ? $rowspan : null,
                ];

                $counter++;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'report_data' => $reportData,
                    'applicant_new' => $applicantNew,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Show Vacancy Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch vacancy report: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Process allotment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processAllotment(Request $request)
    {
        try {
            $request->validate([
                'allotment_type' => 'required|integer',
                'district_code' => 'nullable|integer'
            ]);

            $allotmentType = $request->input('allotment_type');
            $districtCode = $request->input('district_code', 17); // Default to 17 (Kolkata)
            $uid = auth()->id() ?? $request->input('uid', 1); // Get from auth or request

            // Process allotment
            $result = RheAllotmentService::processAllotment($allotmentType, $districtCode, $uid);

            if ($result['success']) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => [
                        'process_no' => $result['process_no']
                    ]
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                    'data' => []
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Process Allotment Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Allotment Process Failed: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
}

