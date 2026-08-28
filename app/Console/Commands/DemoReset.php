<?php

namespace App\Console\Commands;

use App\Services\DemoResetService;
use Illuminate\Console\Command;

/**
 * Pemulihan akun demo dari server.
 *
 * Pemulihan sudah berjalan sendiri saat ada yang masuk memakai akun demo.
 * Perintah ini untuk dua keadaan lain: menyiapkan akunnya pertama kali, dan
 * memaksa pemulihan segera tanpa menunggu 24 jam.
 */
class DemoReset extends Command
{
    protected $signature = 'drive:demo-reset {--force : Pulihkan sekarang juga, tanpa menunggu jadwal}';

    protected $description = 'Pulihkan akun demo ke keadaan semula';

    public function handle(DemoResetService $demo): int
    {
        if (!$demo->aktif()) {
            $this->error('Fitur demo dimatikan (config demo.email kosong).');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            $demo->pulihkan();
            $this->info('Akun demo dipulihkan.');
        } elseif ($demo->pulihkanBilaPerlu()) {
            $this->info('Akun demo dipulihkan karena sudah melewati jadwal.');
        } else {
            $this->line('Belum waktunya dipulihkan. Pakai --force untuk memaksa.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Email    : ' . config('demo.email'));
        $this->line('Password : ' . config('demo.password'));
        $this->line('Peran    : Admin di perusahaan "' . config('demo.company') . '"');
        $this->newLine();

        return self::SUCCESS;
    }
}
