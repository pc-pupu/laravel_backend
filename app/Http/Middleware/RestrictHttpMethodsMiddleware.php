<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictHttpMethodsMiddleware
{
    /**
     * Allowed HTTP methods
     */
    private $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $method = $request->method();
        
        // Block dangerous methods
        $dangerousMethods = ['TRACE', 'TRACK', 'CONNECT'];
        
        if (in_array(strtoupper($method), $dangerousMethods)) {
            abort(405, 'Method Not Allowed');
        }
        
        // Only allow standard HTTP methods
        if (!in_array(strtoupper($method), $this->allowedMethods)) {
            abort(405, 'Method Not Allowed');
        }

        return $next($request);
    }
}

