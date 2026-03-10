<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use App\Services\ErrorLogService;

class DocumentController extends Controller
{
    /**
     * Download document from backend storage
     */
    public function download(Request $request)
    {
        $encryptedPath = $request->query('path');
        
        if (empty($encryptedPath)) {
            return response()->json([
                'error' => 'Missing document path'
            ], 403);
        }

        try {
            $filePath = Crypt::decryptString($encryptedPath);
        } catch (\Exception $e) {
            Log::error('Document Download Error');
            ErrorLogService::logException($e, 'warning', ['module' => 'documents', 'action' => 'download_decrypt']);
            return response()->json(['error' => 'Invalid download link'], 403);
        }

        $filePath = str_replace(["\0", '\\'], ['', '/'], (string) $filePath);
        if ($filePath === '' || str_starts_with($filePath, '/') || str_contains($filePath, '..') || preg_match('#^[a-zA-Z]:[/\\\\]#', $filePath)) {
            return response()->json(['error' => 'Invalid download link'], 403);
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            return response()->json(['error' => 'File type not allowed'], 403);
        }

        if (!Storage::disk('public')->exists($filePath)) {
            Log::error('Document Not Found in Backend');
            return response()->json(['error' => 'File not found'], 404);
        }

        $fullPath = Storage::disk('public')->path($filePath);
        $fileName = basename($filePath);
        $fileName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName) ?: 'document.' . $ext;

        return response()->download($fullPath, $fileName);
    }
}

