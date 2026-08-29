<?php

namespace App\Console\Commands;

use App\Services\DemoResetService;
use Illuminate\Console\Command;

/**
 * Pemulihan tenant demo dari server.
 *
 * Pemulihan sudah berjalan sendiri saat ada yang masuk memakai akun demo.
 * Perintah ini untuk dua keadaan lain: menyiapkannya pertama kali, dan
 * memaksa pemulihan segera tanpa menunggu jadwalnya.
 */
class DemoReset extends Command
{
    protected $signature = 'pos:demo-reset {--force : Pulihkan sekarang juga, tanpa menunggu jadwal}';

    protected $description = 'Pulihkan tenant demo beserta isi contohnya';

    public function handle(DemoResetService $demo): int
    {
        if (! $demo->aktif()) {
            $this->error('Fitur demo dimatikan (config demo.username kosong).');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            $demo->pulihkan();
            $this->info('Tenant demo dipulihkan.');
        } elseif ($demo->pulihkanBilaPerlu()) {
            $this->info('Tenant demo dipulihkan karena sudah melewati jadwal.');
        } else {
            $this->line('Belum waktunya dipulihkan. Pakai --force untuk memaksa.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Dashboard : /admin/login');
        $this->line('  Owner       '.config('demo.username').'  / '.config('demo.password'));
        $this->line('  Supervisor  '.config('demo.username').'-spv  / '.config('demo.password'));
        $this->line('Terminal  : /pos/login  (PIN)');
        $this->line('  Kasir Demo 1  PIN 1234    Kasir Demo 2  PIN 5678');
        $this->newLine();
        $this->line('Tenant    : '.config('demo.tenant_name').' ('.config('demo.tenant_slug').')');
        $this->line('Isi contoh: '.config('demo.outlets').' outlet, riwayat penjualan '
            .config('demo.sales_days').' hari');
        $this->newLine();

        return self::SUCCESS;
    }
}
