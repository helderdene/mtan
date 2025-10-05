<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecalculateSummariesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'from' => ['required', 'date', 'date_format:Y-m-d'],
            'to' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee ID is required.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'from.required' => 'Start date is required.',
            'from.date_format' => 'Start date must be in Y-m-d format (e.g., 2025-10-01).',
            'to.required' => 'End date is required.',
            'to.date_format' => 'End date must be in Y-m-d format (e.g., 2025-10-31).',
            'to.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
