<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ErrorLogService;

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
            ErrorLogService::logException($e, 'error', ['module' => 'waiting_list', 'action' => 'flat_type_waiting_list']);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch flat type wise waiting list',
            ], 500);
        }
    }

    /**
     * Applicant waiting position by application number (Drupal waiting_list)
     */
    public function applicantWaitingPosition(Request $request)
    {
        $applicationNo = trim($request->input('application_no', ''));
        if ($applicationNo === '') {
            return response()->json(['status' => 'error', 'message' => 'Application number is required.'], 422);
        }

        $appType = explode('-', $applicationNo)[0] ?? '';

        $query = DB::table('housing_applicant_official_detail as haod')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->leftJoin('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id');

        if (in_array($appType, ['NA', 'PA'], true)) {
            $query->leftJoin('housing_flat_type as hft', 'hna.flat_type_id', '=', 'hft.flat_type_id');
        } elseif ($appType === 'VS') {
            $query->leftJoin('housing_flat_type as hft', 'hva.flat_type_id', '=', 'hft.flat_type_id');
        } elseif ($appType === 'CS') {
            $query->leftJoin('housing_flat_type as hft', 'hca.flat_type_id', '=', 'hft.flat_type_id');
        } else {
            $query->leftJoin('housing_flat_type as hft', 'hna.flat_type_id', '=', 'hft.flat_type_id');
        }

        $row = $query->where('hoa.application_no', $applicationNo)
            ->where('haod.is_active', 1)
            ->select('hoa.application_no', 'hoa.status', 'hft.flat_type', 'hft.flat_type_id')
            ->first();

        if (!$row) {
            return response()->json(['status' => 'success', 'data' => ['rows' => []]]);
        }

        $waitingNo = $row->status === 'housingapprover_approved_1'
            ? $this->computeFlatTypeWaitingNo($row->application_no, (int) $row->flat_type_id)
            : DB::table('housing_allotment_status_master')
                ->where('short_code', $row->status)
                ->value('applicant_show_status');

        return response()->json([
            'status' => 'success',
            'data' => [
                'rows' => [[
                    'application_no' => $row->application_no,
                    'flat_type' => $row->flat_type,
                    'waiting_no' => $waitingNo,
                ]],
            ],
        ]);
    }

    /**
     * Official combined waiting list (Drupal view_waiting_list)
     */
    public function viewWaitingList()
    {
        $rows = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'haod.uid', '=', 'ha.uid')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat_type as hft', 'hnaa.flat_type_id', '=', 'hft.flat_type_id')
            ->where('hoa.status', 'verified')
            ->whereRaw("substring(hoa.application_no, 1, 2) = 'NA'")
            ->orderByRaw("regexp_replace(hoa.computer_serial_no, '[^0-9]', '', 'g')::int ASC")
            ->orderByRaw("regexp_replace(hoa.computer_serial_no, '[0-9]', '', 'g') ASC")
            ->select([
                'ha.applicant_name',
                'hoa.application_no',
                'hft.flat_type',
                'hoa.computer_serial_no',
            ])
            ->get();

        return response()->json(['status' => 'success', 'data' => ['rows' => $rows]]);
    }

    /**
     * Flat type wise waiting applicants vs vacancy by district (Drupal flattype_applicant_vacancy)
     */
    public function flattypeApplicantVacancy(Request $request)
    {
        $districtId = $request->input('district_id');
        $flatTypeId = (int) $request->input('flat_type_id', 0);

        if (!$districtId || !$flatTypeId) {
            return response()->json([
                'status' => 'success',
                'data' => ['waiting_count' => 0, 'vacancy_count' => 0, 'rows' => []],
            ]);
        }

        $waitingCount = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'haod.housing_applicant_id', '=', 'ha.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_ddo as hd', 'hd.ddo_id', '=', 'haod.ddo_id')
            ->where('hoa.status', 'housingapprover_approved_1')
            ->where('hnaa.flat_type_id', $flatTypeId)
            ->where('hd.district_code', $districtId)
            ->count();

        $vacancyCount = DB::table('housing_flat as hf')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->where('hf.flat_type_id', $flatTypeId)
            ->where('he.district_code', $districtId)
            ->where('hf.flat_status_id', 1)
            ->whereIn('hf.floor', ['Ground', 'Top'])
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'waiting_count' => $waitingCount,
                'vacancy_count' => $vacancyCount,
            ],
        ]);
    }

    private function computeFlatTypeWaitingNo(string $applicationNo, int $flatTypeId): int
    {
        $apps = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'haod.housing_applicant_id', '=', 'ha.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat_type as hft', 'hnaa.flat_type_id', '=', 'hft.flat_type_id')
            ->where('hoa.status', 'housingapprover_approved_1')
            ->where('hft.flat_type_id', $flatTypeId)
            ->orderByRaw("regexp_replace(hoa.computer_serial_no, '[^0-9]', '', 'g')::int ASC")
            ->orderByRaw("regexp_replace(hoa.computer_serial_no, '[0-9]', '', 'g') ASC")
            ->pluck('hoa.application_no');

        $pos = 1;
        foreach ($apps as $appNo) {
            if ($appNo === $applicationNo) {
                return $pos;
            }
            $pos++;
        }

        return 0;
    }
}

