<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCorrectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller/policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'attendance_record_id' => ['nullable', 'exists:attendance_records,id'],
            'type' => ['required', Rule::in(['missing_checkout', 'wrong_time', 'duplicate_record', 'missing_record', 'other'])],
            'original_data' => ['nullable', 'array'],
            'proposed_data' => ['required', 'array'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5MB max
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'reason.min' => 'Please provide a detailed reason (at least 10 characters).',
            'reason.max' => 'Reason is too long (maximum 1000 characters).',
            'supporting_document.max' => 'Supporting document must not exceed 5MB.',
            'supporting_document.mimes' => 'Supporting document must be a PDF, JPG, JPEG, or PNG file.',
        ];
    }
}
