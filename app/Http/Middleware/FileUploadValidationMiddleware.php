<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;

class FileUploadValidationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Validate file uploads if present
        if (!empty($request->allFiles())) {
            $files = $request->allFiles();
            
            foreach ($files as $key => $file) {
                if (is_array($file)) {
                    foreach ($file as $singleFile) {
                        $this->validateFile($singleFile, $key);
                    }
                } else {
                    $this->validateFile($file, $key);
                }
            }
        }

        return $next($request);
    }

    /**
     * Validate a single file
     */
    private function validateFile($file, $fieldName)
    {
        // Audit: Block double extension (e.g., file.php.jpg, document.pdf.exe)
        $filename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        // Reject any filename containing path traversal or null bytes
        if (str_contains($filename, "\0") || str_contains($filename, '..') || preg_match('#[/\\\\]#', $filename)) {
            abort(422, 'File upload rejected: Invalid filename');
        }

        $dangerousExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'js', 'jsp', 'asp', 'aspx', 'sh', 'py', 'pl', 'rb', 'jar', 'war'];
        foreach ($dangerousExtensions as $dangerousExt) {
            if (stripos($nameWithoutExt, '.' . $dangerousExt) !== false || stripos($nameWithoutExt, $dangerousExt . '.') !== false) {
                abort(422, 'File upload rejected: Double extension detected');
            }
        }

        // Reject if multiple extensions (e.g., a.b.c)
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            $suspicious = array_intersect(array_map('strtolower', array_slice($parts, 0, -1)), $dangerousExtensions);
            if (!empty($suspicious)) {
                abort(422, 'File upload rejected: Double extension detected');
            }
        }
        
        // Validate file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
        
        if (!in_array(strtolower($extension), $allowedExtensions)) {
            abort(422, "File upload rejected: Extension '{$extension}' is not allowed.");
        }
        
        // Validate file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if ($file->getSize() > $maxSize) {
            abort(422, "File upload rejected: File size exceeds maximum allowed size of 10MB.");
        }
        
        // Validate MIME type
        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv'
        ];
        
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes)) {
            abort(422, "File upload rejected: MIME type '{$mimeType}' is not allowed.");
        }
    }
}

