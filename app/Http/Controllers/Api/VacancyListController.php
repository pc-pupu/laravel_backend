<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ErrorLogService;

class VacancyListController extends Controller
{
    /**
     * District-wise Flat Vacancy List (for higher officials)
     * Mirrors Drupal vacancy_list_page($district_id, $flat_type_id)
     */
    public function districtWise(Request $request)
    {
        $districtId = (int) $request->input('district_id', 0);
        $flatTypeId = (int) $request->input('flat_type_id', 0);

        try {
            // Fetch districts
            $districts = DB::table('housing_district')
                ->orderBy('district_name')
                ->pluck('district_name', 'district_code')
                ->toArray();

            // Fetch flat types
            $flatTypes = DB::table('housing_flat_type')
                ->orderBy('flat_type_id')
                ->pluck('flat_type', 'flat_type_id')
                ->toArray();

            if ($districtId === 0 || $flatTypeId === 0) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'districts' => $districts,
                        'flat_types' => $flatTypes,
                        'rows' => [],
                    ],
                ]);
            }

            // Get flat_status_id for 'vacant'
            $flatStatusId = DB::table('housing_flat_status')
                ->where('flat_status', 'vacant')
                ->value('flat_status_id');

            if (!$flatStatusId) {
                throw new \Exception('Flat status "vacant" not found');
            }

            // Equivalent of rhe_flat_allotment_list($district_id, $flat_type_id, $flat_status_id)
            $estates = DB::table('housing_flat as hf')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                ->join('housing_district as hd', 'hd.district_code', '=', 'he.district_code')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
                ->where('he.district_code', $districtId)
                ->where('hf.flat_type_id', $flatTypeId)
                ->where('hf.flat_status_id', $flatStatusId)
                ->groupBy('hf.estate_id', 'he.estate_name', 'he.estate_address', 'hd.district_name', 'hft.flat_type')
                ->orderBy('hd.district_name', 'ASC')
                ->orderBy('hf.estate_id', 'ASC')
                ->select(
                    'hf.estate_id',
                    'he.estate_name',
                    'he.estate_address',
                    'hd.district_name',
                    'hft.flat_type'
                )
                ->get();

            $rows = [];
            $totalVacant = 0;

            foreach ($estates as $estate) {
                // Equivalent of rhe_flat_allotment_list_rst_estate(...)
                $flatListQuery = DB::table('housing_flat as hf')
                    ->join('housing_block as hb', 'hf.block_id', '=', 'hb.block_id')
                    ->where('hf.flat_type_id', $flatTypeId)
                    ->where('hf.flat_status_id', $flatStatusId)
                    ->where('hf.estate_id', $estate->estate_id)
                    ->orderBy('hf.estate_id', 'ASC')
                    ->orderBy('hf.flat_id', 'ASC')
                    ->select('hf.flat_no', 'hf.floor', 'hb.block_name');

                $flats = $flatListQuery->get();
                $count = $flats->count();
                $totalVacant += $count;

                $flatStrings = [];
                foreach ($flats as $flat) {
                    $floor = $this->formatFloor($flat->floor);
                    $flatStrings[] = sprintf(
                        '%s(%s-%s Block)',
                        $flat->flat_no,
                        $floor,
                        $flat->block_name
                    );
                }

                $rows[] = [
                    'district_name' => $estate->district_name,
                    'estate_name' => $estate->estate_name,
                    'estate_address' => $estate->estate_address,
                    'flat_type' => $estate->flat_type,
                    'no_of_vacant_flats' => $count,
                    'flat_list' => implode(', ', $flatStrings),
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'districts' => $districts,
                    'flat_types' => $flatTypes,
                    'rows' => $rows,
                    'total_vacant_flats' => $totalVacant,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Vacancy List District-wise Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            ErrorLogService::logException($e, 'error', ['module' => 'vacancy_list', 'action' => 'district_wise']);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch vacancy list',
            ], 500);
        }
    }

    /**
     * RHE-wise Flat Vacancy List for Sub-Division user
     * Mirrors show_vacancy_list() + rhe_wise_vacancy_list_fetch()
     */
    public function rheWise(Request $request)
    {
        $estateId = (int) $request->input('estate_id', 0);
        $flatTypeId = (int) $request->input('flat_type_id', 0);

        try {
            // Fetch list of RHE (estates)
            $rheList = DB::table('housing_estate')
                ->orderBy('estate_name')
                ->pluck('estate_name', 'estate_id')
                ->toArray();

            // Fetch flat types
            $flatTypes = DB::table('housing_flat_type')
                ->orderBy('flat_type_id')
                ->pluck('flat_type', 'flat_type_id')
                ->toArray();

            if ($estateId === 0 || $flatTypeId === 0) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'rhe_list' => $rheList,
                        'flat_types' => $flatTypes,
                        'rows' => [],
                    ],
                ]);
            }

            // Get flat_status_id for 'vacant'
            $flatStatusId = DB::table('housing_flat_status')
                ->where('flat_status', 'vacant')
                ->value('flat_status_id');

            if (!$flatStatusId) {
                throw new \Exception('Flat status "vacant" not found');
            }

            // Equivalent of rhe_wise_vacancy_list_fetch($rhe_name, $flat_type_id, $flat_status_id)
            $estates = DB::table('housing_flat as hf')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                ->join('housing_flat_type as hft', 'hft.flat_type_id', '=', 'hf.flat_type_id')
                ->where('hf.estate_id', $estateId)
                ->where('hf.flat_type_id', $flatTypeId)
                ->where('hf.flat_status_id', $flatStatusId)
                ->groupBy('hf.estate_id', 'he.estate_name', 'he.estate_address', 'hft.flat_type')
                ->orderBy('hf.estate_id', 'ASC')
                ->select(
                    'hf.estate_id',
                    'he.estate_name',
                    'he.estate_address',
                    'hft.flat_type'
                )
                ->get();

            $rows = [];
            $totalVacant = 0;

            foreach ($estates as $estate) {
                $flatListQuery = DB::table('housing_flat as hf')
                    ->join('housing_block as hb', 'hf.block_id', '=', 'hb.block_id')
                    ->where('hf.flat_type_id', $flatTypeId)
                    ->where('hf.flat_status_id', $flatStatusId)
                    ->where('hf.estate_id', $estate->estate_id)
                    ->orderBy('hf.estate_id', 'ASC')
                    ->orderBy('hf.flat_id', 'ASC')
                    ->select('hf.flat_no', 'hf.floor', 'hb.block_name');

                $flats = $flatListQuery->get();
                $count = $flats->count();
                $totalVacant += $count;

                $flatStrings = [];
                foreach ($flats as $flat) {
                    $floor = $this->formatFloor($flat->floor);
                    $flatStrings[] = sprintf(
                        '%s(%s-%s Block)',
                        $flat->flat_no,
                        $floor,
                        $flat->block_name
                    );
                }

                $rows[] = [
                    'estate_name' => $estate->estate_name,
                    'estate_address' => $estate->estate_address,
                    'flat_type' => $estate->flat_type,
                    'no_of_vacant_flats' => $count,
                    'flat_list' => implode(', ', $flatStrings),
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'rhe_list' => $rheList,
                    'flat_types' => $flatTypes,
                    'rows' => $rows,
                    'total_vacant_flats' => $totalVacant,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Vacancy List RHE-wise Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            ErrorLogService::logException($e, 'error', ['module' => 'vacancy_list', 'action' => 'rhe_wise']);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch RHE-wise vacancy list',
            ], 500);
        }
    }

    /**
     * Map floor text to shortened label (mirrors Drupal rhe_flat_allotment_list_rst_estate)
     */
    private function formatFloor(?string $floor): string
    {
        $floor = trim((string) $floor);

        return match ($floor) {
            'Ground' => 'Ground Flr',
            'First' => '1st Flr',
            'Second' => '2nd Flr',
            'Third' => '3rd Flr',
            'Fourth' => '4th Flr',
            'Fifth' => '5th Flr',
            'Sixth' => '6th Flr',
            'Seventh' => '7th Flr',
            'Eighth' => '8th Flr',
            'Ninth' => '9th Flr',
            'Top' => 'Top Flr',
            default => $floor,
        };
    }
}

