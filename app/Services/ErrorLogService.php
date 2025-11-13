<?php

namespace App\Services;

use App\Models\ErrorLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;

class ErrorLogService
{
    /**
     * Log an error to the error_logs table
     *
     * @param \Throwable|string $error The exception or error message
     * @param string $level The error level (error, warning, info, debug)
     * @param array $context Additional context data
     * @return ErrorLog|null
     */
    public static function log($error, $level = 'error', $context = [])
    {
        try {
            // Get user ID if authenticated
            $userId = null;
            try {
                if (Auth::check()) {
                    $user = Auth::user();
                    $userId = $user->uid ?? $user->id ?? null;
                }
            } catch (\Exception $e) {
                // If auth fails, continue without user ID
                $userId = null;
            }

            // Extract error information
            $message = '';
            $file = null;
            $line = null;
            $trace = null;

            if ($error instanceof \Throwable) {
                $message = $error->getMessage();
                $file = $error->getFile();
                $line = $error->getLine();
                $trace = $error->getTraceAsString();
            } else {
                $message = (string) $error;
            }

            // Get request information (safely)
            $url = null;
            $method = null;
            $ipAddress = null;
            try {
                $url = Request::fullUrl();
                $method = Request::method();
                $ipAddress = Request::ip();
            } catch (\Exception $e) {
                // If request is not available (e.g., in console), use defaults
                $url = 'N/A';
                $method = 'N/A';
                $ipAddress = 'N/A';
            }

            // Prepare context data
            $requestData = [];
            $headers = [];
            try {
                $requestData = Request::all();
                $headers = Request::headers->all();
            } catch (\Exception $e) {
                // If request is not available, use empty arrays
            }
            
            $contextData = array_merge($context, [
                'request_data' => $requestData,
                'headers' => $headers,
            ]);

            // Create error log entry
            $errorLog = ErrorLog::create([
                'level' => $level,
                'message' => $message,
                'context' => $contextData,
                'file' => $file,
                'line' => $line,
                'trace' => $trace,
                'user_id' => $userId,
                'url' => $url,
                'method' => $method,
                'ip_address' => $ipAddress,
            ]);

            // Also log to Laravel's default log
            Log::log($level, $message, [
                'file' => $file,
                'line' => $line,
                'user_id' => $userId,
                'url' => $url,
                'context' => $contextData,
            ]);

            return $errorLog;
        } catch (\Exception $e) {
            // If we can't log to database, at least log to file
            Log::error('Failed to log error to database: ' . $e->getMessage(), [
                'original_error' => $message ?? 'Unknown error',
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Log an exception
     *
     * @param \Throwable $exception
     * @param string $level
     * @param array $context
     * @return ErrorLog|null
     */
    public static function logException(\Throwable $exception, $level = 'error', $context = [])
    {
        return self::log($exception, $level, $context);
    }

    /**
     * Log a simple error message
     *
     * @param string $message
     * @param string $level
     * @param array $context
     * @return ErrorLog|null
     */
    public static function logMessage($message, $level = 'error', $context = [])
    {
        return self::log($message, $level, $context);
    }
}

