<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Support\OutletContext;
use App\Support\Tenancy;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pemulihan tenant demo ke keadaan semula.
 *
 * Demo dipakai orang asing yang boleh melakukan apa saja: menambah produk,
 * membatalkan transaksi, mengubah pengaturan toko, bahkan mengganti kata sandi
 * akun demo itu sendiri. Karena itu pemulihannya membuang seluruh isi tenant
 * demo dan membangunnya ulang, bukan sekadar merapikan yang terlihat.
 */
class DemoResetService
{
    /** Nama pengaturan tempat waktu pemulihan terakhir disimpan. */
    private const KUNCI_WAKTU = 'demo_last_reset';

    public function aktif(): bool
    {
        return (string) config('demo.username') !== '';
    }

    /**
     * Apakah yang diketik di layar masuk itu akun demo?
     *
     * Kolom isiannya menerima email maupun username, jadi keduanya diperiksa.
     */
    public function cocokDenganDemo(?string $isian): bool
    {
        if (! $this->aktif() || ! $isian) {
            return false;
        }

        $isian = trim($isian);

        return strcasecmp($isian, (string) config('demo.username')) === 0
            || strcasecmp($isian, (string) config('demo.email')) === 0;
    }

    /**
     * PIN kasir demo, kalau operator ini memang penghuni tenant demo.
     *
     * Nilainya datang dari config - sumber yang sama yang dipakai DemoSeeder
     * saat membuat akunnya - bukan dari basis data, karena `pos_pin` disimpan
     * ter-hash dan tidak bisa dikembalikan ke bentuk semula.
     *
     * Justru itu yang membuatnya aman: PIN kasir pelanggan tidak akan pernah
     * bisa muncul lewat jalan ini, sebab nilainya memang tidak ada yang
     * menyimpan di mana pun. Kecocokan nama saja tidak cukup - tenant-nya
     * ikut diperiksa, supaya pelanggan yang kebetulan menamai kasirnya
     * "demo-kasir1" tidak membocorkan apa pun.
     */
    public function pinKasir(User $user): ?string
    {
        if (! $this->aktif()) {
            return null;
        }

        $tenant = $this->tenantDemo();

        if (! $tenant || (int) $user->tenant_id !== (int) $tenant->id) {
            return null;
        }

        $awalan = (string) config('demo.username').'-kasir';

        if (! preg_match('/^'.preg_quote($awalan, '/').'(\d+)$/', (string) $user->username, $cocok)) {
            return null;
        }

        $daftar = array_values((array) config('demo.cashier_pins'));

        return $daftar[((int) $cocok[1]) - 1] ?? null;
    }

    /** Tenant demo, atau null bila belum pernah dibangun. */
    public function tenantDemo(): ?Tenant
    {
        return Tenant::withoutGlobalScopes()
            ->where('slug', (string) config('demo.tenant_slug'))
            ->first();
    }

    /**
     * Pulihkan bila sudah waktunya.
     *
     * Sengaja dipanggil SEBELUM kredensial diperiksa. Kalau menunggu login
     * berhasil, pengunjung yang mengganti kata sandi akun demo akan mengunci
     * semua orang - dan pemulihannya tidak akan pernah terpicu lagi.
     */
    public function pulihkanBilaPerlu(): bool
    {
        if (! $this->aktif() || ! $this->sudahWaktunya()) {
            return false;
        }

        $this->pulihkan();

        return true;
    }

    private function sudahWaktunya(): bool
    {
        $terakhir = $this->ambilWaktu();

        if (! $terakhir) {
            return true;
        }

        try {
            return Carbon::parse($terakhir)
                ->addHours((int) config('demo.reset_after_hours'))
                ->isPast();
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Bangun ulang tenant demo dari nol.
     */
    public function pulihkan(): void
    {
        // Scope apa pun yang tertinggal dari permintaan berjalan dibuang dulu,
        // supaya penghapusan dan pembuatan ulang tidak ikut tersaring.
        app(OutletContext::class)->forget();
        app(Tenancy::class)->forget();

        DB::transaction(function () {
            $this->bersihkan();
            (new DemoSeeder)->run();
        });

        $this->simpanWaktu();
    }

    /**
     * Buang seluruh isi tenant demo.
     *
     * Urutannya penting. Tabel operasional memakai cascadeOnDelete pada
     * tenant_id, tetapi tabel users memakai nullOnDelete - menghapus tenantnya
     * saja akan meninggalkan akun demo sebagai yatim dengan tenant_id kosong.
     *
     * Karena itu id akunnya dicatat lebih dulu, tenantnya dihapus supaya
     * seluruh penjualan dan shift yang merujuk akun-akun itu ikut terbuang,
     * baru akunnya dihapus terakhir - saat tidak ada lagi yang mengacu padanya.
     */
    private function bersihkan(): void
    {
        $tenant = $this->tenantDemo();

        if (! $tenant) {
            return;
        }

        $akun = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->pluck('id');

        $tenant->delete();

        User::withoutGlobalScopes()->whereIn('id', $akun)->delete();
    }

    // ------------------------------------------------- Penyimpanan waktu
    //
    // Dititipkan ke cache; aplikasi ini belum punya tabel pengaturan. Cache
    // yang terhapus hanya berakibat satu pemulihan tambahan, bukan kerusakan.

    private function ambilWaktu(): ?string
    {
        try {
            return cache()->get(self::KUNCI_WAKTU);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function simpanWaktu(): void
    {
        try {
            cache()->forever(self::KUNCI_WAKTU, now()->toIso8601String());
        } catch (\Throwable $e) {
            // Cache tidak tersedia: pemulihan tetap berhasil, hanya waktunya
            // tidak tercatat sehingga bisa terpicu lagi lebih cepat.
        }
    }
}
