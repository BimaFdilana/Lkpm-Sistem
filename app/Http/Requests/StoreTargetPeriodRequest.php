<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTargetPeriodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'kepala_bagian';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2021', 'max:2100'],
            'quarter' => ['required', 'in:TW I,TW II,TW III,TW IV'],
            'annual_target' => ['required', 'integer', 'min:1'],
            'baseline_realization' => ['required', 'integer', 'min:0'],
            'buffer_amount' => ['nullable', 'integer', 'min:0'],
            'activity_starts_at' => ['nullable', 'date'],
            'activity_ends_at' => ['nullable', 'date', 'after_or_equal:activity_starts_at'],
            'reporting_starts_at' => ['nullable', 'date'],
            'reporting_ends_at' => ['nullable', 'date', 'after_or_equal:reporting_starts_at'],
        ];
    }
}
