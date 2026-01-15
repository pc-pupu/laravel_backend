<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Security Headers - Apply globally
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);
        
        // Restrict HTTP Methods - Apply globally
        $middleware->append(\App\Http\Middleware\RestrictHttpMethodsMiddleware::class);
        
        // File Upload Validation - Apply to web routes
        $middleware->web(append: [
            \App\Http\Middleware\FileUploadValidationMiddleware::class,
            \App\Http\Middleware\InputSanitizationMiddleware::class,
        ]);
        
        // GLOBAL CORS (MUST)
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        // API group CORS (optional but safe)
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        // Log all exceptions to error_logs table
        $exceptions->report(function (\Throwable $e) {
            \App\Services\ErrorLogService::logException($e);
        });
        
        // Hide internal paths and sensitive information in production
        if (config('app.env') === 'production') {
            $exceptions->render(function (\Throwable $e, $request) {
                // Don't expose internal paths or stack traces
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'An error occurred. Please try again later.',
                        'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
                    ], 500);
                }
            });
        }
    })->create();
