<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplicantDataUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->hasRole('admin') || $user->hasRole('super_admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => 'required|mimes:xls,xlsx|max:10240', // Max 10MB
            'skip_existing' => 'boolean',
            'send_email' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File is required.',
            'file.mimes' => 'File must be an Excel file (.xls or .xlsx).',
            'file.max' => 'File size must not exceed 10MB.',
        ];
    }
}
