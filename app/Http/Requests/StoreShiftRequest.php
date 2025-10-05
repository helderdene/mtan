<?php

namespace App\Http\Requests;

use App\Rules\BreakDurationValid;
use App\Rules\BreakWithinShiftHours;
use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handled by middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s'],
            'break_start' => [
                'nullable',
                'date_format:H:i:s',
                'required_with:break_end',
                'before:break_end',
                new BreakWithinShiftHours($this->input('start_time'), $this->input('end_time')),
            ],
            'break_end' => [
                'nullable',
                'date_format:H:i:s',
                'required_with:break_start',
                'after:break_start',
                new BreakDurationValid($this->input('break_start')),
                new BreakWithinShiftHours($this->input('start_time'), $this->input('end_time')),
            ],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['integer', 'between:0,6'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'break_start.required_with' => 'Both break start and end times are required. Please provide both or leave both empty.',
            'break_end.required_with' => 'Both break start and end times are required. Please provide both or leave both empty.',
            'break_start.before' => 'Break start time must be before break end time.',
            'break_end.after' => 'Break start time must be before break end time.',
            'break_start.date_format' => 'Invalid time format. Please use HH:MM:SS format.',
            'break_end.date_format' => 'Invalid time format. Please use HH:MM:SS format.',
            'start_time.required' => 'Shift start time is required.',
            'start_time.date_format' => 'Invalid time format. Please use HH:MM:SS format.',
            'end_time.required' => 'Shift end time is required.',
            'end_time.date_format' => 'Invalid time format. Please use HH:MM:SS format.',
            'name.required' => 'Shift name is required.',
            'name.max' => 'Shift name cannot exceed 255 characters.',
        ];
    }
}
