<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftingAllotmentListController extends Controller
{
    /**
     * Get VS Allotment Process Dates
     * GET /api/shifting-allotment-list/vs/process-dates
     */
    public function getVsProcessDates()
    {
        try {
            $dates = DB::table('housing_allotment_process')
                ->where('allotment_process_type', 'VSAL')
                ->select('allotment_date')
                ->distinct()
                ->orderBy('allotment_date', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->allotment_date,
                        'label' => implode('/', array_reverse(explode('-', $item->allotment_date)))
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $dates,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get VS Process Dates Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch VS process dates',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get VS Allotment Process Numbers by Date
     * GET /api/shifting-allotment-list/vs/process-numbers
     */
    public function getVsProcessNumbers(Request $request)
    {
        try {
            $request->validate([
                'allotment_process_date' => 'required|date'
            ]);

            $processNos = DB::table('housing_allotment_process')
                ->where('allotment_date', $request->allotment_process_date)
                ->where('allotment_process_type', 'VSAL')
                ->select('allotment_process_id', 'allotment_process_no')
                ->orderBy('allotment_process_no', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->allotment_process_no,
                        'label' => $item->allotment_process_no
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $processNos,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get VS Process Numbers Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch VS process numbers',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get CS Allotment Process Dates
     * GET /api/shifting-allotment-list/cs/process-dates
     */
    public function getCsProcessDates()
    {
        try {
            $dates = DB::table('housing_allotment_process')
                ->where('allotment_process_type', 'CSAL')
                ->select('allotment_date')
                ->distinct()
                ->orderBy('allotment_date', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->allotment_date,
                        'label' => implode('/', array_reverse(explode('-', $item->allotment_date)))
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $dates,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get CS Process Dates Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch CS process dates',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get CS Allotment Process Numbers by Date
     * GET /api/shifting-allotment-list/cs/process-numbers
     */
    public function getCsProcessNumbers(Request $request)
    {
        try {
            $request->validate([
                'allotment_process_date' => 'required|date'
            ]);

            $processNos = DB::table('housing_allotment_process')
                ->where('allotment_date', $request->allotment_process_date)
                ->where('allotment_process_type', 'CSAL')
                ->select('allotment_process_id', 'allotment_process_no')
                ->orderBy('allotment_process_no', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->allotment_process_no,
                        'label' => $item->allotment_process_no
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $processNos,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get CS Process Numbers Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch CS process numbers',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get VS Allottee List
     * GET /api/shifting-allotment-list/vs/allottees
     */
    public function getVsAllotteeList(Request $request)
    {
        try {
            $request->validate([
                'allotment_process_date' => 'required|date',
                'allotment_process_no' => 'required|string'
            ]);

            $allotmentProcessDate = $request->allotment_process_date;
            $allotmentProcessNo = $request->allotment_process_no;

            $allottees = DB::table('housing_online_application as hoa')
                ->join('housing_vs_application as hva', 'hva.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->where(function($query) {
                    $query->where('hoa.status', 'allotted')
                        ->orWhere('hoa.status', 'reject_offer');
                })
                ->whereNotNull('hfo.online_application_id')
                ->where('hfo.allotment_date', $allotmentProcessDate)
                ->where('hfo.allotment_process_no', $allotmentProcessNo)
                ->select(
                    'hoa.online_application_id',
                    'hoa.date_of_application',
                    'hoa.application_no',
                    'hfo.allotment_no',
                    'hfo.allotment_date',
                    'hf.flat_no',
                    'hft.flat_type'
                )
                ->orderBy('hfo.flat_occupant_id', 'ASC')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $allottees,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get VS Allottee List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch VS allottee list',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get CS Allottee List
     * GET /api/shifting-allotment-list/cs/allottees
     */
    public function getCsAllotteeList(Request $request)
    {
        try {
            $request->validate([
                'allotment_process_date' => 'required|date',
                'allotment_process_no' => 'required|string'
            ]);

            $allotmentProcessDate = $request->allotment_process_date;
            $allotmentProcessNo = $request->allotment_process_no;

            $allottees = DB::table('housing_online_application as hoa')
                ->join('housing_cs_application as hca', 'hca.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat_occupant as hfo', 'hfo.online_application_id', '=', 'hoa.online_application_id')
                ->join('housing_flat as hf', 'hf.flat_id', '=', 'hfo.flat_id')
                ->join('housing_flat_type as hft', 'hf.flat_type_id', '=', 'hft.flat_type_id')
                ->where(function($query) {
                    $query->where('hoa.status', 'allotted')
                        ->orWhere('hoa.status', 'reject_offer');
                })
                ->whereNotNull('hfo.online_application_id')
                ->where('hfo.allotment_date', $allotmentProcessDate)
                ->where('hfo.allotment_process_no', $allotmentProcessNo)
                ->select(
                    'hoa.online_application_id',
                    'hoa.date_of_application',
                    'hoa.application_no',
                    'hfo.allotment_no',
                    'hfo.allotment_date',
                    'hf.flat_no',
                    'hft.flat_type'
                )
                ->orderBy('hfo.flat_occupant_id', 'ASC')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $allottees,
                'status_code' => 200
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get CS Allottee List Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch CS allottee list',
                'status_code' => 500
            ], 500);
        }
    }
}
