<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveRejectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $correction = $this->route('correction');

        // Only managers can approve/reject, and only pending corrections
        return $correction && $correction->isPending();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isRejection = $this->routeIs('*.reject');

        return [
            'notes' => $isRejection ? ['required', 'string', 'min:10'] : ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'notes.required' => 'Please provide a reason for rejection.',
            'notes.min' => 'Rejection reason must be at least 10 characters.',
        ];
    }
}
