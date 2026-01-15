<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InputSanitizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();
        
        // Recursively sanitize input
        $sanitized = $this->sanitizeArray($input);
        
        // Replace request input with sanitized data
        $request->merge($sanitized);

        return $next($request);
    }

    /**
     * Recursively sanitize array
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            // Sanitize key
            $sanitizedKey = $this->sanitizeString($key);
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value);
            } else {
                $sanitized[$sanitizedKey] = $this->sanitizeString($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize string input
     */
    private function sanitizeString($value): string
    {
        if (!is_string($value)) {
            return $value;
        }
        
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        // Strip tags but allow safe HTML if needed
        $value = strip_tags($value);
        
        // Remove control characters except newlines and tabs
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        // Trim whitespace
        $value = trim($value);
        
        // Limit length to prevent buffer overflow (max 10000 characters)
        if (strlen($value) > 10000) {
            $value = substr($value, 0, 10000);
        }
        
        return $value;
    }
}

