<?php

namespace Tests\Feature;

use App\Models\Classification;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BerkasIntegrityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    private $classification;

    private $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['tahun' => 2026]);
        Status::create(['id' => 1, 'nama_status' => Status::ACTIVE]);
        $this->classification = Classification::create([
            'kode_klasifikasi' => 'TU.01',
            'keterangan' => 'Tata Usaha',
        ]);
        $this->source = $this->makeFilelist('Berkas Asal');

        $this->actingAs($this->user);
    }

    public function test_date_filter_ignores_soft_deleted_letters()
    {
        $incoming = Incoming::create([
            'nomor_agenda' => 1,
            'tanggal_diterima' => '2026-07-10',
            'nomor_surat' => 'IN/001',
            'pengirim' => 'Pengirim',
            'tanggal_surat' => '2026-07-10',
            'perihal' => 'Surat yang sudah dihapus',
            'url' => 'dokumen/masuk/soft-deleted.pdf',
            'tahun' => 2026,
            'is_srikandi' => false,
            'filelist_id' => $this->source->id,
        ]);
        $incoming->delete();

        $this->get(route('surat.berkas', [
            'tanggal_dari' => '2026-07-01',
            'tanggal_sampai' => '2026-07-31',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 0);
    }

    public function test_bulk_move_records_activity_for_each_letter()
    {
        $target = $this->makeFilelist('Berkas Tujuan');
        $incoming = Incoming::create([
            'nomor_agenda' => 1,
            'tanggal_diterima' => '2026-07-10',
            'nomor_surat' => 'IN/001',
            'pengirim' => 'Pengirim',
            'tanggal_surat' => '2026-07-10',
            'perihal' => 'Surat Masuk',
            'url' => 'dokumen/masuk/masuk.pdf',
            'tahun' => 2026,
            'is_srikandi' => false,
            'filelist_id' => $this->source->id,
        ]);
        $outcoming = Outcoming::create([
            'tanggal_surat' => '2026-07-10',
            'nomor_surat' => 'OUT/001',
            'tujuan' => 'Tujuan',
            'perihal' => 'Surat Keluar',
            'url' => 'dokumen/keluar/keluar.pdf',
            'tahun' => 2026,
            'is_digital' => false,
            'is_srikandi' => false,
            'filelist_id' => $this->source->id,
        ]);

        Activity::query()->delete();

        $this->from(route('berkas.buka', $this->source->id))
            ->post(route('berkas.gantiLokasiBulk'), [
                'berkas_asal' => $this->source->id,
                'pemberkasan' => $target->id,
                'items' => [
                    'masuk:'.$incoming->id,
                    'keluar:'.$outcoming->id,
                ],
            ])
            ->assertRedirect(route('berkas.buka', $this->source->id));

        $this->assertSame($target->id, $incoming->fresh()->filelist_id);
        $this->assertSame($target->id, $outcoming->fresh()->filelist_id);

        $activities = Activity::where('event', 'updated')
            ->whereIn('subject_type', [Incoming::class, Outcoming::class])
            ->get();

        $this->assertCount(2, $activities);
        $this->assertEqualsCanonicalizing(
            [$incoming->id, $outcoming->id],
            $activities->pluck('subject_id')->map(function ($id) {
                return (int) $id;
            })->all()
        );

        foreach ($activities as $activity) {
            $this->assertSame(
                $target->id,
                (int) $activity->properties->get('attributes')['filelist_id']
            );
        }
    }

    public function test_document_storage_failure_is_reported_as_a_validation_error()
    {
        $file = \Mockery::mock(UploadedFile::class);
        $file->shouldReceive('store')
            ->once()
            ->with('dokumen/digital', config('documents.disk'))
            ->andReturn(false);

        try {
            app(DocumentService::class)->storeOriginal(
                DocumentService::TYPE_DIGITAL,
                $file
            );
            $this->fail('Kegagalan storage seharusnya menghentikan penyimpanan dokumen.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('berkas', $exception->errors());
        }
    }

    public function test_filelist_create_and_update_reject_a_soft_deleted_classification()
    {
        $deletedClassification = Classification::create([
            'kode_klasifikasi' => 'TU.99',
            'keterangan' => 'Klasifikasi Terhapus',
        ]);
        $deletedClassification->delete();

        $this->from(route('berkas.tambah'))
            ->post(route('berkas.tambah'), [
                'kodeKlasifikasi' => $deletedClassification->id,
                'namaBerkas' => 'Berkas Manipulatif',
                'retensiAktif' => 1,
                'retensiInaktif' => 1,
                'keteranganAkhir' => 'Permanen',
            ])
            ->assertRedirect(route('berkas.tambah'))
            ->assertSessionHasErrors('kodeKlasifikasi');

        $this->assertDatabaseMissing('filelists', [
            'nama_berkas' => 'Berkas Manipulatif',
        ]);

        $this->from(route('berkas.edit', $this->source->id))
            ->post(route('berkas.edit', $this->source->id), [
                'kodeKlasifikasi' => $deletedClassification->id,
                'namaBerkas' => 'Berkas Diubah',
                'retensiAktif' => 2,
                'retensiInaktif' => 2,
                'keteranganAkhir' => 'Musnah',
            ])
            ->assertRedirect(route('berkas.edit', $this->source->id))
            ->assertSessionHasErrors('kodeKlasifikasi');

        $this->source->refresh();
        $this->assertSame($this->classification->id, $this->source->classification_id);
        $this->assertSame('Berkas Asal', $this->source->nama_berkas);
    }

    public function test_active_classification_code_is_unique_after_normalization()
    {
        $this->from(route('klasifikasi.tambah'))
            ->post(route('klasifikasi.tambah'), [
                'kodeKlasifikasi' => '  tu.01  ',
                'keterangan' => 'Duplikat',
            ])
            ->assertRedirect(route('klasifikasi.tambah'))
            ->assertSessionHasErrors('kodeKlasifikasi');

        $this->assertSame(
            1,
            Classification::where('kode_klasifikasi', 'TU.01')->count()
        );
    }

    public function test_database_rejects_duplicate_active_classification_code()
    {
        $this->expectException(QueryException::class);

        Classification::create([
            'kode_klasifikasi' => ' tu.01 ',
            'keterangan' => 'Duplikat melalui model',
        ]);
    }

    public function test_soft_deleted_classification_code_can_be_reused()
    {
        $classification = Classification::create([
            'kode_klasifikasi' => 'HK.01',
            'keterangan' => 'Klasifikasi Lama',
        ]);
        $classification->delete();

        $replacement = Classification::create([
            'kode_klasifikasi' => ' hk.01 ',
            'keterangan' => 'Klasifikasi Baru',
        ]);

        $this->assertNotSame($classification->id, $replacement->id);
        $this->assertNull($replacement->deleted_at);
    }

    public function test_classification_with_an_active_filelist_cannot_be_deleted()
    {
        $this->delete(route('klasifikasi.hapus', $this->classification->id))
            ->assertRedirect(route('surat.klasifikasi'));

        $this->assertNull($this->classification->fresh()->deleted_at);
        $this->assertDatabaseHas('filelists', [
            'id' => $this->source->id,
            'classification_id' => $this->classification->id,
            'deleted_at' => null,
        ]);
    }

    private function makeFilelist(string $name): Filelist
    {
        return Filelist::create([
            'classification_id' => $this->classification->id,
            'nama_berkas' => $name,
            'status_id' => 1,
            'retensi_aktif' => 1,
            'retensi_inaktif' => 1,
            'keterangan_akhir' => 'Permanen',
        ]);
    }
}
