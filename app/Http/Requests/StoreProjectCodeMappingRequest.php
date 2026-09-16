<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectCodeMappingRequest extends FormRequest
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
            'lkpm_project_code' => ['required', 'string', 'max:255', Rule::exists('lkpm_reports', 'project_code')],
            'project_code' => ['required', 'string', 'max:255', Rule::exists('projects', 'project_code')],
        ];
    }
}
