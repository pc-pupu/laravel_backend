<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Port of Drupal waiting_list module helpers (incl. applicant_show_status, Mar 2026).
 */
class WaitingNumberService
{
    public const APPROVED_STATUS = 'housingapprover_approved_1';

    /**
     * Mirrors Drupal flat_type_wise_waiting_no().
     */
    public static function getFlatTypeWiseWaitingNo(string $applicationNo, $flatTypeId = ''): mixed
    {
        $appType = explode('-', $applicationNo)[0] ?? '';

        $row = DB::table('housing_online_application as hoa')
            ->join('housing_allotment_status_master as hasm', 'hoa.status', '=', 'hasm.short_code')
            ->leftJoin('housing_new_allotment_application as hna', 'hna.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
            ->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
            ->where('hoa.application_no', $applicationNo)
            ->select('hoa.status', 'hasm.applicant_show_status')
            ->first();

        if (!$row) {
            return 0;
        }

        if ($row->status === self::APPROVED_STATUS && $flatTypeId !== '' && $flatTypeId !== null) {
            $appData = self::getFlatTypeWiseWaitingDetail((int) $flatTypeId, $appType);
            foreach ($appData as $item) {
                if (($item['application_no'] ?? '') === $applicationNo) {
                    return $item['waiting_no'];
                }
            }

            return 0;
        }

        return $row->applicant_show_status ?? 0;
    }

    /**
     * Mirrors Drupal flat_type_wise_waiting_detail().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getFlatTypeWiseWaitingDetail(int $flatTypeId, string $appType = 'NA'): array
    {
        if ($flatTypeId === 5) {
            return self::buildWaitingRows(
                self::queryNaPaApprovedList($flatTypeId, null),
                $flatTypeId
            );
        }

        $query = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'ha.housing_applicant_id', '=', 'haod.housing_applicant_id')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->where('hoa.status', self::APPROVED_STATUS);

        if (in_array($appType, ['NA', 'PA'], true)) {
            $query->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hnaa.flat_type_id', '=', 'hft.flat_type_id')
                ->where('hft.flat_type_id', $flatTypeId)
                ->orderByRaw("regexp_replace(hoa.computer_serial_no, '[^0-9]', '', 'g')::int ASC")
                ->orderByRaw("regexp_replace(hoa.computer_serial_no, '[0-9]', '', 'g') ASC");
        } elseif ($appType === 'VS') {
            $query->leftJoin('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hva.flat_type_id', '=', 'hft.flat_type_id')
                ->leftJoin('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->where('hft.flat_type_id', $flatTypeId)
                ->whereRaw("SUBSTRING(hoa.application_no, 1, 2) = ?", ['VS'])
                ->orderBy('hoa.online_application_id', 'ASC');
        } elseif ($appType === 'CS') {
            $query->leftJoin('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_type as hft', 'hca.flat_type_id', '=', 'hft.flat_type_id')
                ->leftJoin('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
                ->where('hft.flat_type_id', $flatTypeId)
                ->whereRaw("SUBSTRING(hoa.application_no, 1, 2) = ?", ['CS'])
                ->orderBy('hoa.online_application_id', 'ASC');
        } else {
            return [];
        }

        $results = $query->select(
            'ha.applicant_name',
            'hoa.online_application_id',
            'hoa.status',
            'hoa.application_no',
            'hft.flat_type',
            'hoa.computer_serial_no',
            'hnaa.allotment_category',
            'haod.grade_pay'
        )->get();

        return self::buildWaitingRows($results, $flatTypeId);
    }

    /**
     * Applicant status history grouped by applicant_show_status (Drupal fetch_full_application_status_updated).
     */
    public static function getApplicationStatusHistoryGrouped(string $applicationNo): array
    {
        $rows = DB::select(
            "SELECT COUNT(1) as cnt, t.applicant_show_status, MIN(t.created_at) AS created_at
             FROM (
                 SELECT hpf.short_code, hoa.online_application_id, hpf.created_at, hasm.applicant_show_status, hasm.status_description
                 FROM housing_process_flow hpf
                 INNER JOIN housing_online_application hoa ON hoa.online_application_id = hpf.online_application_id
                 INNER JOIN housing_allotment_status_master hasm ON hasm.status_id = hpf.status_id
                 WHERE hoa.application_no = ?
             ) AS t
             GROUP BY t.applicant_show_status
             ORDER BY MIN(t.created_at) ASC",
            [$applicationNo]
        );

        return array_map(function ($row) {
            return [
                'applicant_show_status' => $row->applicant_show_status,
                'status_description' => $row->applicant_show_status ?? $row->status_description ?? null,
                'created_at' => $row->created_at,
                'count' => (int) $row->cnt,
            ];
        }, $rows);
    }

    /**
     * Display label for current application status (prefers applicant_show_status).
     */
    public static function getDisplayStatusForShortCode(?string $shortCode): ?string
    {
        if (!$shortCode) {
            return null;
        }

        $row = DB::table('housing_allotment_status_master')
            ->where('short_code', $shortCode)
            ->select('applicant_show_status', 'status_description')
            ->first();

        if (!$row) {
            return null;
        }

        return $row->applicant_show_status ?: $row->status_description;
    }

    private static function queryNaPaApprovedList(int $flatTypeId, ?string $appType)
    {
        $query = DB::table('housing_applicant as ha')
            ->join('housing_applicant_official_detail as haod', 'haod.uid', '=', 'ha.uid')
            ->join('housing_online_application as hoa', 'hoa.applicant_official_detail_id', '=', 'haod.applicant_official_detail_id')
            ->join('housing_new_allotment_application as hnaa', 'hnaa.online_application_id', '=', 'hoa.online_application_id')
            ->join('housing_flat_type as hft', 'hnaa.flat_type_id', '=', 'hft.flat_type_id')
            ->where('hoa.status', self::APPROVED_STATUS)
            ->where('hft.flat_type_id', $flatTypeId)
            ->orderByRaw("regexp_replace(hoa.computer_serial_no, '[^0-9]', '', 'g')::int ASC")
            ->orderByRaw("regexp_replace(hoa.computer_serial_no, '[0-9]', '', 'g') ASC");

        if ($appType && in_array($appType, ['VS', 'CS'], true)) {
            $query->whereRaw("SUBSTRING(hoa.application_no, 1, 2) = ?", [$appType]);
        }

        return $query->select(
            'ha.applicant_name',
            'hoa.online_application_id',
            'hoa.status',
            'hoa.application_no',
            'hft.flat_type',
            'hoa.computer_serial_no',
            'hnaa.allotment_category',
            'haod.grade_pay'
        )->get();
    }

    private static function buildWaitingRows($results, int $flatTypeId): array
    {
        $appData = [];
        $i = 1;
        foreach ($results as $data) {
            $row = [
                'waiting_no' => $i,
                'applicant_name' => $data->applicant_name,
                'application_no' => $data->application_no,
                'flat_type' => $data->flat_type,
                'computer_serial_no' => $data->computer_serial_no,
            ];
            if ($flatTypeId === 5) {
                $row['grade_pay'] = $data->grade_pay ?? null;
                $row['allotment_category'] = $data->allotment_category ?? null;
            }
            $appData[] = $row;
            $i++;
        }

        return $appData;
    }
}
