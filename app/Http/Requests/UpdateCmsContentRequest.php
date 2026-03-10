<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Constants\CmsContentType;
use App\Models\housingCms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for updating an existing CMS content entry.
 * Duplicate check excludes the current record.
 */
class UpdateCmsContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');
        $maxOrder = (int) housingCms::max('order_no') + 1;

        return [
            'content_type'         => ['required', 'string', Rule::in(CmsContentType::ALL)],
            'link_title'           => ['nullable', 'string', 'max:255'],
            'content_title'        => [
                'required',
                'string',
                'max:255',
                Rule::unique('housing_cms', 'content_title')->where('link_title', $this->input('link_title') ?? '')->ignore($id, 'housing_cms_id'),
            ],
            'content_description'  => ['required', 'string'],
            'order_no'             => ['nullable', 'integer', 'min:1', 'max:' . ($maxOrder ?: 10000)],
            'meta_keyword'         => ['nullable', 'string', 'max:255'],
            'meta_description'     => ['nullable', 'string'],
            'date_of_notification' => ['required', 'string'],
            'is_active'             => ['required', 'in:0,1'],
            'is_new'               => ['nullable', 'in:0,1'],
            'content_file_upload'  => ['nullable', 'file', 'mimes:pdf', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'content_type.in'           => 'Please select a valid content type.',
            'content_title.unique'       => 'Duplicate content. A record with this content title and link title already exists.',
            'content_file_upload.mimes'  => 'Only PDF files are allowed.',
            'content_file_upload.max'   => 'The file size must not exceed 1 MB.',
        ];
    }
}
