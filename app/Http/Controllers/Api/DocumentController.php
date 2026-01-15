<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

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
            // Decrypt the path
            $filePath = Crypt::decryptString($encryptedPath);
        } catch (\Exception $e) {
            Log::error('Document Download Error', [
                'error' => $e->getMessage(),
                'encrypted_path' => $encryptedPath
            ]);
            return response()->json([
                'error' => 'Invalid download link'
            ], 403);
        }

        // Check if file exists in backend public storage
        if (!Storage::disk('public')->exists($filePath)) {
            Log::error('Document Not Found in Backend', [
                'filePath' => $filePath,
                'storage_path' => Storage::disk('public')->path($filePath)
            ]);
            return response()->json([
                'error' => 'File not found',
                'filePath' => $filePath
            ], 404);
        }

        $fullPath = Storage::disk('public')->path($filePath);
        $fileName = basename($filePath);
        
        return response()->download($fullPath, $fileName);
    }
}

