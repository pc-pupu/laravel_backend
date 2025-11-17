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
            'file_url'            => $this->buildCmsFileUrl($content->file_path),
            'created_date'        => $content->created_date,
            'created_at'          => $content->created_date,
        ];
    }

    protected function buildCmsFileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Already an absolute URL
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        // Legacy Drupal "public://" path
        if (Str::startsWith($path, 'public://')) {
            $cleanPath = Str::replaceFirst('public://', '', $path);
            return asset('storage/' . ltrim($cleanPath, '/'));
        }

        // Stored as "storage/..." from Laravel
        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        // Raw path within storage disk
        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        return asset($path);
    }
}

