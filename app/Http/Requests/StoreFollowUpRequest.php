<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFollowUpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof Assignment
            && $this->user()?->role === 'pic'
            && $assignment->pic_id === $this->user()->id
            && $assignment->is_task_active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact_status' => ['required', 'in:belum_dihubungi,sudah_dihubungi,tidak_dapat_dihubungi'],
            'confirmation_status' => ['required', 'in:belum_terkonfirmasi,terkonfirmasi,terkonfirmasi_sebagian,nilai_kurang,tidak_sesuai'],
            // This is an operational PIC status.  It is deliberately separate from
            // the LKPM/OSS report status, which is only sourced from imported data.
            'verification_status' => ['nullable', 'in:belum,dijadwalkan,dikunjungi,terverifikasi,selesai,tidak_dapat_dihubungi'],
            'indicated_amount' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:5000'],
            'next_follow_up_at' => ['nullable', 'date'],
        ];
    }
}
