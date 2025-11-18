<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsFileController extends Controller
{
    public function download(string $type, string $token)
    {
        try {
            $relativePath = decrypt($token);
        } catch (DecryptException $e) {
            abort(404);
        }

        $cleanPath = ltrim($relativePath, '/');
        if ($type !== 'file' && !Str::startsWith($cleanPath, $type . '/')) {
            abort(404);
        }

        $storagePath = $this->resolveStoragePath($cleanPath);

        if (!Storage::disk('public')->exists($storagePath)) {
            abort(404);
        }

        $fileName = basename($storagePath);
        $mimeType = Storage::disk('public')->mimeType($storagePath) ?: 'application/pdf';

        return response()->stream(function () use ($storagePath) {
            echo Storage::disk('public')->get($storagePath);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    protected function resolveStoragePath(string $path): string
    {
        $path = ltrim($path, '/');

        if (Str::startsWith($path, 'storage/')) {
            $path = Str::replaceFirst('storage/', '', $path);
        }

        if (Str::startsWith($path, 'uploads/')) {
            return $path;
        }

        return 'uploads/cms/' . $path;
    }
}

