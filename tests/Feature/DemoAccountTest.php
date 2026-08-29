<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DemoResetService;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): DemoResetService
    {
        return app(DemoResetService::class);
    }

    private function tenantDemo(): Tenant
    {
        return Tenant::withoutGlobalScopes()
            ->where('slug', config('demo.tenant_slug'))
            ->firstOrFail();
    }

    private function akunDemo(): User
    {
        return User::withoutGlobalScopes()
            ->where('username', config('demo.username'))
            ->firstOrFail();
    }

    /** Hitung baris satu model di dalam tenant demo. */
    private function hitung(string $model): int
    {
        return $model::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantDemo()->id)
            ->count();
    }

    public function test_pemulihan_membangun_tenant_beserta_isi_contohnya(): void
    {
        $this->demo()->pulihkan();

        $akun = $this->akunDemo();

        $this->assertSame(Role::Owner, $akun->role);
        $this->assertTrue($akun->is_active);
        $this->assertTrue(Hash::check(config('demo.password'), $akun->password));

        // Tenant tersendiri: akun demo berperan Owner, dan Owner melihat
        // seluruh outlet berikut laporan labanya. Menaruhnya di tenant asli
        // berarti membuka data penjualan pelanggan kepada siapa pun.
        $this->assertSame($this->tenantDemo()->id, $akun->tenant_id);

        $this->assertSame((int) config('demo.outlets'), $this->hitung(Outlet::class));
        $this->assertGreaterThan(0, $this->hitung(Product::class));
        $this->assertGreaterThan(0, $this->hitung(Sale::class));
        $this->assertGreaterThan(0, $this->hitung(Shift::class));
    }

    public function test_setiap_outlet_punya_penjualan_sendiri(): void
    {
        $this->demo()->pulihkan();

        $outlets = Outlet::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantDemo()->id)
            ->get();

        // Outlet tanpa kasir tidak pernah bertransaksi, dan laporan
        // perbandingan cabang - salah satu hal yang ingin diperlihatkan
        // demo - jadi tampil kosong sebelah.
        foreach ($outlets as $outlet) {
            $this->assertGreaterThan(
                0,
                Sale::withoutGlobalScopes()->where('outlet_id', $outlet->id)->count(),
                "Outlet {$outlet->code} harus punya penjualan contoh"
            );
        }
    }

    public function test_pemulihan_menghapus_jejak_pengunjung_sebelumnya(): void
    {
        $this->demo()->pulihkan();

        $akun = $this->akunDemo();
        $tenantId = $this->tenantDemo()->id;

        // Pengunjung bebas melakukan apa saja di dalam demo.
        $akun->update([
            'password' => 'sudahDiubah99',
            'name' => 'Diubah Pengunjung',
            'is_active' => false,
        ]);

        $titipan = Outlet::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'name' => 'Outlet Titipan',
            'code' => 'TTP',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 99,
        ]);

        $this->demo()->pulihkan();

        $pulih = $this->akunDemo();

        $this->assertTrue(Hash::check(config('demo.password'), $pulih->password),
            'Kata sandi yang diganti pengunjung harus kembali');
        $this->assertTrue($pulih->is_active);
        $this->assertSame(config('demo.name'), $pulih->name);

        $this->assertDatabaseMissing('outlets', ['id' => $titipan->id]);
    }

    public function test_pemulihan_tidak_menyentuh_tenant_lain(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $asli = Tenant::withoutGlobalScopes()->where('slug', 'toko-utama')->firstOrFail();
        $jumlahPenjualan = Sale::withoutGlobalScopes()->where('tenant_id', $asli->id)->count();
        $jumlahPengguna = User::withoutGlobalScopes()->where('tenant_id', $asli->id)->count();

        $this->demo()->pulihkan();

        $this->assertDatabaseHas('tenants', ['id' => $asli->id]);
        $this->assertSame($jumlahPenjualan,
            Sale::withoutGlobalScopes()->where('tenant_id', $asli->id)->count());
        $this->assertSame($jumlahPengguna,
            User::withoutGlobalScopes()->where('tenant_id', $asli->id)->count());
    }

    public function test_pemulihan_tidak_meninggalkan_akun_yatim(): void
    {
        $this->demo()->pulihkan();
        $this->demo()->pulihkan();

        // Kolom tenant_id pada users memakai nullOnDelete, jadi menghapus
        // tenantnya saja akan meninggalkan akun tanpa tenant yang menumpuk
        // setiap kali pemulihan berjalan.
        $this->assertSame(0, User::withoutGlobalScopes()->whereNull('tenant_id')->count());
    }

    public function test_pemulihan_hanya_sekali_dalam_selang_waktunya(): void
    {
        $this->assertTrue($this->demo()->pulihkanBilaPerlu(), 'Pertama kali selalu dipulihkan');

        // Tiap percobaan masuk memicu pemeriksaan ini. Kalau membangun ulang
        // setiap kali, pekerjaan orang yang sedang mencoba akan terhapus.
        $this->assertFalse($this->demo()->pulihkanBilaPerlu());
    }

    public function test_masuk_memicu_pemulihan_walau_kata_sandi_diganti(): void
    {
        $this->demo()->pulihkan();
        $this->akunDemo()->update(['password' => 'dikunciPengunjung']);

        cache()->forget('demo_last_reset');

        $this->post(route('admin.login.attempt'), [
            'login' => config('demo.username'),
            'password' => config('demo.password'),
        ]);

        $this->assertAuthenticated();
    }

    public function test_layar_masuk_menyediakan_tombol_demo(): void
    {
        $html = $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertStringContainsString('isiDemo()', $html);
        $this->assertStringContainsString((string) config('demo.username'), $html);
        $this->assertStringContainsString('Coba Demo', $html);
    }

    public function test_tombol_demo_hilang_bila_fitur_dimatikan(): void
    {
        config(['demo.username' => '']);

        $html = $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('isiDemo()', $html);
    }
}
