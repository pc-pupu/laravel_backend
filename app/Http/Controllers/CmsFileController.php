<?php

namespace App\Http\Controllers;

use App\Models\housingCms;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsFileController extends Controller
{
    public function showFile(string $type, string $token)
    {
        if (str_contains($token, '.')) {
            [$encodedId, $signature] = $this->splitToken($token);
            $cmsId = $this->decodeIdFragment($encodedId);

            if (!$cmsId) {
                abort(404);
            }

            return $this->serveCmsFile($type, $cmsId, $signature);
        }

        return $this->legacyDownload($type, $token);
    }

    public function showFileWithId(string $type, int $id, string $token)
    {
        return $this->serveCmsFile($type, $id, $token);
    }

    protected function serveCmsFile(string $type, int $id, string $token)
    {
        $content = housingCms::where('housing_cms_id', $id)
            ->where('content_type', $type)
            ->firstOrFail();

        $storagePath = $this->normalizeStoragePath($content->file_path, $content->content_type);
        $expectedSignature = $this->generateSignature($content->housing_cms_id, $storagePath);

        if (!hash_equals($expectedSignature, $token)) {
            abort(403, 'Invalid file token.');
        }

        return $this->streamFile($storagePath);
    }

    public function legacyDownload(string $type, string $token)
    {
        try {
            $relativePath = decrypt($token);
        } catch (DecryptException $e) {
            abort(404);
        }

        $storagePath = $this->normalizeStoragePath($relativePath, $type);

        return $this->streamFile($storagePath);
    }

    protected function streamFile(string $storagePath)
    {
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

    protected function normalizeStoragePath(string $path, ?string $expectedType = null): string
    {
        $cleanPath = ltrim($path, '/');

        if (Str::startsWith($cleanPath, 'public://')) {
            $cleanPath = Str::replaceFirst('public://', '', $cleanPath);
        }

        if (Str::startsWith($cleanPath, 'storage/')) {
            $cleanPath = Str::replaceFirst('storage/', '', $cleanPath);
        }

        if (Str::startsWith($cleanPath, 'uploads/cms/')) {
            $cleanPath = Str::replaceFirst('uploads/cms/', '', $cleanPath);
        } elseif (Str::startsWith($cleanPath, 'uploads/')) {
            $cleanPath = Str::replaceFirst('uploads/', '', $cleanPath);
        }

        $segments = explode('/', trim($cleanPath, '/'));
        $filename = array_pop($segments);
        $type = $segments[0] ?? $expectedType ?? 'file';

        return 'uploads/cms/' . $type . '/' . $filename;
    }

    protected function splitToken(string $token): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            abort(403, 'Invalid token format.');
        }

        return $parts;
    }

    protected function decodeIdFragment(string $encoded)
    {
        $padded = strtr($encoded, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);

        if ($decoded === false || !ctype_digit($decoded)) {
            return null;
        }

        return (int) $decoded;
    }

    protected function generateSignature(int $id, string $path): string
    {
        return hash_hmac('sha256', $id . '|' . $path, config('app.key'));
    }
}

