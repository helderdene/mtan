<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftOverrideRequest extends FormRequest
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
            'override_date' => ['sometimes', 'required', 'date'],
            'type' => ['sometimes', 'required', Rule::in(['holiday', 'off-day', 'half-day', 'custom-shift'])],
            'custom_start_time' => ['nullable', 'date_format:H:i:s'],
            'custom_end_time' => ['nullable', 'date_format:H:i:s'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Override type must be one of: holiday, off-day, half-day, custom-shift.',
        ];
    }
}
