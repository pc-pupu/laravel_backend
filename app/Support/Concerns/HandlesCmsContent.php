<?php

namespace App\Support\Concerns;

use App\Models\housingCms;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesCmsContent
{
    /**
     * Allowed CMS content types as per legacy Drupal module.
     */
    protected array $cmsContentTypes = [
        'faq',
        'about_us',
        'contact_us',
        'what_is_new',
        'notice',
        'user_manual',
    ];

    protected function normalizeContentType(string $type): string
    {
        $type = strtolower(str_replace('-', '_', trim($type)));
        return in_array($type, $this->cmsContentTypes, true) ? $type : $type;
    }

    protected function formatCmsContent(housingCms $content): array
    {
        return [
            'housing_cms_id'      => $content->housing_cms_id,
            'content_type'        => $content->content_type,
            'content_title'       => $content->content_title,
            'link_title'          => $content->link_title,
            'order_no'            => $content->order_no,
            'meta_keyword'        => $content->meta_keyword,
            'meta_description'    => $content->meta_description,
            'date_of_notification'=> $content->date_of_notification,
            'content_description' => $content->content_description,
            'is_active'           => (int) $content->is_active,
            'is_new'              => (int) $content->is_new,
            'url'                 => $content->url,
            'file_name'           => $content->file_name,
            'file_path'           => $content->file_path,
            'file_url'            => $this->buildCmsFileUrl($content->file_path, $content->content_type),
            'created_date'        => $content->created_date,
            'created_at'          => $content->created_date,
        ];
    }

    protected function buildCmsFileUrl(?string $path, ?string $contentType = null): ?string
    {
        if (!$path) {
            return null;
        }

        $normalized = $this->normalizeFilePathForToken($path, $contentType);

        try {
            $token = encrypt($normalized);
        } catch (\Exception $e) {
            return null;
        }

        $typeSegment = $this->extractTypeFromPath($normalized, $contentType);

        return url('cms/' . $typeSegment . '/' . urlencode($token));
    }

    protected function normalizeFilePathForToken(string $path, ?string $contentType = null): string
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

        if (!$contentType) {
            $contentType = Str::before($cleanPath, '/') ?: 'file';
        }

        if (!Str::startsWith($cleanPath, $contentType . '/')) {
            $cleanPath = trim($contentType . '/' . basename($cleanPath), '/');
        }

        return $cleanPath;
    }

    protected function extractTypeFromPath(string $path, ?string $fallback = null): string
    {
        $segments = explode('/', trim($path, '/'));
        return $segments[0] ?? ($fallback ?: 'file');
    }
}

