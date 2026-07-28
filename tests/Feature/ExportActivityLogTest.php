<?php

namespace Tests\Feature;

use App\Models\Classification;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\AlihMediaStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ExportActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_exports_are_recorded_without_letter_contents()
    {
        $user = User::factory()->create();

        Incoming::create([
            'nomor_agenda' => null,
            'tanggal_diterima' => '2026-02-21',
            'nomor_surat' => 'RAHASIA/MASUK',
            'pengirim' => 'Pengirim',
            'tanggal_surat' => '2026-02-20',
            'perihal' => 'Isi surat masuk tidak boleh masuk log',
            'url' => 'dokumen/masuk/audit-export.pdf',
            'tahun' => 2026,
            'is_srikandi' => true,
        ]);
        Outcoming::create([
            'tanggal_surat' => '2026-03-20',
            'nomor_surat' => 'RAHASIA/KELUAR',
            'tujuan' => 'Tujuan',
            'perihal' => 'Isi surat keluar tidak boleh masuk log',
            'url' => 'dokumen/keluar/audit-export.pdf',
            'tahun' => 2026,
            'is_digital' => true,
            'is_srikandi' => true,
        ]);
        Classification::create([
            'kode_klasifikasi' => 'AU.01',
            'keterangan' => 'Audit export',
        ]);

        Activity::query()->delete();
        $this->withActiveYear(2026)->actingAs($user);

        $this->get(route('surat.masuk.export-pencatatan', [
            'sumber_surat' => 'srikandi',
            'tanggal_dari' => '2026-02-01',
            'tanggal_sampai' => '2026-02-28',
        ]))
            ->assertOk()
            ->assertDownload();

        $this->get(route('surat.keluar.export-pencatatan', [
            'jalur_pengiriman' => 'srikandi',
            'tanggal_dari' => '2026-03-01',
            'tanggal_sampai' => '2026-03-31',
        ]))
            ->assertOk()
            ->assertDownload();

        $this->get(route('surat.klasifikasi.export'))
            ->assertOk()
            ->assertDownload();

        $activities = Activity::query()
            ->where('log_name', 'export')
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $activities);

        $incoming = $activities->firstWhere(
            'description',
            'Export pencatatan_surat_masuk disiapkan'
        );
        $this->assertNotNull($incoming);
        $this->assertSame('exported', $incoming->event);
        $this->assertSame($user->id, (int) $incoming->causer_id);
        $this->assertSame(2026, $incoming->properties->get('tahun_aktif'));
        $this->assertSame(1, $incoming->properties->get('jumlah_baris'));
        $this->assertSame(
            'srikandi',
            $incoming->properties->get('filter')['sumber_surat']
        );

        $outgoing = $activities->firstWhere(
            'description',
            'Export pencatatan_surat_keluar disiapkan'
        );
        $this->assertNotNull($outgoing);
        $this->assertSame(1, $outgoing->properties->get('jumlah_baris'));
        $this->assertSame(
            '2026-03-31',
            $outgoing->properties->get('filter')['tanggal_sampai']
        );

        $classification = $activities->firstWhere(
            'description',
            'Export daftar_klasifikasi disiapkan'
        );
        $this->assertNotNull($classification);
        $this->assertSame(1, $classification->properties->get('jumlah_baris'));
        $this->assertSame(
            'global_aktif',
            $classification->properties->get('cakupan')
        );

        $serializedProperties = $activities->pluck('properties')->toJson();
        $this->assertStringNotContainsString('RAHASIA/MASUK', $serializedProperties);
        $this->assertStringNotContainsString('RAHASIA/KELUAR', $serializedProperties);
        $this->assertStringNotContainsString(
            'Isi surat masuk tidak boleh masuk log',
            $serializedProperties
        );
    }

    public function test_existing_berkas_and_alih_media_exports_are_also_recorded()
    {
        $this->seed(AlihMediaStatusSeeder::class);

        $user = User::factory()->create();
        $status = Status::create(['nama_status' => 'Aktif']);
        $classification = Classification::create([
            'kode_klasifikasi' => 'AM.01',
            'keterangan' => 'Alih media',
        ]);
        $processing = Filelist::create([
            'classification_id' => $classification->id,
            'nama_berkas' => 'Diproses',
            'status_id' => $status->id,
            'retensi_aktif' => 1,
            'retensi_inaktif' => 1,
            'keterangan_akhir' => 'Permanen',
            'alih_media_status_id' => Filelist::ALIH_MEDIA_PROCESSING,
        ]);
        $closed = Filelist::create([
            'classification_id' => $classification->id,
            'nama_berkas' => 'Ditutup',
            'status_id' => $status->id,
            'retensi_aktif' => 1,
            'retensi_inaktif' => 1,
            'keterangan_akhir' => 'Permanen',
            'alih_media_status_id' => Filelist::ALIH_MEDIA_CLOSED,
        ]);

        Incoming::create([
            'nomor_agenda' => 1,
            'tanggal_diterima' => '2026-04-21',
            'nomor_surat' => 'AM/MASUK',
            'pengirim' => 'Pengirim',
            'tanggal_surat' => '2026-04-20',
            'perihal' => 'Alih media masuk',
            'url' => 'dokumen/masuk/alih-media.pdf',
            'tahun' => 2026,
            'is_srikandi' => false,
            'filelist_id' => $processing->id,
        ]);
        Outcoming::create([
            'tanggal_surat' => '2026-05-20',
            'nomor_surat' => 'AM/KELUAR',
            'tujuan' => 'Tujuan',
            'perihal' => 'Alih media keluar',
            'url' => 'dokumen/keluar/alih-media.pdf',
            'tahun' => 2026,
            'is_digital' => false,
            'is_srikandi' => false,
            'filelist_id' => $closed->id,
        ]);

        Activity::query()->delete();
        $this->withActiveYear(2026)->actingAs($user);

        $this->get(route('surat.berkas.export', [
            'jenis_export' => 'daftar_isi_berkas',
        ]))
            ->assertOk()
            ->assertDownload();
        $this->get(route('alih-media.diproses.export-daftar-arsip'))
            ->assertOk()
            ->assertDownload();
        $this->get(route('alih-media.selesai.export-daftar-arsip'))
            ->assertOk()
            ->assertDownload();

        $activities = Activity::query()
            ->where('log_name', 'export')
            ->get()
            ->keyBy('description');

        $this->assertCount(3, $activities);
        $this->assertSame(
            2,
            $activities['Export daftar_isi_berkas disiapkan']
                ->properties
                ->get('jumlah_baris')
        );
        $this->assertSame(
            1,
            $activities['Export daftar_arsip_alih_media_diproses disiapkan']
                ->properties
                ->get('jumlah_baris')
        );
        $this->assertSame(
            1,
            $activities['Export daftar_arsip_alih_media_selesai disiapkan']
                ->properties
                ->get('jumlah_baris')
        );
    }
}
