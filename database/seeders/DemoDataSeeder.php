<?php

namespace Database\Seeders;

use App\Models\TargetPeriod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Programmer Sistem', 'email' => 'programmer@dpmptsp.test', 'role' => 'programmer'],
            ['name' => 'Kepala Dinas', 'email' => 'kadis@dpmptsp.test', 'role' => 'kepala_dinas'],
            ['name' => 'Kepala Bagian', 'email' => 'kabag@dpmptsp.test', 'role' => 'kepala_bagian'],
            ['name' => 'PIC Industri', 'email' => 'pic1@dpmptsp.test', 'role' => 'pic'],
            ['name' => 'PIC Perdagangan', 'email' => 'pic2@dpmptsp.test', 'role' => 'pic'],
            ['name' => 'PIC Jasa', 'email' => 'pic3@dpmptsp.test', 'role' => 'pic'],
            ['name' => 'PIC Pertanian', 'email' => 'pic4@dpmptsp.test', 'role' => 'pic'],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(['email' => $user['email']], $user + ['password' => Hash::make('password')]);
        }

        TargetPeriod::query()->updateOrCreate(['year' => 2026, 'quarter' => 'TW III'], ['annual_target' => 7900000000000, 'target_amount' => 2545000000000, 'buffer_amount' => 3200000000000, 'baseline_realization' => 2810000000000, 'activity_starts_at' => '2026-07-01', 'activity_ends_at' => '2026-09-30', 'reporting_starts_at' => '2026-10-01', 'reporting_ends_at' => '2026-10-15', 'is_active' => true]);

    }
}
