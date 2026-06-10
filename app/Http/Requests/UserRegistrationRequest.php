<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public registration endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'applicant_name' => 'required_without:name|string|max:255|regex:/^[a-zA-Z. ]+$/',
            'name' => 'required_without:applicant_name|string|max:255|regex:/^[a-zA-Z. ]+$/',
            'dob' => 'required|date_format:d/m/Y|before_or_equal:' . now()->subYears(18)->format('d/m/Y'),
            'gender' => 'required|in:M,F,male,female',
            'mobile' => [
                'required',
                'regex:/^[6-9]\d{9}$/',
                Rule::unique('housing_applicant', 'mobile_no'),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'mail'),
            ],
            'hrms_id' => [
                'required',
                'regex:/^[1-9]\d{9}$/',
                Rule::unique('housing_applicant_official_detail', 'hrms_id'),
            ],
            'app_designation' => 'required_without:designation|string|max:255',
            'designation' => 'required_without:app_designation|string|max:255',
            'office_name' => 'required_without:office|string|max:255',
            'office' => 'required_without:office_name|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'applicant_name.required_without' => 'Name is required.',
            'dob.required' => 'Date of birth is required.',
            'dob.date_format' => 'Date of birth must be in DD/MM/YYYY format.',
            'dob.before_or_equal' => 'You must be at least 18 years old.',
            'gender.required' => 'Gender is required.',
            'mobile.required' => 'Mobile number is required.',
            'mobile.regex' => 'Mobile number must be 10 digits starting with 6-9.',
            'mobile.unique' => 'This mobile number is already registered.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'hrms_id.required' => 'HRMS ID is required.',
            'hrms_id.regex' => 'HRMS ID must be 10 digits starting with 1-9.',
            'hrms_id.unique' => 'This HRMS ID is already registered.',
            'designation.required' => 'Designation is required.',
            'app_designation.required_without' => 'Designation is required.',
            'office.required' => 'Office is required.',
            'office_name.required_without' => 'Office is required.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower($this->email),
            'applicant_name' => $this->applicant_name ?? $this->name,
            'app_designation' => $this->app_designation ?? $this->designation,
            'office_name' => $this->office_name ?? $this->office,
            'gender' => $this->normalizeGender($this->gender),
        ]);
    }

    private function normalizeGender(?string $gender): ?string
    {
        return match (strtolower((string) $gender)) {
            'male' => 'M',
            'female' => 'F',
            default => $gender,
        };
    }
}
