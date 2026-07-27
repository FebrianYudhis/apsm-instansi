<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\Classification;
use App\Models\Incoming;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rejects_an_invalid_year_before_authentication()
    {
        config(['app.start_year' => 2025]);
        $user = User::factory()->create(['tahun' => 2025]);

        $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password',
            'tahun' => 9999,
        ])->assertSessionHasErrors('tahun');

        $this->assertGuest();
        $this->assertSame(2025, (int) $user->fresh()->tahun);
    }

    public function test_filelist_validation_rejects_unknown_classification_and_negative_retention()
    {
        $user = User::factory()->create(['tahun' => now()->year]);
        $this->actingAs($user);

        $this->post(route('berkas.tambah'), [
            'kodeKlasifikasi' => 999999,
            'namaBerkas' => 'Berkas tidak valid',
            'retensiAktif' => -1,
            'retensiInaktif' => 'bukan angka',
            'keteranganAkhir' => 'Permanen',
        ])->assertSessionHasErrors([
            'kodeKlasifikasi',
            'retensiAktif',
            'retensiInaktif',
        ]);

        $this->assertDatabaseMissing('filelists', [
            'nama_berkas' => 'Berkas tidak valid',
        ]);
    }

    public function test_classification_validation_respects_database_column_lengths()
    {
        $user = User::factory()->create(['tahun' => now()->year]);
        $this->actingAs($user);

        $this->post(route('klasifikasi.tambah'), [
            'kodeKlasifikasi' => str_repeat('K', 256),
            'keterangan' => str_repeat('X', 256),
        ])->assertSessionHasErrors(['kodeKlasifikasi', 'keterangan']);

        $this->assertSame(0, Classification::count());
    }

    public function test_year_switch_rejects_external_redirects_and_uses_a_safe_default()
    {
        config(['app.start_year' => 2025]);
        $user = User::factory()->create(['tahun' => 2025]);
        $this->actingAs($user);

        $this->post(route('pindah-tahun', 2026), [
            'redirect_to' => 'https://example.com/phishing',
        ])->assertRedirect(route('surat.masuk'));

        $this->assertSame(2026, (int) $user->fresh()->tahun);
    }

    public function test_year_switch_rejects_years_outside_the_configured_range()
    {
        config(['app.start_year' => 2025]);
        $user = User::factory()->create(['tahun' => 2025]);
        $this->actingAs($user);

        $this->post(route('pindah-tahun', 2024))->assertNotFound();
        $this->assertSame(2025, (int) $user->fresh()->tahun);
    }

    public function test_detail_item_can_view_a_different_year_without_granting_edit_access()
    {
        $user = User::factory()->create(['tahun' => 2026]);
        $access = Access::create(['sifat_akses' => 'Biasa']);
        $surat = Incoming::create([
            'nomor_agenda' => 1,
            'tanggal_diterima' => '2025-01-02',
            'nomor_surat' => 'OLD/001',
            'pengirim' => 'Pengirim',
            'tanggal_surat' => '2025-01-01',
            'perihal' => 'Surat Tahun Lama',
            'url' => 'dokumen/masuk/lama.pdf',
            'tahun' => 2025,
            'is_srikandi' => false,
            'access_id' => $access->id,
        ]);

        $this->actingAs($user)->from('/app')
            ->get(route('surat.detailItem', ['masuk', $surat->id]))
            ->assertOk()
            ->assertViewHas('requiresYearSwitch', true);
    }

    public function test_filelist_date_filters_reject_an_inverted_range()
    {
        $user = User::factory()->create(['tahun' => 2026]);

        $this->actingAs($user)
            ->get(route('surat.berkas', [
                'tanggal_dari' => '2026-07-20',
                'tanggal_sampai' => '2026-07-01',
            ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertSessionHasErrors('tanggal_sampai');
    }
}
