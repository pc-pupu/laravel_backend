<?php

namespace App\Exceptions;

use App\Services\ErrorLogService;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Log all exceptions to error_logs table
            $this->logException($e);
        });
    }

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $e)
    {
        // Log to error_logs table
        $this->logException($e);

        // Call parent to log to default Laravel log
        parent::report($e);
    }

    /**
     * Log exception to error_logs table
     *
     * @param \Throwable $e
     * @return void
     */
    protected function logException(Throwable $e)
    {
        try {
            // Determine error level based on exception type
            $level = 'error';
            
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $level = 'warning';
            } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                $level = 'warning';
            } elseif ($e instanceof \Illuminate\Http\Exceptions\HttpException) {
                $code = $e->getStatusCode();
                if ($code >= 500) {
                    $level = 'error';
                } elseif ($code >= 400) {
                    $level = 'warning';
                }
            }

            // Log to database
            ErrorLogService::logException($e, $level, [
                'exception_class' => get_class($e),
                'code' => $e->getCode(),
            ]);
        } catch (\Exception $logException) {
            // If logging fails, at least log to file
            \Log::error('Failed to log exception to database: ' . $logException->getMessage());
        }
    }
}

