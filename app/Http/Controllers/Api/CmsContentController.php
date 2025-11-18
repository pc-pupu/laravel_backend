<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\housingCms;
use App\Support\Concerns\HandlesCmsContent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Log;

class CmsContentController extends Controller
{
    use HandlesCmsContent;

    public function index(Request $request)
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

        $contents->getCollection()->transform(function ($content) {
            return $this->formatCmsContent($content);
        });

        return response()->json([
            'status' => 'success',
            'data'   => $contents,
        ]);
    }

    public function stats()
    {
        $nextOrder = (int) housingCms::max('order_no') + 1;

        return response()->json([
            'status' => 'success',
            'data' => [
                'next_order_no' => $nextOrder ?: 1,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please fix the errors below.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $this->mapPayload($validator->validated());

        if ($request->hasFile('content_file_upload')) {
            try {
                $data = array_merge($data, $this->storeFile($request, $data['content_type']));
            } catch (\Exception $e) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }
        }
        // Log::info('Creating CMS Content', ['data' => $data]);

        $content = housingCms::create($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Content added successfully.',
            'data'    => $this->formatCmsContent($content),
        ], 201);
    }

    public function show($id)
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

    public function update(Request $request, $id)
    {
        $content = housingCms::find($id);

        if (!$content) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Content not found.',
            ], 404);
        }

        $validator = $this->validator($request->all(), true);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please fix the errors below.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $this->mapPayload($validator->validated(), $content);

        if ($request->hasFile('content_file_upload')) {
            try {
                $this->removeExistingFile($content);
                $data = array_merge($data, $this->storeFile($request, $data['content_type']));
            } catch (\Exception $e) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        $content->update($data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Content updated successfully.',
            'data'    => $this->formatCmsContent($content->fresh()),
        ]);
    }

    public function destroy($id)
    {
        $content = housingCms::find($id);

        if (!$content) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Content not found.',
            ], 404);
        }

        $this->removeExistingFile($content);
        $content->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Content deleted successfully.',
        ]);
    }

    protected function validator(array $data, bool $isUpdate = false)
    {
        $maxOrder = (int) housingCms::max('order_no') + 1;

        return Validator::make($data, [
            'content_type'        => 'required|string|in:' . implode(',', $this->cmsContentTypes),
            'link_title'          => 'nullable|string|max:255',
            'content_title'       => 'required|string|max:255',
            'content_description' => 'required|string',
            'order_no'            => 'nullable|integer|min:1|max:' . ($maxOrder ?: 10000),
            'meta_keyword'        => 'nullable|string|max:255',
            'meta_description'    => 'nullable|string',
            'date_of_notification'=> 'required|string',
            'is_active'           => 'required|in:0,1',
            'is_new'              => 'nullable|in:0,1',
            'content_file_upload' => ($isUpdate ? 'nullable' : 'nullable') . '|file|mimes:pdf|max:1024', // 1 MB = 1024 KB
        ], [
            'content_type.in' => 'Please select a valid content type.',
            'content_file_upload.mimes' => 'Only PDF files are allowed.',
            'content_file_upload.max' => 'The file size must not exceed 1 MB.',
        ]);
    }

    protected function mapPayload(array $validated, ?housingCms $existing = null): array
    {
        $date = $this->parseDate($validated['date_of_notification'] ?? null);

        $order = $validated['order_no']
            ?? ($existing ? $existing->order_no : (housingCms::max('order_no') + 1));

        return [
            'content_type'        => $this->normalizeContentType($validated['content_type']),
            'content_title'       => $validated['content_title'],
            'link_title'          => $validated['link_title'] ?? null,
            'order_no'            => $order,
            'meta_keyword'        => $validated['meta_keyword'] ?? null,
            'meta_description'    => $validated['meta_description'] ?? null,
            'date_of_notification'=> $date,
            'content_description' => $validated['content_description'],
            'is_active'           => (int) ($validated['is_active'] ?? 1),
            'is_new'              => (int) ($validated['is_new'] ?? 0),
            'url'                 => Str::slug($validated['content_title'], '_'),
            'created_date'        => Carbon::now()->format('Y-m-d H:i:s'),
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

