<?php

if (!function_exists('log_error')) {
    /**
     * Helper function to log errors easily
     *
     * @param \Throwable|string $error
     * @param string $level
     * @param array $context
     * @return \App\Models\ErrorLog|null
     */
    function log_error($error, $level = 'error', $context = [])
    {
        return \App\Services\ErrorLogService::log($error, $level, $context);
    }
}

if (!function_exists('log_exception')) {
    /**
     * Helper function to log exceptions easily
     *
     * @param \Throwable $exception
     * @param string $level
     * @param array $context
     * @return \App\Models\ErrorLog|null
     */
    function log_exception(\Throwable $exception, $level = 'error', $context = [])
    {
        return \App\Services\ErrorLogService::logException($exception, $level, $context);
    }
}

if (!function_exists('log_warning')) {
    /**
     * Helper function to log warnings easily
     *
     * @param string $message
     * @param array $context
     * @return \App\Models\ErrorLog|null
     */
    function log_warning($message, $context = [])
    {
        return \App\Services\ErrorLogService::logMessage($message, 'warning', $context);
    }
}

if (!function_exists('log_info')) {
    /**
     * Helper function to log info messages easily
     *
     * @param string $message
     * @param array $context
     * @return \App\Models\ErrorLog|null
     */
    function log_info($message, $context = [])
    {
        return \App\Services\ErrorLogService::logMessage($message, 'info', $context);
    }
}

