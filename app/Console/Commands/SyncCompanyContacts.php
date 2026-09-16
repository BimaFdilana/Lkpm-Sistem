<?php

namespace App\Console\Commands;

use App\CompanyContactSynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-company-contacts')]
#[Description('Mengisi Nama Kontak dan Jabatan perusahaan dari laporan LKPM yang terhubung.')]
class SyncCompanyContacts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CompanyContactSynchronizer $companyContactSynchronizer): int
    {
        $updatedCount = $companyContactSynchronizer->sync();
        $this->info("Data kontak diperbarui untuk {$updatedCount} perusahaan.");

        return self::SUCCESS;
    }
}
