<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCorrectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $correction = $this->route('correction');

        // Only pending corrections can be updated
        return $correction && $correction->canBeUpdatedByEmployee();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proposed_data' => ['sometimes', 'array'],
            'reason' => ['sometimes', 'string', 'min:10', 'max:1000'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
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
