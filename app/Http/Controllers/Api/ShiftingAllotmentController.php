<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShiftingAllotmentController extends Controller
{
    /**
     * Get RHE list for VS Allotment
     * GET /api/shifting-allotment/vs/rhe-list
     */
    public function getVsRheList()
    {
        try {
            $floor = ['F', 'S'];
            
            $rhes = DB::table('housing_flat as hf')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                ->whereIn('hf.floor', $floor)
                ->where('hf.flat_status_id', 1) // vacant
                ->where('hf.flat_category_id', 3) // VS category
                ->select('he.estate_id', 'he.estate_name')
                ->distinct()
                ->orderBy('hf.flat_id', 'ASC')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->estate_id,
                        'label' => $item->estate_name
                    ];
                })
                ->values()
                ->toArray();

            return response()->json([
                'status' => 'success',
                'data' => $rhes
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get VS RHE List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch RHE list',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get RHE list for CS Allotment
     * GET /api/shifting-allotment/cs/rhe-list
     */
    public function getCsRheList()
    {
        try {
            $floor = ['F', 'S'];
            
            $rhes = DB::table('housing_flat as hf')
                ->join('housing_estate as he', 'he.estate_id', '=', 'hf.estate_id')
                ->whereIn('hf.floor', $floor)
                ->where('hf.flat_status_id', 1) // vacant
                ->where('hf.flat_category_id', 2) // CS category
                ->select('he.estate_id', 'he.estate_name')
                ->distinct()
                ->orderBy('hf.flat_id', 'ASC')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->estate_id,
                        'label' => $item->estate_name
                    ];
                })
                ->values()
                ->toArray();

            return response()->json([
                'status' => 'success',
                'data' => $rhes
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get CS RHE List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch RHE list',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get vacancy count for VS Allotment
     * GET /api/shifting-allotment/vs/vacancy-count
     */
    public function getVsVacancyCount(Request $request)
    {
        try {
            $request->validate([
                'rhe_id' => 'required|integer'
            ]);

            $rheId = $request->input('rhe_id');
            $floor = ['F', 'S'];

            $count = DB::table('housing_flat')
                ->where('estate_id', $rheId)
                ->whereIn('floor', $floor)
                ->where('flat_category_id', 3) // VS category
                ->where('flat_status_id', 1) // vacant
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'vacancy_count' => $count
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get VS Vacancy Count Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch vacancy count',
                'data' => ['vacancy_count' => 0]
            ], 500);
        }
    }

    /**
     * Get vacancy count for CS Allotment
     * GET /api/shifting-allotment/cs/vacancy-count
     */
    public function getCsVacancyCount(Request $request)
    {
        try {
            $request->validate([
                'rhe_id' => 'required|integer'
            ]);

            $rheId = $request->input('rhe_id');
            $floor = ['F', 'S'];

            $count = DB::table('housing_flat')
                ->where('estate_id', $rheId)
                ->whereIn('floor', $floor)
                ->where('flat_category_id', 2) // CS category
                ->where('flat_status_id', 1) // vacant
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'vacancy_count' => $count
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get CS Vacancy Count Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch vacancy count',
                'data' => ['vacancy_count' => 0]
            ], 500);
        }
    }

    /**
     * Get applicant count for VS Allotment
     * GET /api/shifting-allotment/vs/applicant-count
     */
    public function getVsApplicantCount(Request $request)
    {
        try {
            $request->validate([
                'rhe_id' => 'required|integer'
            ]);

            $rheId = $request->input('rhe_id');

            $count = DB::table('housing_online_application as hoa')
                ->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                ->where('hoa.status', 'verified')
                ->whereRaw("substring(hoa.application_no, '\\w+') = ?", ['VS'])
                ->where('hva.occupation_estate', $rheId)
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'applicant_count' => $count
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get VS Applicant Count Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch applicant count',
                'data' => ['applicant_count' => 0]
            ], 500);
        }
    }

    /**
     * Get applicant count for CS Allotment
     * GET /api/shifting-allotment/cs/applicant-count
     */
    public function getCsApplicantCount(Request $request)
    {
        try {
            $request->validate([
                'rhe_id' => 'required|integer'
            ]);

            $rheId = $request->input('rhe_id');

            $count = DB::table('housing_online_application as hoa')
                ->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->where('hoa.status', 'verified')
                ->whereRaw("substring(hoa.application_no, '\\w+') = ?", ['CS'])
                ->where('hca.occupation_estate', $rheId)
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'applicant_count' => $count
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get CS Applicant Count Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch applicant count',
                'data' => ['applicant_count' => 0]
            ], 500);
        }
    }

    /**
     * Process VS Allotment
     * POST /api/shifting-allotment/vs/process
     */
    public function processVsAllotment(Request $request)
    {
        try {
            $request->validate([
                'rhe_id' => 'required|integer',
                'uid' => 'nullable|integer'
            ]);

            $rheId = $request->input('rhe_id');
            $uid = $request->input('uid', 1);

            // Get vacancy and applicant counts
            $vacancyCount = $this->getVsVacancyCountData($rheId);
            $applicantCount = $this->getVsApplicantCountData($rheId);

            if ($vacancyCount <= 0 || $applicantCount <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No. of vacancy or No. of Applicant or both are Zero, Allotment not possible!!!',
                    'data' => []
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Get allotment process number
                $processNo = $this->getOrCreateAllotmentProcess('VSAL');

                // Get vacancy details
                $vacancyDetails = $this->getVsVacancyDetails($rheId, $vacancyCount);

                // Process allotment
                $allottedEmails = [];
                foreach ($vacancyDetails as $vacancy) {
                    $flatId = $vacancy['flat_id'];
                    $flatTypeId = $vacancy['flat_type_id'];

                    // Get matching applicant
                    $applicant = DB::selectOne("
                        SELECT hoa.online_application_id, hpb.flat_type_id, hva.occupation_estate, hva.occupation_flat
                        FROM housing_online_application hoa
                        INNER JOIN housing_applicant_official_detail haod ON haod.applicant_official_detail_id = hoa.applicant_official_detail_id
                        INNER JOIN housing_pay_band hpb ON hpb.pay_band_id = haod.pay_band_id
                        INNER JOIN housing_vs_application hva ON hva.online_application_id = hoa.online_application_id
                        WHERE hoa.status = ? 
                        AND substring(hoa.application_no, '\\w+') = ?
                        AND hva.occupation_estate = ?
                        AND hpb.flat_type_id = ?
                        ORDER BY hoa.online_application_id
                        LIMIT 1
                    ", ['verified', 'VS', $rheId, $flatTypeId]);

                    if ($applicant) {
                        // Update application status
                        DB::table('housing_online_application')
                            ->where('online_application_id', $applicant->online_application_id)
                            ->update(['status' => 'allotted']);

                        // Update new flat status to allotted
                        DB::table('housing_flat')
                            ->where('flat_id', $flatId)
                            ->update(['flat_status_id' => 2]); // allotted

                        // Update old flat status to vacant
                        DB::table('housing_flat')
                            ->where('flat_id', $applicant->occupation_flat)
                            ->update(['flat_status_id' => 1]); // vacant

                        // Create allotment record
                        $allotmentNo = 'VSAL-' . $applicant->online_application_id . '-' . date('dmY');
                        DB::table('housing_flat_occupant')->insert([
                            'online_application_id' => $applicant->online_application_id,
                            'flat_id' => $flatId,
                            'allotment_no' => $allotmentNo,
                            'allotment_process_no' => $processNo,
                            'allotment_date' => Carbon::now()->format('Y-m-d'),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        // Get email for notification
                        $userEmail = DB::table('users as u')
                            ->join('housing_applicant_official_detail as haod', 'u.uid', '=', 'haod.uid')
                            ->join('housing_online_application as hoa', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                            ->where('hoa.online_application_id', $applicant->online_application_id)
                            ->value('u.mail');

                        if ($userEmail) {
                            $allottedEmails[] = $userEmail;
                        }
                    }
                }

                DB::commit();

                // Send email notifications (if email service is configured)
                if (!empty($allottedEmails)) {
                    // TODO: Implement email sending
                    Log::info('VS Allotment emails to be sent', ['emails' => $allottedEmails]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Successfully done allotment',
                    'data' => [
                        'process_no' => $processNo,
                        'allotted_count' => count($allottedEmails)
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Process VS Allotment Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'rhe_id' => $request->input('rhe_id')
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Allotment process failed: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Process CS Allotment
     * POST /api/shifting-allotment/cs/process
     */
    public function processCsAllotment(Request $request)
    {
        try {
            $request->validate([
                'rhe_id' => 'required|integer',
                'uid' => 'nullable|integer'
            ]);

            $rheId = $request->input('rhe_id');
            $uid = $request->input('uid', 1);

            // Get vacancy and applicant counts
            $vacancyCount = $this->getCsVacancyCountData($rheId);
            $applicantCount = $this->getCsApplicantCountData($rheId);

            if ($vacancyCount <= 0 || $applicantCount <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No. of vacancy or No. of Applicant or both are Zero, Allotment not possible!!!',
                    'data' => []
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Get allotment process number
                $processNo = $this->getOrCreateAllotmentProcess('CSAL');

                // Get vacancy details
                $vacancyDetails = $this->getCsVacancyDetails($rheId, $vacancyCount);

                // Process allotment
                $allottedEmails = [];
                foreach ($vacancyDetails as $vacancy) {
                    $flatId = $vacancy['flat_id'];
                    $flatTypeId = $vacancy['flat_type_id'];

                    // Get matching applicant
                    $applicant = DB::selectOne("
                        SELECT hoa.online_application_id, hpb.flat_type_id, hca.occupation_estate, hca.occupation_flat
                        FROM housing_online_application hoa
                        INNER JOIN housing_applicant_official_detail haod ON haod.applicant_official_detail_id = hoa.applicant_official_detail_id
                        INNER JOIN housing_pay_band hpb ON hpb.pay_band_id = haod.pay_band_id
                        INNER JOIN housing_cs_application hca ON hca.online_application_id = hoa.online_application_id
                        WHERE hoa.status = ? 
                        AND substring(hoa.application_no, '\\w+') = ?
                        AND hca.occupation_estate = ?
                        AND hpb.flat_type_id = ?
                        ORDER BY hoa.online_application_id
                        LIMIT 1
                    ", ['verified', 'CS', $rheId, $flatTypeId]);

                    if ($applicant) {
                        // Update application status
                        DB::table('housing_online_application')
                            ->where('online_application_id', $applicant->online_application_id)
                            ->update(['status' => 'allotted']);

                        // Update new flat status to allotted
                        DB::table('housing_flat')
                            ->where('flat_id', $flatId)
                            ->update(['flat_status_id' => 2]); // allotted

                        // Update old flat status to vacant
                        DB::table('housing_flat')
                            ->where('flat_id', $applicant->occupation_flat)
                            ->update(['flat_status_id' => 1]); // vacant

                        // Create allotment record
                        $allotmentNo = 'CSAL-' . $applicant->online_application_id . '-' . date('dmY');
                        DB::table('housing_flat_occupant')->insert([
                            'online_application_id' => $applicant->online_application_id,
                            'flat_id' => $flatId,
                            'allotment_no' => $allotmentNo,
                            'allotment_process_no' => $processNo,
                            'allotment_date' => Carbon::now()->format('Y-m-d'),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        // Get email for notification
                        $userEmail = DB::table('users as u')
                            ->join('housing_applicant_official_detail as haod', 'u.uid', '=', 'haod.uid')
                            ->join('housing_online_application as hoa', 'haod.applicant_official_detail_id', '=', 'hoa.applicant_official_detail_id')
                            ->where('hoa.online_application_id', $applicant->online_application_id)
                            ->value('u.mail');

                        if ($userEmail) {
                            $allottedEmails[] = $userEmail;
                        }
                    }
                }

                DB::commit();

                // Send email notifications (if email service is configured)
                if (!empty($allottedEmails)) {
                    // TODO: Implement email sending
                    Log::info('CS Allotment emails to be sent', ['emails' => $allottedEmails]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Successfully done allotment',
                    'data' => [
                        'process_no' => $processNo,
                        'allotted_count' => count($allottedEmails)
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Process CS Allotment Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'rhe_id' => $request->input('rhe_id')
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Allotment process failed: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    // Helper methods

    private function getVsVacancyCountData($rheId)
    {
        $floor = ['F', 'S'];
        return DB::table('housing_flat')
            ->where('estate_id', $rheId)
            ->whereIn('floor', $floor)
            ->where('flat_category_id', 3)
            ->where('flat_status_id', 1)
            ->count();
    }

    private function getCsVacancyCountData($rheId)
    {
        $floor = ['F', 'S'];
        return DB::table('housing_flat')
            ->where('estate_id', $rheId)
            ->whereIn('floor', $floor)
            ->where('flat_category_id', 2)
            ->where('flat_status_id', 1)
            ->count();
    }

    private function getVsApplicantCountData($rheId)
    {
        return DB::table('housing_online_application as hoa')
            ->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
            ->where('hoa.status', 'verified')
            ->whereRaw("substring(hoa.application_no, '\\w+') = ?", ['VS'])
            ->where('hva.occupation_estate', $rheId)
            ->count();
    }

    private function getCsApplicantCountData($rheId)
    {
        return DB::table('housing_online_application as hoa')
            ->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
            ->where('hoa.status', 'verified')
            ->whereRaw("substring(hoa.application_no, '\\w+') = ?", ['CS'])
            ->where('hca.occupation_estate', $rheId)
            ->count();
    }

    private function getVsVacancyDetails($rheId, $noOfVacancy)
    {
        $floor = ['F', 'S'];
        $flats = DB::table('housing_flat')
            ->where('estate_id', $rheId)
            ->whereIn('floor', $floor)
            ->where('flat_status_id', 1)
            ->where('flat_category_id', 3)
            ->orderBy('flat_id', 'ASC')
            ->orderBy('floor', 'ASC')
            ->limit($noOfVacancy)
            ->get();

        $vacancyDetails = [];
        foreach ($flats as $flat) {
            $vacancyDetails[] = [
                'flat_id' => $flat->flat_id,
                'flat_type_id' => $flat->flat_type_id
            ];
        }

        return $vacancyDetails;
    }

    private function getCsVacancyDetails($rheId, $noOfVacancy)
    {
        $floor = ['F', 'S'];
        $flats = DB::table('housing_flat')
            ->where('estate_id', $rheId)
            ->whereIn('floor', $floor)
            ->where('flat_status_id', 1)
            ->where('flat_category_id', 2)
            ->orderBy('flat_id', 'ASC')
            ->orderBy('floor', 'ASC')
            ->limit($noOfVacancy)
            ->get();

        $vacancyDetails = [];
        foreach ($flats as $flat) {
            $vacancyDetails[] = [
                'flat_id' => $flat->flat_id,
                'flat_type_id' => $flat->flat_type_id
            ];
        }

        return $vacancyDetails;
    }

    private function getOrCreateAllotmentProcess($processType)
    {
        $existing = DB::table('housing_allotment_process')
            ->where('allotment_process_type', $processType)
            ->first();

        if (!$existing) {
            $processNo = $processType . '-01';
            DB::table('housing_allotment_process')->insert([
                'allotment_process_no' => $processNo,
                'allotment_process_type' => $processType,
                'allotment_date' => Carbon::now()->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            return $processNo;
        }

        $maxNo = DB::table('housing_allotment_process')
            ->where('allotment_process_type', $processType)
            ->selectRaw("max(substr(allotment_process_no, 6))::int as max_no")
            ->value('max_no');

        $nextNo = ($maxNo ?? 0) + 1;
        $processNo = $nextNo < 10 
            ? $processType . '-0' . $nextNo 
            : $processType . '-' . $nextNo;

        DB::table('housing_allotment_process')->insert([
            'allotment_process_no' => $processNo,
            'allotment_process_type' => $processType,
            'allotment_date' => Carbon::now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return $processNo;
    }
}
