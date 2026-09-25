<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDashboardAnnualTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'kepala_dinas';
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2021', 'max:2100'],
            'annual_target' => ['required', 'integer', 'min:1'],
            'tw_1' => ['required', 'integer', 'min:0'],
            'tw_2' => ['required', 'integer', 'min:0'],
            'tw_3' => ['required', 'integer', 'min:0'],
            'tw_4' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $distributionTotal = collect(['tw_1', 'tw_2', 'tw_3', 'tw_4'])
                ->sum(fn (string $key): int => (int) $this->input($key));

            if ((int) $this->input('annual_target') !== $distributionTotal) {
                $validator->errors()->add('annual_target', 'Total pembagian TW I–IV harus sama dengan target tahunan.');
            }
        }];
    }
}
