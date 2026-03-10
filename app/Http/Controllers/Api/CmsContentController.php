<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCmsContentRequest;
use App\Http\Requests\UpdateCmsContentRequest;
use App\Models\housingCms;
use App\Services\ErrorLogService;
use App\Support\Concerns\HandlesCmsContent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Log;

/**
 * CMS Content API (admin).
 * CRUD for housing_cms; mirrors legacy Drupal cms_content module.
 */
class CmsContentController extends Controller
{
    use HandlesCmsContent;

    public function index(Request $request): JsonResponse
    {
        $query = housingCms::query();

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(content_title) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(link_title) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('content_type')) {
            $query->where('content_type', $this->normalizeContentType($request->input('content_type')));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->input('is_active'));
        }

        if ($request->filled('is_new')) {
            $query->where('is_new', (int) $request->input('is_new'));
        }

        $sort = $request->input('sort', 'housing_cms_id');
        $direction = $request->input('direction', 'desc');
        $query->orderBy($sort, $direction);

        $perPage = (int) $request->input('per_page', 15);
        $contents = $query->paginate($perPage);

        $contents->getCollection()->transform(fn ($content) => $this->formatCmsContent($content));

        return response()->json([
            'status' => 'success',
            'data'   => $contents,
        ]);
    }

    public function stats(): JsonResponse
    {
        $nextOrder = (int) housingCms::max('order_no') + 1;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'next_order_no' => $nextOrder ?: 1,
            ],
        ]);
    }

    public function store(StoreCmsContentRequest $request): JsonResponse
    {
        $data = $this->mapPayload($request->validated());

        if ($request->hasFile('content_file_upload')) {
            try {
                $data = array_merge($data, $this->storeFile($request, $data['content_type']));
            } catch (\Exception $e) {
                Log::warning('CMS content file store failed', ['error' => $e->getMessage()]);
                // This is a handled error (422) so it won't reach the global handler; log it explicitly.
                ErrorLogService::logException($e, 'warning', [
                    'module' => 'cms_content',
                    'action' => 'store_file',
                ]);
                return response()->json([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        try {
            $content = ErrorLogService::wrap(function () use ($data) {
                return housingCms::create($data);
            }, ['module' => 'cms_content', 'action' => 'create']);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to add content.',
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Content added successfully.',
            'data'    => $this->formatCmsContent($content),
        ], 201);
    }

    /** @param int|string $id */
    public function show($id): JsonResponse
    {
        $content = housingCms::find($id);

        if (!$content) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Content not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatCmsContent($content),
        ]);
    }

    /** @param int|string $id */
    public function update(UpdateCmsContentRequest $request, $id): JsonResponse
    {
        $id = (int) $id;
        $content = housingCms::find($id);

        if (!$content) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Content not found.',
            ], 404);
        }

        $data = $this->mapPayload($request->validated(), $content);
        $data['updated_date'] = Carbon::now()->format('Y-m-d H:i:s');

        if ($request->hasFile('content_file_upload')) {
            try {
                $this->removeExistingFile($content);
                $data = array_merge($data, $this->storeFile($request, $data['content_type']));
            } catch (\Exception $e) {
                Log::warning('CMS content file store failed on update', ['id' => $id, 'error' => $e->getMessage()]);
                // This is a handled error (422) so it won't reach the global handler; log it explicitly.
                ErrorLogService::logException($e, 'warning', [
                    'module' => 'cms_content',
                    'action' => 'update_file',
                    'housing_cms_id' => $id,
                ]);
                return response()->json([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        
        try {
            $updated = ErrorLogService::wrap(function () use ($content, $data) {
                // Eloquent update() returns bool; it may return false without throwing.
                return $content->update($data);
            }, [
                'module' => 'cms_content',
                'action' => 'update',
                'housing_cms_id' => $content->housing_cms_id ?? $content->id,
            ]);

            if ($updated !== true) {
                // No exception was thrown, but update did not succeed — log it explicitly.
                ErrorLogService::logMessage('CMS content update returned false (no exception).', 'warning', [
                    'module' => 'cms_content',
                    'action' => 'update',
                    'housing_cms_id' => $content->housing_cms_id ?? $content->id,
                ]);

                return response()->json([
                    'status'  => 'error',
                    'message' => 'Failed to update content.',
                ], 500);
            }
        } catch (\Throwable $e) {
            // wrap() already logged the exception; return a safe error response.
            // \Log::info('CMS content update failed with exception.', [
            //     'module' => 'cms_content',
            //     'action' => 'update',
            //     'housing_cms_id' => $content->housing_cms_id ?? $content->id,
            //     'error' => $e->getMessage(),
            // ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update content.',
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Content updated successfully.',
            'data'    => $this->formatCmsContent($content->fresh()),
        ]);
    }

    /** @param int|string $id */
    public function destroy($id): JsonResponse
    {
        $content = housingCms::find((int) $id);

        if (!$content) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Content not found.',
            ], 404);
        }

        try {
            ErrorLogService::wrap(function () use ($content) {
                $this->removeExistingFile($content);
                $content->delete();
            }, ['module' => 'cms_content', 'action' => 'delete', 'housing_cms_id' => $content->housing_cms_id ?? $content->id]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete content.',
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Content deleted successfully.',
        ]);
    }

    /** @return array<string, mixed> */
    protected function mapPayload(array $validated, ?housingCms $existing = null): array
    {
        $date = $this->parseDate($validated['date_of_notification'] ?? null);

        $order = $validated['order_no']
            ?? ($existing ? $existing->order_no : (housingCms::max('order_no') + 1));

        return [
            'content_type'         => $this->normalizeContentType($validated['content_type']),
            'content_title'        => $validated['content_title'],
            'link_title'           => $validated['link_title'] ?? null,
            'order_no'             => (int) $order,
            'meta_keyword'         => $validated['meta_keyword'] ?? null,
            'meta_description'     => $validated['meta_description'] ?? null,
            'date_of_notification' => $date,
            'content_description'  => $validated['content_description'],
            'is_active'            => (int) ($validated['is_active'] ?? 1),
            'is_new'               => (int) ($validated['is_new'] ?? 0),
            'url'                  => Str::slug($validated['content_title'], '_'),
            'created_date'         => Carbon::now()->format('Y-m-d H:i:s'),
        ];
    }

    protected function parseDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable $th) {
                continue;
            }
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    protected function storeFile(Request $request, string $contentType): array
    {
        $file = $request->file('content_file_upload');

        // Validate MIME type (PDF only)
        $allowedMime = ['application/pdf'];
        if (!in_array($file->getMimeType(), $allowedMime)) {
            throw new \Exception('Invalid file type. Only PDF files are allowed.');
        }

        // Check for multiple extensions
        $originalName = $file->getClientOriginalName();
        if (substr_count($originalName, '.') > 1) {
            throw new \Exception('Multiple extensions are not allowed.');
        }

        // Get filename without extension
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // Sanitize filename: replace spaces and special chars with underscore
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $originalName);

        // Generate filename with timestamp
        $timestamp = now()->format('Ymd_His');
        $filename = $safeName . '_notification_' . $timestamp . '.' . $file->getClientOriginalExtension();

        // Create directory path based on content_type
        $directory = 'uploads/cms/' . $contentType;

        // Create directory if it doesn't exist
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        // Store the file
        $file->storeAs($directory, $filename, 'public');

        $relativePath = trim($contentType . '/' . $filename, '/');

        return [
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $relativePath,
        ];
    }

    protected function removeExistingFile(housingCms $content): void
    {
        if (!$content->file_path) {
            return;
        }

        $storagePath = $this->resolveStoragePath($content->file_path);

        if ($storagePath && Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }
    }

    protected function resolveStoragePath(string $path): string
    {
        $cleanPath = ltrim($path, '/');

        if (Str::startsWith($cleanPath, 'storage/')) {
            $cleanPath = Str::replaceFirst('storage/', '', $cleanPath);
        }

        if (Str::startsWith($cleanPath, 'uploads/')) {
            return $cleanPath;
        }

        return 'uploads/cms/' . $cleanPath;
    }
}

