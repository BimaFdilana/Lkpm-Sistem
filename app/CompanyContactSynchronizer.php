<?php

namespace App;

use App\Models\Company;
use App\Models\LkpmReport;

class CompanyContactSynchronizer
{
    public function sync(): int
    {
        $contacts = [];

        LkpmReport::query()
            ->where('is_canonical', true)
            ->whereNotNull('project_id')
            ->with('project.company')
            ->orderBy('reported_at')
            ->orderBy('id')
            ->get()
            ->each(function (LkpmReport $report) use (&$contacts): void {
                $company = $report->project?->company;
                $contactName = $report->source_payload['KONTAK NAMA'] ?? null;
                $contactPhone = $report->source_payload['KONTAK HP'] ?? null;
                $contactEmail = $report->source_payload['KONTAK EMAIL'] ?? null;
                $contactPosition = $report->source_payload['JABATAN'] ?? null;

                if ($company === null || ($contactName === null && $contactPhone === null && $contactEmail === null && $contactPosition === null)) {
                    return;
                }

                $current = $contacts[$company->id] ?? [
                    'contact_name' => $company->contact_name,
                    'contact_phone' => $company->contact_phone,
                    'contact_email' => $company->contact_email,
                    'contact_position' => $company->contact_position,
                    'contact_source_report_id' => $company->contact_source_report_id,
                    'contact_synced_at' => $company->contact_synced_at,
                ];

                $contacts[$company->id] = [
                    'contact_name' => $this->valueOrCurrent($contactName, $current['contact_name']),
                    'contact_phone' => $this->phoneOrCurrent($contactPhone, $current['contact_phone']),
                    'contact_email' => $this->valueOrCurrent($contactEmail, $current['contact_email']),
                    'contact_position' => $this->valueOrCurrent($contactPosition, $current['contact_position']),
                    'contact_source_report_id' => $report->id,
                    'contact_synced_at' => now(),
                ];
            });

        foreach ($contacts as $companyId => $contact) {
            Company::query()->whereKey($companyId)->update($contact);
        }

        return count($contacts);
    }

    private function valueOrCurrent(mixed $value, ?string $current): ?string
    {
        return filled($value) ? trim((string) $value) : $current;
    }

    private function phoneOrCurrent(mixed $value, ?string $current): ?string
    {
        if (! filled($value)) {
            return $current;
        }

        $phone = trim((string) $value);
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === null || $digits === '') {
            return $phone;
        }

        if (str_starts_with($digits, '62')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '+62'.$digits;
        }

        return $phone;
    }
}
