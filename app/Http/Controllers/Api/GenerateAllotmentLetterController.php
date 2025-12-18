<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateAllotmentLetterController extends Controller
{
    /**
     * Get flat types for dropdown
     */
    public function getFlatTypes()
    {
        try {
            $flatTypes = DB::table('housing_flat_type')
                ->orderBy('flat_type', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->flat_type_id,
                        'label' => $item->flat_type
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $flatTypes,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Flat Types Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch flat types',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get waiting list for a flat type
     */
    public function getWaitingList(Request $request)
    {
        try {
            $request->validate([
                'flat_type_id' => 'required|integer'
            ]);

            $flatTypeId = $request->flat_type_id;
            $flatType = $this->fetchRheFlatNameById($flatTypeId);
            $waitingList = $this->fetchWaitingList($flatTypeId);

            return response()->json([
                'status' => 'success',
                'data' => $waitingList,
                'flat_type' => $flatType,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get Waiting List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch waiting list',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Generate allotment letter
     */
    public function generateLetter(Request $request)
    {
        try {
            $request->validate([
                'flat_id' => 'required|integer',
                'online_application_id' => 'required|integer',
                'flat_type' => 'required|string',
                'roaster_counter' => 'required|integer',
                'list_no' => 'required|integer'
            ]);

            // Check if already generated
            $existing = DB::table('housing_allotment_letter')
                ->where('flat_id', $request->flat_id)
                ->where('online_application_id', $request->online_application_id)
                ->where('flat_type', $request->flat_type)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Allotment letter already generated for this flat and application',
                    'status_code' => 400
                ], 400);
            }

            // Insert into housing_allotment_letter
            DB::table('housing_allotment_letter')->insert([
                'flat_id' => $request->flat_id,
                'online_application_id' => $request->online_application_id,
                'flat_type' => $request->flat_type,
                'roaster_counter' => $request->roaster_counter,
                'list_no' => $request->list_no,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Allotment letter generated successfully',
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Generate Letter Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate allotment letter',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Fetch waiting list (complex logic from Drupal)
     */
    private function fetchWaitingList($flatTypeId)
    {
        // Get flat type name (A, B, C, etc.)
        $flatType = $this->fetchRheFlatNameById($flatTypeId);
        if (!$flatType) {
            return [];
        }

        // Get flats that already have letters generated
        $flatArray = $this->getAllotmentLetterFlatList($flatType);

        // Get vacant flat status ID
        $flatStatusId = $this->fetchFlatStatusId('vacant');

        // Get estate list with available flats
        $estateList = $this->getEstateListWithFlats($flatTypeId, $flatStatusId, $flatArray);

        // Get application data
        $housingApplicationData = $this->fetchHousingApplicationData($flatTypeId);

        // Get virtual roaster counter
        $housingVirtualRoasterCounter = $this->fetchHousingVirtualRoasterCounter($flatTypeId);

        // Process and match applicants with flats
        $dataArray = [];
        foreach ($housingApplicationData as $application) {
            // Get roaster counter for this category
            $roasterData = $this->fetchRoasterCounter($housingVirtualRoasterCounter, $application->allotment_category);
            
            if ($roasterData == null) {
                $roasterData = ['roaster_counter' => 0, 'list_no' => 0];
            }

            $counter = $roasterData['roaster_counter'];
            if ($counter > 0 && $counter < 9) {
                $counter = str_pad($counter, 2, "0", STR_PAD_LEFT);
            }

            $index = $roasterData['list_no'] . $counter;

            // Get estate choice list for new applications
            $estateChoiceList = null;
            if ($application->application_type == 'New Allotment Application') {
                $estateChoiceList = $this->fetchApplicantChoiceListForEstate($application->online_application_id);
            }

            // Format applicant name
            $applicantName = str_replace(' ', '&nbsp;', $application->applicant_name);
            $allotmentCategory = str_replace(' ', '&nbsp;', $application->allotment_category);
            $applicationType = str_replace(' ', '&nbsp;', $application->application_type);

            // Format date
            $date = new \DateTime($application->date_of_application);
            $formattedDate = $date->format('dS M,y');

            $dataArray[$index] = [
                'applicant' => '<h6><label class="label" style="color:#2F709B;letter-spacing: 0.06em;">' . $applicantName . '</label><label style="color:#0090C7;font-weight: 400;display:block;">(' . $allotmentCategory . ')</label><div style="display:none;"><hr><label style="color:#0090C7;font-weight: 400;">' . $applicationType . '</label></div></h6>',
                'roaster_counter' => '<h6><b>' . $roasterData['roaster_counter'] . ' / List ' . $roasterData['list_no'] . '</b></h6>',
                'rc' => $roasterData['roaster_counter'],
                'ln' => $roasterData['list_no'],
                'application_date' => '<h6><b>' . $formattedDate . '</b></h6>',
                'offer' => $estateChoiceList,
                'waiting_no' => '',
                'online_application_id' => $application->online_application_id,
                'flat_offer' => true,
                'grade_pay' => $application->grade_pay ?? null
            ];

            if ($flatType == 'A' || $flatType == 'B') {
                $dataArray[$index]['grade_pay'] = '<h6><b>' . ($application->grade_pay ?? '') . '</b></h6>';
            }
        }

        // Sort by index
        ksort($dataArray);

        // Match applicants with flats
        $dataArr = [];
        $i = 1;
        foreach ($dataArray as $dataVal) {
            $flatArr = $this->allotVirtualRHE($estateList, $dataVal['offer']);
            $dataVal['offer'] = '';

            if ($flatArr != null) {
                $estateName = str_replace(' ', '&nbsp;', $flatArr['estate_name']);
                $dataVal['offer'] = '<h6><label class="label">Flat:&nbsp;' . $flatArr['flat_no'] . '&nbsp;<label style="color:#0090C7;font-weight: 400;">[&nbsp;' . $estateName . '&nbsp;]</label></label></h6>';
                $dataVal['flat_id'] = $flatArr['flat_id'];
            } else {
                $dataVal['flat_offer'] = false;
            }

            $dataVal['waiting_no'] = '<h6><span>' . $i . '</span></h6>';
            $dataVal['waiting_no_numeric'] = $i; // Add numeric waiting number for easier comparison
            $dataArr[$dataVal['online_application_id']] = $dataVal;
            $i++;
        }

        return $dataArr;
    }

    /**
     * Get flat type name by ID
     */
    private function fetchRheFlatNameById($flatTypeId)
    {
        $flatType = DB::table('housing_flat_type')
            ->where('flat_type_id', $flatTypeId)
            ->value('flat_type');

        return $flatType;
    }

    /**
     * Get list of flat IDs that already have allotment letters
     */
    private function getAllotmentLetterFlatList($flatType)
    {
        $flatIds = DB::table('housing_allotment_letter')
            ->where('flat_type', $flatType)
            ->pluck('flat_id')
            ->toArray();

        return $flatIds;
    }

    /**
     * Get flat status ID by status name
     */
    private function fetchFlatStatusId($flatStatus)
    {
        $statusId = DB::table('housing_flat_status')
            ->where('flat_status', trim($flatStatus))
            ->value('flat_status_id');

        return $statusId;
    }

    /**
     * Get estate list with available flats
     */
    private function getEstateListWithFlats($flatTypeId, $flatStatusId, $excludeFlatIds)
    {
        $estates = DB::table('housing_flat as hf')
            ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
            ->join('housing_district as hd', 'he.district_code', '=', 'hd.district_code')
            ->where('hf.flat_type_id', $flatTypeId)
            ->where('hf.flat_status_id', $flatStatusId)
            ->when(count($excludeFlatIds) > 0, function ($query) use ($excludeFlatIds) {
                $query->whereNotIn('hf.flat_id', $excludeFlatIds);
            })
            ->select('hf.estate_id', 'he.estate_name', 'he.estate_address', 'hd.district_name')
            ->groupBy('hf.estate_id', 'he.estate_name', 'he.estate_address', 'hd.district_name')
            ->orderBy('hd.district_name', 'asc')
            ->orderBy('hf.estate_id', 'asc')
            ->get();

        $estateList = [];
        foreach ($estates as $estate) {
            // Get flats for this estate
            $flats = DB::table('housing_flat as hf')
                ->where('hf.flat_type_id', $flatTypeId)
                ->where('hf.flat_status_id', $flatStatusId)
                ->where('hf.estate_id', $estate->estate_id)
                ->when(count($excludeFlatIds) > 0, function ($query) use ($excludeFlatIds) {
                    $query->whereNotIn('hf.flat_id', $excludeFlatIds);
                })
                ->select('hf.flat_id', 'hf.flat_no')
                ->orderBy('hf.flat_id', 'asc')
                ->get();

            $flatList = [];
            foreach ($flats as $flat) {
                $flatList[$flat->flat_id] = $flat->flat_no;
            }

            $estateList[$estate->estate_id] = [$estate->estate_name => $flatList];
        }

        return $estateList;
    }

    /**
     * Fetch housing application data
     */
    private function fetchHousingApplicationData($flatTypeId)
    {
        $flatType = $this->fetchRheFlatNameById($flatTypeId);

        $applications = DB::table('housing_application_data as had')
            ->where('had.flat_type', $flatType)
            ->select(
                'had.application_type',
                'had.online_application_id',
                'had.date_of_application',
                'had.applicant_name',
                'had.grade_pay',
                'had.allotment_category',
                'had.flat_type',
                'had.flat_type_id'
            )
            ->orderBy('had.flat_type', 'asc')
            ->orderBy('had.allotment_category', 'asc')
            ->get();

        return $applications;
    }

    /**
     * Fetch housing virtual roaster counter
     */
    private function fetchHousingVirtualRoasterCounter($flatTypeId)
    {
        $flatType = $this->fetchRheFlatNameById($flatTypeId);

        $counters = DB::table('housing_virtual_roaster_counter as hvrc')
            ->where('hvrc.flat_type', $flatType)
            ->select('hvrc.roaster_counter', 'hvrc.list_no', 'hvrc.category')
            ->orderBy('hvrc.flat_type', 'asc')
            ->orderBy('hvrc.category', 'asc')
            ->orderBy('hvrc.list_no', 'asc')
            ->orderBy('hvrc.roaster_counter', 'asc')
            ->get();

        $virtualRoasterCounter = [];
        foreach ($counters as $counter) {
            $virtualRoasterCounter[$counter->category][] = [
                'roaster_counter' => $counter->roaster_counter,
                'list_no' => $counter->list_no,
                'category' => $counter->category
            ];
        }

        return $virtualRoasterCounter;
    }

    /**
     * Fetch applicant choice list for estate
     */
    private function fetchApplicantChoiceListForEstate($onlineApplicationId)
    {
        $choices = DB::table('housing_new_allotment_application as hna')
            ->leftJoin('housing_estate as he1', 'he1.estate_id', '=', 'hna.estate_id_choice1')
            ->leftJoin('housing_estate as he2', 'he2.estate_id', '=', 'hna.estate_id_choice2')
            ->leftJoin('housing_estate as he3', 'he3.estate_id', '=', 'hna.estate_id_choice3')
            ->leftJoin('housing_estate as he4', 'he4.estate_id', '=', 'hna.estate_id_choice4')
            ->where('hna.online_application_id', $onlineApplicationId)
            ->select(
                'hna.estate_id_choice1 as choice1',
                'hna.estate_id_choice2 as choice2',
                'hna.estate_id_choice3 as choice3',
                'hna.estate_id_choice4 as choice4',
                'he1.estate_name as choice1_estate_name',
                'he2.estate_name as choice2_estate_name',
                'he3.estate_name as choice3_estate_name',
                'he4.estate_name as choice4_estate_name'
            )
            ->first();

        $estateData = [];
        if ($choices) {
            if ($choices->choice1) {
                $estateData[$choices->choice1] = $choices->choice1_estate_name;
            }
            if ($choices->choice2) {
                $estateData[$choices->choice2] = $choices->choice2_estate_name;
            }
            if ($choices->choice3) {
                $estateData[$choices->choice3] = $choices->choice3_estate_name;
            }
            if ($choices->choice4) {
                $estateData[$choices->choice4] = $choices->choice4_estate_name;
            }
        }

        return $estateData;
    }

    /**
     * Allot virtual RHE (match applicant choice with available flats)
     */
    private function allotVirtualRHE(&$estateList, $estateChoiceList)
    {
        $flatArr = null;

        if ($estateChoiceList && is_array($estateChoiceList)) {
            foreach ($estateChoiceList as $key => $val) {
                if (isset($estateList[$key])) {
                    $flatArr = $this->allotVirtualRHEFlat($estateList, $key);
                    break;
                }
            }
        }

        if ($flatArr == null && count($estateList) > 0) {
            $firstKey = array_key_first($estateList);
            $flatArr = $this->allotVirtualRHEFlat($estateList, $firstKey);
        }

        return $flatArr;
    }

    /**
     * Allot virtual RHE flat (get first available flat from estate)
     */
    private function allotVirtualRHEFlat(&$estateList, $key)
    {
        if (!isset($estateList[$key])) {
            return null;
        }

        $estateName = array_key_first($estateList[$key]);
        $flatList = $estateList[$key][$estateName];

        if (empty($flatList)) {
            unset($estateList[$key]);
            return null;
        }

        $flatId = array_key_first($flatList);
        $flatNo = $flatList[$flatId];

        $flatArr = [
            'estate_name' => $estateName,
            'flat_id' => $flatId,
            'flat_no' => $flatNo
        ];

        unset($estateList[$key][$estateName][$flatId]);

        if (empty($estateList[$key][$estateName])) {
            unset($estateList[$key][$estateName]);
        }

        if (empty($estateList[$key])) {
            unset($estateList[$key]);
        }

        return $flatArr;
    }

    /**
     * Fetch roaster counter for a category
     */
    private function fetchRoasterCounter(&$virtualRoasterArray, $desiredCategory)
    {
        if (isset($virtualRoasterArray[$desiredCategory]) && !empty($virtualRoasterArray[$desiredCategory])) {
            $obj = array_shift($virtualRoasterArray[$desiredCategory]);

            if (empty($virtualRoasterArray[$desiredCategory])) {
                unset($virtualRoasterArray[$desiredCategory]);
            }

            return $obj;
        }

        return null;
    }
}

