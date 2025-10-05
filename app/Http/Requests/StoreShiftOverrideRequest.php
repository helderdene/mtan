<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftOverrideRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // TODO: Add proper authorization logic
        // For now, allow all authenticated users
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'override_date' => ['required', 'date'],
            'type' => ['required', Rule::in(['holiday', 'off-day', 'half-day', 'custom-shift'])],
            'custom_start_time' => [
                'nullable',
                'date_format:H:i:s',
                Rule::requiredIf(fn () => in_array($this->type, ['half-day', 'custom-shift'])),
            ],
            'custom_end_time' => [
                'nullable',
                'date_format:H:i:s',
                Rule::requiredIf(fn () => in_array($this->type, ['half-day', 'custom-shift'])),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'custom_start_time.required_if' => 'Start time is required for half-day and custom-shift overrides.',
            'custom_end_time.required_if' => 'End time is required for half-day and custom-shift overrides.',
            'type.in' => 'Override type must be one of: holiday, off-day, half-day, custom-shift.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that custom times are provided for half-day and custom-shift
            if (in_array($this->type, ['half-day', 'custom-shift'])) {
                if (!$this->custom_start_time || !$this->custom_end_time) {
                    $validator->errors()->add('custom_times', 'Both start and end times are required for this override type.');
                }
            }

            // Validate that either shift_id or employee_id is provided (or both)
            if (!$this->shift_id && !$this->employee_id && $this->type !== 'holiday') {
                $validator->errors()->add('target', 'Either shift_id or employee_id must be specified for non-holiday overrides.');
            }
        });
    }
}
