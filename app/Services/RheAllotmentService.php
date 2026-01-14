<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\ProcessFlowService;

class RheAllotmentService
{
    /**
     * Get estate-wise vacancy count by floor for a given flat type and district
     *
     * @param int $allotmentType Flat type ID
     * @param int $districtCode District code (default: 0 for all)
     * @return \Illuminate\Support\Collection
     */
    public static function getEstatewiseVacancy($allotmentType, $districtCode = 0)
    {
        $statusId = 1; // "ready to allot" flat status

        $query = DB::table('housing_flat as t1')
            ->join('housing_estate as t2', 't1.estate_id', '=', 't2.estate_id')
            ->join('housing_district as t3', 't2.district_code', '=', 't3.district_code')
            ->where('t1.flat_type_id', $allotmentType)
            ->where('t1.flat_status_id', $statusId)
            ->select(
                't2.estate_id',
                't2.estate_name',
                DB::raw("count(case when t1.floor = 'Ground' then t1.flat_id end) as floor_0"),
                DB::raw("count(case when t1.floor = 'First' then t1.flat_id end) as floor_1"),
                DB::raw("count(case when t1.floor = 'Second' then t1.flat_id end) as floor_2"),
                DB::raw("count(case when t1.floor = 'Third' then t1.flat_id end) as floor_3"),
                DB::raw("count(case when t1.floor = 'Fourth' then t1.flat_id end) as floor_4"),
                DB::raw("count(case when t1.floor = 'Fifth' then t1.flat_id end) as floor_5"),
                DB::raw("count(case when t1.floor = 'Sixth' then t1.flat_id end) as floor_6"),
                DB::raw("count(case when t1.floor = 'Seventh' then t1.flat_id end) as floor_7"),
                DB::raw("count(case when t1.floor = 'Eighth' then t1.flat_id end) as floor_8"),
                DB::raw("count(case when t1.floor = 'Ninth' then t1.flat_id end) as floor_9"),
                DB::raw("count(case when t1.floor = 'Top' then t1.flat_id end) as floor_top")
            )
            ->groupBy('t2.estate_id', 't2.estate_name')
            ->orderBy('t2.estate_name');

        if ($districtCode > 0) {
            $query->where('t3.district_code', $districtCode);
        }

        return $query->get();
    }

    /**
     * Get number of VS (Vertical Shifting) applicants for a flat type and estate
     *
     * @param int $allotmentType Flat type ID
     * @param int $occupationEstate Estate ID
     * @return int
     */
    public static function getNoOfApplicantVs($allotmentType, $occupationEstate)
    {
        if (empty($allotmentType) || empty($occupationEstate)) {
            return 0;
        }

        $count = DB::table('housing_online_application as t1')
            ->join('housing_vs_application as t2', 't1.online_application_id', '=', 't2.online_application_id')
            ->join('housing_applicant_official_detail as t3', 't1.applicant_official_detail_id', '=', 't3.applicant_official_detail_id')
            ->where('t1.status', 'housingapprover_approved_1')
            ->whereNotNull('t3.hrms_id')
            ->where('t2.flat_type_id', $allotmentType)
            ->where('t2.occupation_estate', $occupationEstate)
            ->count();

        return $count;
    }

    /**
     * Get number of CS (Category Shifting) applicants for a flat type and estate
     *
     * @param int $allotmentType Flat type ID
     * @param int $occupationEstate Estate ID
     * @return int
     */
    public static function getNoOfApplicantCs($allotmentType, $occupationEstate)
    {
        if (empty($allotmentType) || empty($occupationEstate)) {
            return 0;
        }

        $count = DB::table('housing_online_application as t1')
            ->join('housing_cs_application as t2', 't1.online_application_id', '=', 't2.online_application_id')
            ->join('housing_applicant_official_detail as t3', 't1.applicant_official_detail_id', '=', 't3.applicant_official_detail_id')
            ->where('t1.status', 'housingapprover_approved_1')
            ->whereNotNull('t3.hrms_id')
            ->where('t2.flat_type_id', $allotmentType)
            ->where('t2.occupation_estate', $occupationEstate)
            ->count();

        return $count;
    }

    /**
     * Get number of New applicants for a flat type
     *
     * @param int $allotmentType Flat type ID
     * @return int
     */
    public static function getNoOfApplicantNew($allotmentType)
    {
        if (empty($allotmentType)) {
            return 0;
        }

        $count = DB::table('housing_online_application as t1')
            ->join('housing_new_allotment_application as t2', 't1.online_application_id', '=', 't2.online_application_id')
            ->join('housing_applicant_official_detail as t3', 't1.applicant_official_detail_id', '=', 't3.applicant_official_detail_id')
            ->where('t1.status', 'housingapprover_approved_1')
            ->whereNotNull('t3.hrms_id')
            ->where('t2.flat_type_id', $allotmentType)
            ->count();

        return $count;
    }

    /**
     * Get total applicant count (VS + CS + New) for a flat type
     *
     * @param int $allotmentType Flat type ID
     * @return bool Returns true if there are any applicants, false otherwise
     */
    public static function getApplicantTotalCount($allotmentType)
    {
        if (empty($allotmentType)) {
            return false;
        }

        $newCount = self::getNoOfApplicantNew($allotmentType);
        
        // Get all estates for this flat type to check VS and CS applicants
        $estates = DB::table('housing_estate')->pluck('estate_id');
        
        $vsCount = 0;
        $csCount = 0;
        foreach ($estates as $estateId) {
            $vsCount += self::getNoOfApplicantVs($allotmentType, $estateId);
            $csCount += self::getNoOfApplicantCs($allotmentType, $estateId);
        }

        return ($newCount > 0 || $csCount > 0 || $vsCount > 0);
    }

    /**
     * Get vacancy details for VS or CS applicants
     *
     * @param int $allotmentType Flat type ID
     * @param int $estateId Estate ID
     * @param string $applicationType 'vs' or 'cs'
     * @param int $districtCode District code
     * @return array
     */
    public static function getVacancyDetails($allotmentType, $estateId, $applicationType, $districtCode = 0)
    {
        $vacancyDetails = [];
        
        // Floor constraints for VS and CS
        $floorConstraints = [
            'vs' => ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth', 'Seventh', 'Eighth', 'Ninth'],
            'cs' => ['Ground', 'Top']
        ];

        $floors = $floorConstraints[$applicationType] ?? [];

        if (empty($floors)) {
            return $vacancyDetails;
        }

        $query = DB::table('housing_flat as t1')
            ->join('housing_estate as t2', 't1.estate_id', '=', 't2.estate_id')
            ->join('housing_district as t3', 't2.district_code', '=', 't3.district_code')
            ->where('t1.flat_type_id', $allotmentType)
            ->where('t1.flat_status_id', 1) // Ready to allot
            ->whereIn('t1.floor', $floors);

        if ($estateId) {
            $query->where('t2.estate_id', $estateId);
        }

        if ($districtCode > 0) {
            $query->where('t3.district_code', $districtCode);
        }

        $results = $query->select('t1.estate_id', 't1.flat_id', 't1.flat_type_id')
            ->orderBy('t1.flat_id')
            ->get();

        foreach ($results as $record) {
            $vacancyDetails[] = [
                'estate_id' => $record->estate_id,
                'flat_id' => $record->flat_id,
                'flat_type_id' => $record->flat_type_id,
            ];
        }

        return $vacancyDetails;
    }

    /**
     * Get total vacancy for new applicants
     *
     * @param int $allotmentType Flat type ID
     * @param int $districtCode District code
     * @return int
     */
    public static function getTotalVacancyNew($allotmentType, $districtCode = 0)
    {
        $query = DB::table('housing_flat as t1')
            ->join('housing_estate as t2', 't1.estate_id', '=', 't2.estate_id')
            ->join('housing_district as t3', 't2.district_code', '=', 't3.district_code')
            ->where('t1.flat_type_id', $allotmentType)
            ->where('t1.flat_status_id', 1); // Ready to allot

        if ($districtCode > 0) {
            $query->where('t3.district_code', $districtCode);
        }

        return $query->count();
    }

    /**
     * Get next applicant for VS or CS based on seniority
     *
     * @param int $estateId Estate ID
     * @param int $flatTypeId Flat type ID
     * @param string $shiftingType 'vs' or 'cs'
     * @return object|null
     */
    public static function getApplicant($estateId, $flatTypeId, $shiftingType)
    {
        $shiftingTables = [
            'vs' => 'housing_vs_application',
            'cs' => 'housing_cs_application',
        ];

        if (!isset($shiftingTables[$shiftingType])) {
            return null;
        }

        $table = $shiftingTables[$shiftingType];

        $applicant = DB::table('housing_online_application as hoa')
            ->join('housing_applicant_official_detail as haod', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
            ->join($table . ' as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
            ->where('hoa.status', 'housingapprover_approved_1')
            ->where('hca.occupation_estate', $estateId)
            ->where('hca.flat_type_id', $flatTypeId)
            ->whereNotNull('haod.hrms_id')
            ->select(
                'hoa.online_application_id',
                'hca.flat_type_id',
                'hca.occupation_estate',
                'hca.occupation_flat',
                'hoa.date_of_application'
            )
            ->orderBy('hoa.date_of_application', 'ASC')
            ->orderBy('hoa.online_application_id', 'ASC')
            ->first();

        return $applicant;
    }

    /**
     * Get vacancy based on applicant preference
     *
     * @param int $flatTypeId Flat type ID
     * @param int $onlineApplicationId Application ID
     * @param int $districtCode District code
     * @return object|null
     */
    public static function getVacancyOnPreference($flatTypeId, $onlineApplicationId, $districtCode = 0)
    {
        $query = DB::table('housing_flat as t1')
            ->join('housing_estate as t2', 't1.estate_id', '=', 't2.estate_id')
            ->join('housing_district as t3', 't2.district_code', '=', 't3.district_code')
            ->join(DB::raw('(select preference_order, estate_id from housing_new_application_estate_preferences
                where online_application_id = ' . (int)$onlineApplicationId . '
                ORDER BY preference_order ASC
                limit 3) as t4'), 't2.estate_id', '=', 't4.estate_id')
            ->where('t1.flat_type_id', $flatTypeId)
            ->where('t1.flat_status_id', 1) // Ready to allot
            ->select('t1.*')
            ->orderBy('t4.preference_order')
            ->orderBy('t1.flat_id');

        if ($districtCode > 0) {
            $query->where('t3.district_code', $districtCode);
        }

        return $query->first();
    }

    /**
     * Get vacancy details for new applicants (ordered by floor preference)
     *
     * @param int $flatTypeId Flat type ID
     * @param int $districtCode District code
     * @return object|null
     */
    public static function getVacancyDetailsNew($flatTypeId, $districtCode = 0)
    {
        $query = DB::table('housing_flat as t1')
            ->join('housing_estate as t2', 't1.estate_id', '=', 't2.estate_id')
            ->join('housing_district as t3', 't2.district_code', '=', 't3.district_code')
            ->where('t1.flat_type_id', $flatTypeId)
            ->where('t1.flat_status_id', 1) // Ready to allot
            ->select('t1.*')
            ->orderByRaw("
                CASE
                    when t1.floor = 'First' then 1
                    when t1.floor = 'Second' then 2
                    when t1.floor = 'Third' then 3
                    when t1.floor = 'Fourth' then 4
                    when t1.floor = 'Fifth' then 5
                    when t1.floor = 'Sixth' then 6
                    when t1.floor = 'Seventh' then 7
                    when t1.floor = 'Eighth' then 8
                    when t1.floor = 'Ninth' then 9
                    when t1.floor = 'Ground' then 0
                    when t1.floor = 'Top' then 10
                END,
                t1.floor
            ");

        if ($districtCode > 0) {
            $query->where('t3.district_code', $districtCode);
        }

        return $query->first();
    }

    /**
     * Get next roaster counter for new applicant allotment
     *
     * @param int $flatTypeId Flat type ID
     * @return int
     */
    public static function getNextRoasterCounter($flatTypeId)
    {
        $roasterCounterStart = 1;
        $roasterCounterEnd = 29;
        $nextRoasterCounter = $roasterCounterStart;

        // Get the last roaster counter - use max() to avoid primary key dependency
        $lastCounter = DB::table('housing_allotment_roaster_counter')
            ->where('allotment_type', $flatTypeId)
            ->max('last_roaster_counter');

        if ($lastCounter) {
            if ($lastCounter < $roasterCounterEnd) {
                $nextRoasterCounter = $lastCounter + 1;
            } else {
                $nextRoasterCounter = $roasterCounterStart;
            }
        }

        return $nextRoasterCounter;
    }

    /**
     * Get next applicant for new allotment based on roaster
     *
     * @param int $flatTypeId Flat type ID
     * @param int $nextRoasterCounter Roaster counter
     * @return array ['is_spl_recomended' => bool, 'result' => object|null]
     */
    public static function getApplicantNew($flatTypeId, $nextRoasterCounter)
    {
        $roasterTables = [
            1 => 'housing_roaster4ab_master', // A
            2 => 'housing_roaster4ab_master', // B
            3 => 'housing_roaster4cd_master', // C
            4 => 'housing_roaster4cd_master', // D
            5 => 'housing_roasteraplus_master', // A+
        ];

        $roasterTable = $roasterTables[$flatTypeId] ?? null;

        if (!$roasterTable) {
            return ['is_spl_recomended' => 0, 'result' => null];
        }

        // Get category from roaster table
        $roasterCategory = DB::table($roasterTable)
            ->where('counter', $nextRoasterCounter)
            ->value('category');

        $isSpecialRecommended = 0;
        $result = null;

        // Check if category is 'Recommended' (special recommendation)
        if ($roasterCategory === 'Recommended') {
            $specialRecommended = DB::table('housing_special_recommended')
                ->orderBy('priority_order', 'ASC')
                ->first();

            if ($specialRecommended) {
                // Get applicant from special recommendation
                $result = DB::table('housing_online_application as t1')
                    ->join('housing_new_allotment_application as t2', 't1.online_application_id', '=', 't2.online_application_id')
                    ->join('housing_applicant_official_detail as t3', 't1.applicant_official_detail_id', '=', 't3.applicant_official_detail_id')
                    ->where('t1.status', 'housingapprover_approved_1')
                    ->where('t2.flat_type_id', $flatTypeId)
                    ->whereNotNull('t3.hrms_id')
                    ->where('t3.hrms_id', '!=', 0)
                    ->where('t1.online_application_id', $specialRecommended->housing_online_application_id)
                    ->select('t1.*')
                    ->orderByRaw("
                        NULLIF(regexp_replace(t1.computer_serial_no, '[^0-9]', '', 'g'), '')::INTEGER ASC,
                        regexp_replace(t1.computer_serial_no, '[0-9]', '', 'g') ASC
                    ")
                    ->first();

                $isSpecialRecommended = 1;
            }
        }

        // If no special recommended applicant found, get regular applicant
        if (!$result) {
            $result = DB::table('housing_online_application as t1')
                ->join('housing_new_allotment_application as t2', 't1.online_application_id', '=', 't2.online_application_id')
                ->join('housing_applicant_official_detail as t3', 't1.applicant_official_detail_id', '=', 't3.applicant_official_detail_id')
                ->where('t1.status', 'housingapprover_approved_1')
                ->where('t2.flat_type_id', $flatTypeId)
                ->whereNotNull('t3.hrms_id')
                ->where('t3.hrms_id', '!=', 0)
                ->where('t2.allotment_category', $roasterCategory)
                ->select('t1.*', 't2.allotment_category')
                ->orderByRaw("
                    NULLIF(regexp_replace(t1.computer_serial_no, '[^0-9]', '', 'g'), '')::INTEGER ASC,
                    regexp_replace(t1.computer_serial_no, '[0-9]', '', 'g') ASC
                ")
                ->first();

            $isSpecialRecommended = 0;
        }

        return [
            'is_spl_recomended' => $isSpecialRecommended,
            'result' => $result
        ];
    }

    /**
     * Get next allotment process number
     *
     * @param int $allotmentType Flat type ID
     * @return string
     */
    public static function getNextAllotmentProcessNo($allotmentType)
    {
        $exists = DB::table('housing_allotment_process')
            ->where('allotment_process_type', 'ALOT')
            ->exists();

        $allotmentProcessNo = 'ALOT-01';

        if ($exists) {
            $maxNo = DB::table('housing_allotment_process')
                ->where('allotment_process_type', 'ALOT')
                ->selectRaw("max(substring(allotment_process_no, 6))::integer as max_no")
                ->value('max_no');

            if ($maxNo) {
                $nextNo = $maxNo + 1;
                if ($nextNo < 10) {
                    $allotmentProcessNo = 'ALOT-0' . $nextNo;
                } else {
                    $allotmentProcessNo = 'ALOT-' . $nextNo;
                }
            }
        }

        return $allotmentProcessNo;
    }

    /**
     * Update housing online application status to 'allotted'
     *
     * @param int $onlineApplicationId
     * @param int $uid User ID
     * @return void
     */
    public static function updateHousingOnlineApplication($onlineApplicationId, $uid)
    {
        DB::table('housing_online_application')
            ->where('online_application_id', $onlineApplicationId)
            ->update([
                'status' => 'allotted',
                'date_of_verified' => now(),
            ]);

        // Insert into process flow
        ProcessFlowService::insertProcessFlow($onlineApplicationId, 'allotted', $uid);
    }

    /**
     * Update housing flat status to 'alloted' (status 7)
     *
     * @param int $flatId
     * @return void
     */
    public static function updateHousingFlat($flatId)
    {
        DB::table('housing_flat')
            ->where('flat_id', $flatId)
            ->update(['flat_status_id' => 7]); // 7 = Alloted (changed from 2 = Occupied)
    }

    /**
     * Create housing flat occupant record
     *
     * @param int $flatId
     * @param int $onlineApplicationId
     * @param string $allotmentNoPrefix Prefix (VSAL, CSAL, NAL)
     * @return int Flat occupant ID
     */
    public static function updateHousingFlatOccupant($flatId, $onlineApplicationId, $allotmentNoPrefix)
    {
        $allotmentProcessNo = DB::table('housing_allotment_process')
            ->where('allotment_process_type', 'ALOT')
            ->orderBy('allotment_process_id', 'desc')
            ->value('allotment_process_no');

        $allotmentNo = $allotmentNoPrefix . '-' . $onlineApplicationId . '-' . date('dmY');

        // Insert and get the primary key (flat_occupant_id) using PostgreSQL RETURNING clause
        $flatOccupantId = DB::selectOne(
            "INSERT INTO housing_flat_occupant (online_application_id, flat_id, allotment_no, allotment_process_no, allotment_date, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?) 
             RETURNING flat_occupant_id",
            [
                $onlineApplicationId,
                $flatId,
                $allotmentNo,
                $allotmentProcessNo,
                now()->format('Y-m-d'),
                now(),
                now(),
            ]
        );

        return $flatOccupantId->flat_occupant_id;
    }

    /**
     * Update housing allotment roaster counter
     *
     * @param int $allotmentType
     * @param int $nextRoasterCounter
     * @return void
     */
    public static function updateHousingAllotmentRoasterCounter($allotmentType, $nextRoasterCounter)
    {
        DB::table('housing_allotment_roaster_counter')->insert([
            'allotment_type' => $allotmentType,
            'last_roaster_counter' => $nextRoasterCounter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update housing allotment roaster details
     *
     * @param int $allotmentType
     * @param int $nextRoasterCounter
     * @param string $allotmentCategory
     * @param int $flatOccupantId
     * @return void
     */
    public static function updateHousingAllotmentRoasterDetails($allotmentType, $nextRoasterCounter, $allotmentCategory, $flatOccupantId)
    {
        $allotmentProcessNo = DB::table('housing_allotment_process')
            ->where('allotment_flat_type', $allotmentType)
            ->orderBy('allotment_process_id', 'desc')
            ->value('allotment_process_no');

        DB::table('housing_allotment_roaster_details')->insert([
            'allotment_process_no' => $allotmentProcessNo,
            'allotment_flat_type' => $allotmentType,
            'roaster_vacancy_position' => $nextRoasterCounter,
            'allotment_reason' => $allotmentCategory,
            'roaster_list_no' => null,
            'flat_occupant_id' => $flatOccupantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update housing special recommendation (move to log and delete)
     *
     * @param int $onlineApplicationId
     * @return void
     */
    public static function updateHousingSpecialRecommendation($onlineApplicationId)
    {
        $specialRecommended = DB::table('housing_special_recommended')
            ->where('housing_online_application_id', $onlineApplicationId)
            ->first();

        if (!$specialRecommended) {
            return;
        }

        // Insert into log table
        DB::table('housing_special_recommended_log')->insert([
            'special_recommend_id' => $specialRecommended->special_recommend_id,
            'housing_online_application_id' => $specialRecommended->housing_online_application_id,
            'priority_order' => $specialRecommended->priority_order,
            'flag' => 'alloted',
            'old_category' => $specialRecommended->old_category,
            'new_category' => $specialRecommended->new_category,
            'created_at_housing_special_recommended' => $specialRecommended->created_at,
            'updated_at_housing_special_recommended' => $specialRecommended->updated_at,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $priorityOrder = $specialRecommended->priority_order;

        // Delete from special recommended table
        DB::table('housing_special_recommended')
            ->where('special_recommend_id', $specialRecommended->special_recommend_id)
            ->delete();

        // Update priority order for remaining records
        $maxOrder = DB::table('housing_special_recommended')
            ->max('priority_order') ?? 0;

        for ($i = $priorityOrder + 1; $i <= $maxOrder; $i++) {
            DB::table('housing_special_recommended')
                ->where('priority_order', $i)
                ->update(['priority_order' => $i - 1]);
        }
    }

    /**
     * Create or update housing allotment process
     *
     * @param int $allotmentType
     * @return string Allotment process number
     */
    public static function updateHousingAllotmentProcess($allotmentType)
    {
        $allotmentProcessNo = self::getNextAllotmentProcessNo($allotmentType);

        // Insert the allotment process record
        DB::table('housing_allotment_process')->insert([
            'allotment_process_no' => $allotmentProcessNo,
            'allotment_process_type' => 'ALOT',
            'allotment_flat_type' => $allotmentType,
            'allotment_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // We don't need the ID, just return the process number
        return $allotmentProcessNo;
    }

    /**
     * Process VS (Vertical Shifting) allotment for an estate
     *
     * @param int $allotmentType
     * @param int $estateId
     * @param int $districtCode
     * @param int $uid User ID
     * @return void
     */
    public static function processVsAllotment($allotmentType, $estateId, $districtCode, $uid)
    {
        $totalApplicant = self::getNoOfApplicantVs($allotmentType, $estateId);

        if ($totalApplicant <= 0) {
            return;
        }

        $vacancyDetails = self::getVacancyDetails($allotmentType, $estateId, 'vs', $districtCode);

        foreach ($vacancyDetails as $vacancy) {
            $flatId = $vacancy['flat_id'];
            $flatTypeId = $vacancy['flat_type_id'];

            // Get next applicant by seniority
            $applicant = self::getApplicant($estateId, $flatTypeId, 'vs');

            if ($applicant) {
                self::updateHousingOnlineApplication($applicant->online_application_id, $uid);
                self::updateHousingFlat($flatId);
                self::updateHousingFlatOccupant($flatId, $applicant->online_application_id, 'VSAL');
            }
        }
    }

    /**
     * Process CS (Category Shifting) allotment for an estate
     *
     * @param int $allotmentType
     * @param int $estateId
     * @param int $districtCode
     * @param int $uid User ID
     * @return void
     */
    public static function processCsAllotment($allotmentType, $estateId, $districtCode, $uid)
    {
        $totalApplicant = self::getNoOfApplicantCs($allotmentType, $estateId);
        $csAllotedCounter = 1;

        if ($totalApplicant <= 0) {
            return;
        }

        // Check for remaining VS vacancies (floors 1-9) after VS allotment
        $vacancyDetailsVs = self::getVacancyDetails($allotmentType, $estateId, 'vs', $districtCode);
        $countVacancyDetailsVs = count($vacancyDetailsVs);

        // Allot 40% of remaining 1-9 floor vacancies for CS
        if ($countVacancyDetailsVs > 1) {
            $csCountFromVs = round($countVacancyDetailsVs * 0.4);

            foreach ($vacancyDetailsVs as $vacancy) {
                if ($csAllotedCounter > $csCountFromVs) {
                    break;
                }

                $flatId = $vacancy['flat_id'];
                $flatTypeId = $vacancy['flat_type_id'];

                $applicant = self::getApplicant($estateId, $flatTypeId, 'cs');

                if ($applicant) {
                    self::updateHousingOnlineApplication($applicant->online_application_id, $uid);
                    self::updateHousingFlat($flatId);
                    self::updateHousingFlatOccupant($flatId, $applicant->online_application_id, 'CSAL');
                    $csAllotedCounter++;
                }
            }
        }

        // Allot remaining CS applicants to Ground and Top floors
        if (--$csAllotedCounter < $totalApplicant) {
            $vacancyDetails = self::getVacancyDetails($allotmentType, $estateId, 'cs', $districtCode);

            foreach ($vacancyDetails as $vacancy) {
                $flatId = $vacancy['flat_id'];
                $flatTypeId = $vacancy['flat_type_id'];

                $applicant = self::getApplicant($estateId, $flatTypeId, 'cs');

                if ($applicant) {
                    self::updateHousingOnlineApplication($applicant->online_application_id, $uid);
                    self::updateHousingFlat($flatId);
                    self::updateHousingFlatOccupant($flatId, $applicant->online_application_id, 'CSAL');
                }
            }
        }
    }

    /**
     * Process New applicant allotment
     *
     * @param int $allotmentType
     * @param int $districtCode
     * @param int $uid User ID
     * @return void
     */
    public static function processNewAllotment($allotmentType, $districtCode, $uid)
    {
        $roasterCounterStart = 1;
        $roasterCounterEnd = 29;
        $applicantAllottedCounter = 0;
        $totalApplicant = self::getNoOfApplicantNew($allotmentType);

        while ($applicantAllottedCounter < $totalApplicant) {
            $nextRoasterCounter = self::getNextRoasterCounter($allotmentType);

            while (true) {
                $applicantData = self::getApplicantNew($allotmentType, $nextRoasterCounter);
                $applicant = $applicantData['result'];
                $isSpecialRecommended = $applicantData['is_spl_recomended'];

                if ($applicant) {
                    // Try to get vacancy based on preference first
                    $vacancy = self::getVacancyOnPreference($allotmentType, $applicant->online_application_id, $districtCode);

                    // If no preference vacancy, get general vacancy
                    if (!$vacancy) {
                        $vacancy = self::getVacancyDetailsNew($allotmentType, $districtCode);
                    }

                    // If no vacancy available, skip this applicant
                    if (!$vacancy) {
                        $applicantAllottedCounter++;
                        break;
                    }

                    $flatId = $vacancy->flat_id;

                    // Update application, flat, and create occupant
                    self::updateHousingOnlineApplication($applicant->online_application_id, $uid);
                    self::updateHousingFlat($flatId);
                    $flatOccupantId = self::updateHousingFlatOccupant($flatId, $applicant->online_application_id, 'NAL');

                    // Handle special recommendation if applicable
                    if ($isSpecialRecommended == 1) {
                        self::updateHousingSpecialRecommendation($applicant->online_application_id);
                    }

                    // Update roaster counter and details
                    self::updateHousingAllotmentRoasterCounter($allotmentType, $nextRoasterCounter);
                    self::updateHousingAllotmentRoasterDetails(
                        $allotmentType,
                        $nextRoasterCounter,
                        $applicant->allotment_category ?? 'General',
                        $flatOccupantId
                    );

                    $applicantAllottedCounter++;
                    break;
                } else {
                    // Move to next roaster counter
                    if ($nextRoasterCounter < $roasterCounterEnd) {
                        $nextRoasterCounter++;
                    } else {
                        $nextRoasterCounter = $roasterCounterStart;
                    }
                }
            }
        }
    }

    /**
     * Process complete allotment for a flat type
     *
     * @param int $allotmentType
     * @param int $districtCode
     * @param int $uid User ID
     * @return array ['success' => bool, 'process_no' => string, 'message' => string]
     */
    public static function processAllotment($allotmentType, $districtCode, $uid)
    {
        try {
            DB::beginTransaction();

            // Check if there are any applicants
            $hasApplicants = self::getApplicantTotalCount($allotmentType);
            if (!$hasApplicants) {
                return [
                    'success' => false,
                    'process_no' => null,
                    'message' => 'No Application Available for Allotment'
                ];
            }

            // Create allotment process
            $processNo = self::updateHousingAllotmentProcess($allotmentType);

            // Get all vacant estates
            $allVacantEstates = self::getEstatewiseVacancy($allotmentType, $districtCode);

            // Process VS and CS for each estate
            foreach ($allVacantEstates as $estate) {
                $estateId = $estate->estate_id;
                self::processVsAllotment($allotmentType, $estateId, $districtCode, $uid);
                self::processCsAllotment($allotmentType, $estateId, $districtCode, $uid);
            }

            // Process New applicants
            self::processNewAllotment($allotmentType, $districtCode, $uid);

            DB::commit();

            return [
                'success' => true,
                'process_no' => $processNo,
                'message' => 'Allotment Process Completed Successfully with Process No ' . $processNo
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RHE Allotment Process Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'allotment_type' => $allotmentType,
                'district_code' => $districtCode,
            ]);

            return [
                'success' => false,
                'process_no' => null,
                'message' => 'Allotment Process Failed: ' . $e->getMessage()
            ];
        }
    }
}

