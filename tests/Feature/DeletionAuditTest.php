<?php

use App\Models\Classification;
use App\Models\Digital;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

dataset('auditable deletion routes', [
    'surat masuk' => [Incoming::class, 'masuk.hapus', 'surat.masuk'],
    'surat keluar' => [Outcoming::class, 'keluar.hapus', 'surat.keluar'],
    'surat digital' => [Digital::class, 'digital.hapus', 'surat.digital'],
    'klasifikasi' => [Classification::class, 'klasifikasi.hapus', 'surat.klasifikasi'],
    'berkas' => [Filelist::class, 'berkas.hapus', 'surat.berkas'],
]);

test('setiap tabel soft delete memiliki kolom audit penghapusan', function () {
    foreach (['incomings', 'outcomings', 'digitals', 'classifications', 'filelists'] as $table) {
        expect(Schema::hasColumns($table, [
            'deleted_by_user_id',
            'deletion_reason',
        ]))->toBeTrue();
    }
});

test('alasan wajib diisi sebelum data dapat dihapus', function () {
    $user = User::factory()->create();
    $digital = Digital::create([
        'perihal' => 'Dokumen yang tidak boleh dihapus tanpa alasan',
        'url' => 'dokumen/digital/audit.pdf',
    ]);

    $this->actingAs($user)
        ->delete(route('digital.hapus', $digital->id))
        ->assertRedirect()
        ->assertSessionHasErrors('alasan_penghapusan');

    expect($digital->fresh()->deleted_at)->toBeNull()
        ->and(Activity::query()
            ->where('subject_type', Digital::class)
            ->where('subject_id', $digital->id)
            ->where('event', 'deleted')
            ->exists())->toBeFalse();
});

test('penghapusan mencatat pelaku dan alasan pada data serta activity log', function (
    string $modelClass,
    string $deleteRoute,
    string $redirectRoute
) {
    $user = User::factory()->create();
    $this->withActiveYear(2026);
    $this->actingAs($user);

    $record = makeAuditableDeletionRecord($modelClass);
    $reason = 'Duplikat saat proses verifikasi arsip';
    Activity::query()->delete();

    $this->delete(route($deleteRoute, $record->getKey()), [
        'alasan_penghapusan' => $reason,
    ])->assertRedirect(route($redirectRoute));

    $deletedRecord = $modelClass::withTrashed()->findOrFail($record->getKey());
    $activity = Activity::query()
        ->where('subject_type', $modelClass)
        ->where('subject_id', $record->getKey())
        ->where('event', 'deleted')
        ->latest('id')
        ->firstOrFail();

    expect($deletedRecord->deleted_at)->not->toBeNull()
        ->and((int) $deletedRecord->deleted_by_user_id)->toBe($user->id)
        ->and($deletedRecord->deletion_reason)->toBe($reason)
        ->and($deletedRecord->deletedBy->is($user))->toBeTrue()
        ->and((int) $activity->causer_id)->toBe($user->id)
        ->and($activity->properties->get('attributes'))->toMatchArray([
            'deleted_by_user_id' => $user->id,
            'deleted_by_name' => $user->name,
            'deletion_reason' => $reason,
        ]);
})->with('auditable deletion routes');

test('log aktivitas menyimpan alasan penghapusan sebagai attribute detail', function () {
    $user = User::factory()->create();
    $digital = Digital::create([
        'perihal' => 'Dokumen duplikat',
        'url' => 'dokumen/digital/duplikat.pdf',
    ]);
    $reason = 'Dokumen tercatat dua kali';

    $this->actingAs($user)
        ->delete(route('digital.hapus', $digital->id), [
            'alasan_penghapusan' => $reason,
        ])
        ->assertRedirect(route('surat.digital'));

    $this->get(route('activity-log'))
        ->assertOk()
        ->assertDontSee('Alasan Penghapusan')
        ->assertSee('Bagian Data')
        ->assertDontSee('Bagian (Model)');

    $response = $this->get(route('activity-log', ['length' => 100]), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk();

    $deletedActivity = collect($response->json('data'))
        ->firstWhere('event', 'deleted');
    $createdActivity = collect($response->json('data'))
        ->firstWhere('event', 'created');

    expect($deletedActivity)->not->toHaveKey('alasan_penghapusan')
        ->and($deletedActivity['pelaku'])->toBe($user->name)
        ->and($deletedActivity['description'])->toContain('badge-danger')
        ->and($deletedActivity['description'])->toContain('Data Dihapus')
        ->and($deletedActivity['model'])->toBe('Surat Digital')
        ->and($deletedActivity['perubahan'])->not->toContain('title="Data Sekarang"')
        ->and($createdActivity['description'])->toContain('badge-success')
        ->and($createdActivity['description'])->toContain('Data Dibuat')
        ->and($createdActivity['model'])->toBe('Surat Digital')
        ->and($createdActivity['perubahan'])->not->toContain('title="Data Sekarang"')
        ->and(data_get($deletedActivity, 'properties.attributes.deleted_by_name'))->toBe($user->name)
        ->and(data_get($deletedActivity, 'properties.attributes.deletion_reason'))->toBe($reason);
});

test('log aktivitas menampilkan nama bagian dan badge aktivitas dalam bahasa Indonesia', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    foreach ([
        Incoming::class,
        Outcoming::class,
        Digital::class,
        Classification::class,
        Filelist::class,
    ] as $modelClass) {
        makeAuditableDeletionRecord($modelClass);
    }

    activity('auth')
        ->causedBy($user)
        ->log('User successfully logged in');

    activity('export')
        ->causedBy($user)
        ->event('exported')
        ->log('Export daftar_klasifikasi disiapkan');

    $activities = collect($this->get(route('activity-log', ['length' => 100]), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk()->json('data'));

    foreach ([
        Incoming::class => 'Surat Masuk',
        Outcoming::class => 'Surat Keluar',
        Digital::class => 'Surat Digital',
        Classification::class => 'Klasifikasi',
        Filelist::class => 'Berkas',
    ] as $subjectType => $expectedLabel) {
        expect($activities->firstWhere('subject_type', $subjectType)['model'])
            ->toBe($expectedLabel);
    }

    $loginActivity = $activities->firstWhere('log_name', 'auth');
    $exportActivity = $activities->firstWhere('event', 'exported');
    $activeDigitalActivity = $activities
        ->where('subject_type', Digital::class)
        ->firstWhere('event', 'created');

    expect($loginActivity['description'])->toContain('badge-primary')
        ->and($loginActivity['description'])->toContain('Masuk ke Sistem')
        ->and($loginActivity['model'])->toBe('Autentikasi')
        ->and($exportActivity['description'])->toContain('badge-warning')
        ->and($exportActivity['description'])->toContain('Ekspor Disiapkan')
        ->and($exportActivity['model'])->toBe('Ekspor Data')
        ->and($activeDigitalActivity['perubahan'])->toContain('title="Data Sekarang"');
});

test('tombol data sekarang surat digital membuka detail item dan bukan halaman edit', function () {
    $user = User::factory()->create();
    $digital = Digital::create([
        'perihal' => 'Dokumen Digital untuk Detail Log',
        'url' => 'dokumen/digital/detail-log.pdf',
    ]);

    $this->actingAs($user);

    $activity = collect($this->get(route('activity-log', ['length' => 100]), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk()->json('data'))
        ->where('subject_type', Digital::class)
        ->firstWhere('event', 'created');

    expect($activity['perubahan'])
        ->toContain(route('surat.detailItem', ['digital', $digital->id]))
        ->not->toContain(route('digital.edit', $digital->id));

    $this->get(route('surat.detailItem', ['digital', $digital->id]))
        ->assertOk()
        ->assertViewIs('app.surat.detail-item')
        ->assertViewHas('jenis', 'digital')
        ->assertViewHas('requiresYearSwitch', false)
        ->assertSee('Surat Digital')
        ->assertSee('Dokumen Digital untuk Detail Log')
        ->assertDontSee('class="nomor-surat"', false)
        ->assertDontSee('Kearsipan')
        ->assertDontSee('Dokumen Watermark');
});

test('detail surat hanya menampilkan nomor dan perihal sekali di header', function () {
    $user = User::factory()->create();
    $incoming = Incoming::create([
        'nomor_agenda' => 102,
        'tanggal_diterima' => '2026-08-01',
        'nomor_surat' => 'IN/DETAIL/UNIK/001',
        'pengirim' => 'Pengirim Detail',
        'tanggal_surat' => '2026-07-31',
        'perihal' => 'Perihal Detail yang Tidak Berulang',
        'url' => 'dokumen/masuk/detail-unik.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
    ]);

    $response = $this->withActiveYear(2026)
        ->actingAs($user)
        ->get(route('surat.detailItem', ['masuk', $incoming->id]))
        ->assertOk()
        ->assertDontSee('class="detail-label">Nomor Surat</div>', false)
        ->assertDontSee('class="detail-label">Perihal</div>', false);

    expect(substr_count($response->getContent(), $incoming->nomor_surat))->toBe(1)
        ->and(substr_count($response->getContent(), $incoming->perihal))->toBe(1);
});

function makeAuditableDeletionRecord(string $modelClass): Model
{
    return match ($modelClass) {
        Incoming::class => Incoming::create([
            'nomor_agenda' => 101,
            'tanggal_diterima' => '2026-07-29',
            'nomor_surat' => 'IN/AUDIT/001',
            'pengirim' => 'Pengirim',
            'tanggal_surat' => '2026-07-28',
            'perihal' => 'Surat Masuk Audit',
            'url' => 'dokumen/masuk/audit.pdf',
            'tahun' => 2026,
            'is_srikandi' => false,
        ]),
        Outcoming::class => Outcoming::create([
            'tanggal_surat' => '2026-07-29',
            'nomor_surat' => 'OUT/AUDIT/001',
            'tujuan' => 'Tujuan',
            'perihal' => 'Surat Keluar Audit',
            'url' => 'dokumen/keluar/audit.pdf',
            'tahun' => 2026,
            'is_digital' => false,
            'is_srikandi' => false,
        ]),
        Digital::class => Digital::create([
            'perihal' => 'Dokumen Digital Audit',
            'url' => 'dokumen/digital/audit.pdf',
        ]),
        Classification::class => Classification::create([
            'kode_klasifikasi' => 'AU.01',
            'keterangan' => 'Klasifikasi Audit',
        ]),
        Filelist::class => makeAuditableFilelist(),
    };
}

function makeAuditableFilelist(): Filelist
{
    $status = Status::create([
        'nama_status' => Status::ACTIVE,
    ]);
    $classification = Classification::create([
        'kode_klasifikasi' => 'AU.02',
        'keterangan' => 'Klasifikasi Berkas Audit',
    ]);

    return Filelist::create([
        'classification_id' => $classification->id,
        'nama_berkas' => 'Berkas Audit',
        'status_id' => $status->id,
        'retensi_aktif' => 1,
        'retensi_inaktif' => 1,
        'keterangan_akhir' => 'Permanen',
    ]);
}
