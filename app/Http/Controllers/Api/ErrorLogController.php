<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use App\Services\ErrorLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ErrorLogController extends Controller
{
    /**
     * Display a listing of error logs.
     */
    public function index(Request $request)
    {
        try {
            // Check if table exists
            if (!Schema::hasTable('error_logs')) {
                // Return empty pagination response if table doesn't exist
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'data' => [],
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 0,
                        'from' => null,
                        'to' => null
                    ]
                ]);
            }

            $query = ErrorLog::query();

            // Load user relationship (left join so logs without users still show)
            $query->with(['user' => function($q) {
                // Make relationship optional
            }]);

            // Filter by level
            if ($request->has('level') && $request->level) {
                $query->where('level', $request->level);
            }

            // Search by message (case-insensitive)
            if ($request->has('search') && $request->search) {
                $searchTerm = strtolower($request->search);
                $query->whereRaw('LOWER(message) LIKE ?', ["%{$searchTerm}%"]);
            }

            // Filter by user
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Date range filter
            if ($request->has('date_from') && $request->date_from) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $logs = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data' => $logs
            ]);
        } catch (\Exception $e) {
            // Log to error_logs table
            ErrorLogService::logException($e, 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load error logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified error log.
     */
    public function show($id)
    {
        try {
            $log = ErrorLog::with('user')->find($id);

            if (!$log) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error log not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $log
            ]);
        } catch (\Exception $e) {
            // Log to error_logs table
            ErrorLogService::logException($e, 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load error log: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified error log.
     */
    public function destroy($id)
    {
        try {
            $log = ErrorLog::find($id);

            if (!$log) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error log not found'
                ], 404);
            }

            $log->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Error log deleted successfully'
            ]);
        } catch (\Exception $e) {
            // Log to error_logs table
            ErrorLogService::logException($e, 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete error log: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all error logs.
     */
    public function clear()
    {
        try {
            // Check if table exists
            if (!Schema::hasTable('error_logs')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error logs table does not exist.'
                ], 404);
            }

            ErrorLog::truncate();

            return response()->json([
                'status' => 'success',
                'message' => 'All error logs cleared successfully'
            ]);
        } catch (\Exception $e) {
            // Log to error_logs table
            ErrorLogService::logException($e, 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear error logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get error log statistics.
     */
    public function statistics()
    {
        try {
            // Check if table exists
            if (!Schema::hasTable('error_logs')) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'total' => 0,
                        'by_level' => [],
                        'today' => 0,
                        'this_week' => 0,
                        'this_month' => 0,
                    ]
                ]);
            }

            $stats = [
                'total' => ErrorLog::count(),
                'by_level' => ErrorLog::selectRaw('level, count(*) as count')
                    ->groupBy('level')
                    ->pluck('count', 'level')
                    ->toArray(),
                'today' => ErrorLog::whereDate('created_at', today())->count(),
                'this_week' => ErrorLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'this_month' => ErrorLog::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            // Log to error_logs table
            ErrorLogService::logException($e, 'error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load statistics: ' . $e->getMessage(),
                'data' => [
                    'total' => 0,
                    'by_level' => [],
                    'today' => 0,
                    'this_week' => 0,
                    'this_month' => 0,
                ]
            ], 500);
        }
    }
}

