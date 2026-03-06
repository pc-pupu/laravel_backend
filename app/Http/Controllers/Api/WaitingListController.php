<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WaitingListController extends Controller
{
    /**
     * Flat Type Wise Waiting List (for officials + PDF/source)
     * Mirrors flat_type_wise_waiting_detail_for_competent_authority() in Drupal
     */
    public function flatTypeWaitingList(Request $request)
    {
        $flatTypeId = (int) $request->input('flat_type_id', 0);

        try {
            // Fetch flat types (1=A, 2=B, 3=C, 4=D, 5=A+)
            $flatTypes = DB::table('housing_flat_type')
                ->select('flat_type_id', 'flat_type')
                ->orderBy('flat_type_id')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [$row->flat_type_id => $row->flat_type];
                })
                ->toArray();

            if ($flatTypeId === 0) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'flat_types' => $flatTypes,
                        'rows' => [],
                    ],
                ]);
            }

            // \Log::info('Fetching waiting list for flat type ID: ' . $flatTypeId);
            // Query based on Drupal flat_type_wise_waiting_detail_for_competent_authority
            $query = DB::table('housing_applicant as ha')
                ->join('housing_applicant_official_detail as haod', 'haod.housing_applicant_id', '=', 'ha.housing_applicant_id')
                ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
                ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hnaa.flat_type_id', '=', 'hft.flat_type_id')
                ->where('hoa.status', 'housingapprover_approved_1')
                ->where('hft.flat_type_id', $flatTypeId)
                ->select(
                    'ha.applicant_name',
                    'hoa.online_application_id',
                    'hoa.status',
                    'hoa.application_no',
                    'hft.flat_type',
                    'hoa.computer_serial_no',
                    'hnaa.allotment_category',
                    'haod.grade_pay'
                );

            // Order by numeric then alpha part of computer_serial_no (PostgreSQL)
            $query->orderByRaw("regexp_replace(hoa.computer_serial_no, '[^0-9]', '', 'g')::int ASC");
            $query->orderByRaw("regexp_replace(hoa.computer_serial_no, '[0-9]', '', 'g') ASC");

            $results = $query->get();
            // \Log::info('Waiting list query executed. Number of records: ' . $results->count());

            $rows = [];
            $waitingNo = 1;
            foreach ($results as $row) {
                $rows[] = [
                    'waiting_no' => $waitingNo++,
                    'applicant_name' => $row->applicant_name,
                    'application_no' => $row->application_no,
                    'online_application_id' => $row->online_application_id,
                    'allotment_category' => $row->allotment_category,
                    'flat_type' => $row->flat_type,
                    'grade_pay' => $row->grade_pay,
                    'computer_serial_no' => $row->computer_serial_no,
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'flat_types' => $flatTypes,
                    'rows' => $rows,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Flat Type Waiting List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch flat type wise waiting list',
            ], 500);
        }
    }
}

