<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreImportBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isOneOf('programmer', 'kepala_bagian') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_type' => ['required', 'in:projects,lkpm,sectors'],
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:51200'],
            'report_year' => ['nullable', 'integer', 'min:2021', 'max:2035'],
            'report_quarter' => ['nullable', 'in:Triwulan I,Triwulan II,Triwulan III,Triwulan IV'],
        ];
    }
}
