<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengelolaan kuota penyimpanan oleh superadministrator.
 */
class QuotaManagementTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1073741824;

    private function company(string $nama, array $extra = []): Company
    {
        return Company::create(array_merge([
            'name' => $nama,
            'default_quota' => User::DEFAULT_STORAGE_QUOTA,
            'is_active' => true,
        ], $extra));
    }

    private function user(?Company $company, string $role = User::ROLE_USER, array $extra = []): User
    {
        return User::create(array_merge([
            'company_id' => $company?->id,
            'name' => 'Pengguna ' . uniqid(),
            'email' => 'u' . uniqid() . '@dekorasi.test',
            'password' => Hash::make('password123'),
            'role' => $role,
            'storage_quota' => User::DEFAULT_STORAGE_QUOTA,
            'storage_used' => 0,
            'is_active' => true,
        ], $extra));
    }

    // ------------------------------------------------------------ Akses

    public function test_hanya_superadmin_yang_bisa_membuka_kelola_kuota(): void
    {
        $company = $this->company('PT A');
        $admin = $this->user($company, User::ROLE_ADMIN);
        $biasa = $this->user($company);
        $korban = $this->user($company);

        foreach ([$admin, $biasa] as $pelaku) {
            $this->actingAs($pelaku)->get('/admin/quotas')->assertForbidden();

            $this->actingAs($pelaku)
                ->put('/admin/quotas/' . $korban->id, ['quota_gb' => 99])
                ->assertForbidden();

            $this->actingAs($pelaku)
                ->post('/admin/quotas/bulk', ['quota_gb' => 99, 'target' => 'all'])
                ->assertForbidden();
        }

        // Kuota tidak boleh berubah sedikit pun oleh percobaan di atas.
        $this->assertSame(User::DEFAULT_STORAGE_QUOTA, $korban->fresh()->storage_quota);
    }

    public function test_superadmin_melihat_pengguna_seluruh_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $this->user($this->company('PT A'), User::ROLE_USER, ['name' => 'Anggota Alpha']);
        $this->user($this->company('PT B'), User::ROLE_USER, ['name' => 'Anggota Beta']);

        $this->actingAs($super)->get('/admin/quotas')
            ->assertOk()
            ->assertSee('Anggota Alpha')
            ->assertSee('Anggota Beta');
    }

    // ------------------------------------------------- Ubah satu pengguna

    public function test_kuota_satu_pengguna_bisa_diubah(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'));

        $this->actingAs($super)
            ->put('/admin/quotas/' . $target->id, ['quota_gb' => 5])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(5 * self::GB, $target->fresh()->storage_quota);
    }

    public function test_perubahan_kuota_memberi_tahu_penggunanya(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'));

        $this->actingAs($super)->put('/admin/quotas/' . $target->id, ['quota_gb' => 5]);

        // Perubahan kuota terasa langsung oleh pengguna; jangan sampai mereka
        // baru tahu dari kegagalan unggah.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $target->id,
            'type' => 'quota_changed',
        ]);
    }

    public function test_kuota_di_bawah_pemakaian_diperingatkan_tapi_tetap_disimpan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'), User::ROLE_USER, [
            'storage_used' => 3 * self::GB,
        ]);

        $response = $this->actingAs($super)
            ->put('/admin/quotas/' . $target->id, ['quota_gb' => 1])
            ->assertRedirect();

        $this->assertSame(1 * self::GB, $target->fresh()->storage_quota);
        $this->assertStringContainsString('di bawah pemakaian', session('success'));
    }

    public function test_kuota_angka_bulat_bisa_disimpan_persis(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'));

        // Regresi: input memakai min="0.01" dengan step="0.1", sehingga browser
        // hanya menganggap 0.01, 0.11, ... 99.91, 100.01 sebagai nilai sah dan
        // menolak angka bulat seperti 100.
        foreach ([1, 10, 50, 100, 512] as $gb) {
            $this->actingAs($super)
                ->put('/admin/quotas/' . $target->id, ['quota_gb' => $gb])
                ->assertSessionHasNoErrors();

            $this->assertSame($gb * self::GB, $target->fresh()->storage_quota,
                "Kuota {$gb} GB harus tersimpan persis");
        }
    }

    public function test_input_kuota_tidak_memaksa_kelipatan_tertentu(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $this->user($this->company('PT A'));

        $html = $this->actingAs($super)->get('/admin/quotas')->assertOk()->getContent();

        // step="0.1" berpasangan dengan min="0.01" adalah sumber penolakan
        // angka bulat oleh browser. step="any" membebaskan nilainya.
        $this->assertStringNotContainsString('step="0.1"', $html);
        $this->assertStringContainsString('step="any"', $html);
    }

    public function test_kuota_pecahan_bebas_tetap_diterima(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'));

        $this->actingAs($super)
            ->put('/admin/quotas/' . $target->id, ['quota_gb' => 2.75])
            ->assertSessionHasNoErrors();

        $this->assertSame((int) round(2.75 * self::GB), $target->fresh()->storage_quota);
    }

    public function test_kuota_tidak_valid_ditolak(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $target = $this->user($this->company('PT A'));

        $this->actingAs($super)
            ->put('/admin/quotas/' . $target->id, ['quota_gb' => 0])
            ->assertSessionHasErrors('quota_gb');

        $this->actingAs($super)
            ->put('/admin/quotas/' . $target->id, ['quota_gb' => 'banyak'])
            ->assertSessionHasErrors('quota_gb');

        $this->assertSame(User::DEFAULT_STORAGE_QUOTA, $target->fresh()->storage_quota);
    }

    // --------------------------------------------------------- Massal

    public function test_atur_massal_satu_perusahaan_tidak_menyentuh_perusahaan_lain(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $a = $this->company('PT A');
        $b = $this->company('PT B');

        $anggotaA1 = $this->user($a);
        $anggotaA2 = $this->user($a);
        $anggotaB = $this->user($b);

        $this->actingAs($super)->post('/admin/quotas/bulk', [
            'quota_gb' => 7,
            'target' => 'company',
            'company_id' => $a->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(7 * self::GB, $anggotaA1->fresh()->storage_quota);
        $this->assertSame(7 * self::GB, $anggotaA2->fresh()->storage_quota);
        $this->assertSame(User::DEFAULT_STORAGE_QUOTA, $anggotaB->fresh()->storage_quota,
            'Perusahaan lain tidak boleh ikut berubah');
    }

    public function test_atur_massal_seluruh_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $a = $this->user($this->company('PT A'));
        $b = $this->user($this->company('PT B'));

        $this->actingAs($super)->post('/admin/quotas/bulk', [
            'quota_gb' => 2,
            'target' => 'all',
        ])->assertRedirect();

        $this->assertSame(2 * self::GB, $a->fresh()->storage_quota);
        $this->assertSame(2 * self::GB, $b->fresh()->storage_quota);
    }

    public function test_atur_massal_wajib_menyebut_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);

        $this->actingAs($super)->post('/admin/quotas/bulk', [
            'quota_gb' => 3,
            'target' => 'company',
        ])->assertSessionHasErrors('company_id');
    }

    // ------------------------------------------- Samakan ke kuota bawaan

    public function test_samakan_ke_kuota_bawaan_perusahaan(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $company = $this->company('PT Rapi', ['default_quota' => 4 * self::GB]);

        $menyimpang = $this->user($company, User::ROLE_USER, ['storage_quota' => 100 * 1048576]);
        $sudahPas = $this->user($company, User::ROLE_USER, ['storage_quota' => 4 * self::GB]);

        $this->actingAs($super)
            ->post('/admin/quotas/company/' . $company->id . '/default')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(4 * self::GB, $menyimpang->fresh()->storage_quota);
        $this->assertSame(4 * self::GB, $sudahPas->fresh()->storage_quota);

        // Yang sudah sesuai tidak perlu diberi notifikasi ulang.
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $sudahPas->id,
            'type' => 'quota_changed',
        ]);
    }

    public function test_perusahaan_tanpa_pengguna_memberi_pesan_jelas(): void
    {
        $super = $this->user(null, User::ROLE_SUPERADMIN);
        $kosong = $this->company('PT Kosong');

        $this->actingAs($super)
            ->post('/admin/quotas/company/' . $kosong->id . '/default')
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
