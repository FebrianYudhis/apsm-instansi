<?php

use App\Models\AlihMediaStatus;
use App\Models\Classification;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use App\Models\User;
use App\Services\FilelistOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->activeStatus = Status::create([
        'id' => 1,
        'nama_status' => Status::ACTIVE,
    ]);
    $this->inactiveStatus = Status::create([
        'id' => 2,
        'nama_status' => Status::INACTIVE,
    ]);
    $this->classification = Classification::create([
        'kode_klasifikasi' => 'TU.01',
        'keterangan' => 'Tata Usaha',
    ]);
    $this->filelist = createAttachFilelist($this->classification, 'Berkas Tujuan');

    $this->withActiveYear(2026)->actingAs($this->user);
});

test('pending filing filter returns only eligible letters for the selected type and year', function () {
    $eligibleIncoming = createAttachIncoming([
        'tahun' => 2025,
        'nomor_surat' => 'IN-ELIGIBLE',
    ]);
    createAttachIncoming([
        'tahun' => 2026,
        'nomor_surat' => 'IN-OTHER-YEAR',
    ]);
    createAttachIncoming([
        'tahun' => 2026,
        'nomor_surat' => 'IN-LEGACY-WATERMARKED',
        'url_watermarked' => 'dokumen/masuk/legacy-watermarked.pdf',
    ]);
    createAttachIncoming([
        'tahun' => 2025,
        'nomor_surat' => 'IN-FILED',
        'filelist_id' => $this->filelist->id,
    ]);
    createAttachIncoming([
        'tahun' => 2025,
        'nomor_surat' => 'IN-SRIKANDI',
        'is_srikandi' => true,
    ]);
    createAttachIncoming([
        'tahun' => 2025,
        'nomor_surat' => 'IN-WATERMARKED',
        'url_watermarked' => 'dokumen/masuk/watermarked.pdf',
    ]);
    $deletedIncoming = createAttachIncoming([
        'tahun' => 2025,
        'nomor_surat' => 'IN-DELETED',
    ]);
    $deletedIncoming->delete();
    $eligibleOutgoing = createAttachOutcoming([
        'tahun' => 2025,
        'nomor_surat' => 'OUT-ELIGIBLE',
    ]);

    $this->get(route('surat.belum-diberkaskan', [
        'jenis' => 'masuk',
        'tahun' => 2025,
    ]), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $eligibleIncoming->id)
        ->assertJsonPath('data.0.jenis', 'masuk')
        ->assertJsonPath('data.0.nomor_agenda', $eligibleIncoming->nomor_agenda)
        ->assertJsonPath('data.0.tahun', 2025)
        ->assertJsonMissingPath('data.0.url')
        ->assertJsonPath('data.0.preview_url', route('document.admin', [
            'jenis' => 'masuk',
            'id' => $eligibleIncoming->id,
            'versi' => 'tampil',
        ]));

    $this->get(route('surat.belum-diberkaskan', [
        'jenis' => 'keluar',
        'tahun' => 2025,
    ]), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $eligibleOutgoing->id)
        ->assertJsonPath('data.0.jenis', 'keluar')
        ->assertJsonPath('data.0.nomor_agenda', null);

    $this->get(
        route('surat.belum-diberkaskan'),
        ['X-Requested-With' => 'XMLHttpRequest']
    )
        ->assertOk()
        ->assertSee('IN-LEGACY-WATERMARKED');
});

test('incoming and outgoing letters from different years attach in one atomic request', function () {
    $incoming = createAttachIncoming(['tahun' => 2025]);
    $outgoing = createAttachOutcoming();
    Activity::query()->delete();

    $this->post(route('berkas.lampirkanBulk', $this->filelist->id), [
        'items' => [
            'masuk:'.$incoming->id,
            'keluar:'.$outgoing->id,
        ],
    ])->assertRedirect(route('berkas.buka', $this->filelist->id));

    expect($incoming->fresh()->filelist_id)->toBe($this->filelist->id)
        ->and($outgoing->fresh()->filelist_id)->toBe($this->filelist->id);

    $activities = Activity::query()
        ->where('event', 'updated')
        ->whereIn('subject_type', [Incoming::class, Outcoming::class])
        ->get();

    expect($activities)->toHaveCount(2)
        ->and($activities->pluck('subject_id')->map(fn ($id): int => (int) $id)->all())
        ->toEqualCanonicalizing([$incoming->id, $outgoing->id]);
});

test('incoming and outgoing letters can be detached from a filelist with activity logs', function () {
    $incoming = createAttachIncoming(['filelist_id' => $this->filelist->id]);
    $outgoing = createAttachOutcoming(['filelist_id' => $this->filelist->id]);
    Activity::query()->delete();

    $this->from(route('berkas.buka', $this->filelist->id))
        ->post(route('berkas.keluarkan', [
            $this->filelist->id,
            'masuk',
            $incoming->id,
        ]))
        ->assertRedirect(route('berkas.buka', $this->filelist->id));

    expect($incoming->fresh()->filelist_id)->toBeNull()
        ->and($outgoing->fresh()->filelist_id)->toBe($this->filelist->id);

    $this->from(route('berkas.buka', $this->filelist->id))
        ->post(route('berkas.keluarkan', [
            $this->filelist->id,
            'keluar',
            $outgoing->id,
        ]))
        ->assertRedirect(route('berkas.buka', $this->filelist->id));

    expect($outgoing->fresh()->filelist_id)->toBeNull();

    $activities = Activity::query()
        ->where('event', 'updated')
        ->whereIn('subject_type', [Incoming::class, Outcoming::class])
        ->get();

    expect($activities)->toHaveCount(2)
        ->and($activities->pluck('subject_id')->map(fn ($id): int => (int) $id)->all())
        ->toEqualCanonicalizing([$incoming->id, $outgoing->id]);
});

test('filelist contents show detach only for movable letters', function () {
    $movable = createAttachIncoming([
        'nomor_surat' => 'IN-DETACH',
        'filelist_id' => $this->filelist->id,
    ]);
    $locked = createAttachOutcoming([
        'nomor_surat' => 'OUT-LOCKED',
        'filelist_id' => $this->filelist->id,
        'url_watermarked' => 'dokumen/alih-media/locked.pdf',
    ]);

    $this->get(route('berkas.buka', $this->filelist->id))
        ->assertOk()
        ->assertSee('class="m-0 detach-letter-form"', false)
        ->assertSee(route('berkas.keluarkan', [
            $this->filelist->id,
            'masuk',
            $movable->id,
        ]), false)
        ->assertDontSee(route('berkas.keluarkan', [
            $this->filelist->id,
            'keluar',
            $locked->id,
        ]), false);
});

test('attaching is atomic when one selected letter is no longer eligible', function () {
    $otherFilelist = createAttachFilelist($this->classification, 'Berkas Lain');
    $eligible = createAttachIncoming([
        'nomor_surat' => 'IN-VALID',
        'tahun' => 2025,
    ]);
    $alreadyFiled = createAttachOutcoming([
        'nomor_surat' => 'OUT-CHANGED',
        'filelist_id' => $otherFilelist->id,
    ]);

    $this->post(route('berkas.lampirkanBulk', $this->filelist->id), [
        'items' => [
            'masuk:'.$eligible->id,
            'keluar:'.$alreadyFiled->id,
        ],
    ])->assertRedirect(route('berkas.buka', $this->filelist->id));

    expect($eligible->fresh()->filelist_id)->toBeNull()
        ->and($alreadyFiled->fresh()->filelist_id)->toBe($otherFilelist->id);
});

test('duplicate and malformed selection keys are rejected before attaching', function () {
    $incoming = createAttachIncoming();
    $selectionKey = 'masuk:'.$incoming->id;

    $this->from(route('berkas.buka', $this->filelist->id))
        ->post(route('berkas.lampirkanBulk', $this->filelist->id), [
            'items' => [$selectionKey, $selectionKey],
        ])
        ->assertRedirect(route('berkas.buka', $this->filelist->id))
        ->assertSessionHasErrors('items.0');

    $this->from(route('berkas.buka', $this->filelist->id))
        ->post(route('berkas.lampirkanBulk', $this->filelist->id), [
            'items' => ['digital:'.$incoming->id],
        ])
        ->assertRedirect(route('berkas.buka', $this->filelist->id))
        ->assertSessionHasErrors('items.0');

    expect($incoming->fresh()->filelist_id)->toBeNull();

    $service = app(FilelistOperationService::class);
    $result = $service->attachLetters($this->filelist->id, ['invalid-without-colon', 'masuk:not-a-number']);
    expect($result)->toBe(['status' => 'letter_invalid']);
});

test('letters outside the configured year range reject the whole selection', function () {
    $outsideRange = createAttachIncoming(['tahun' => 2024]);
    $eligible = createAttachOutcoming();

    $this->post(route('berkas.lampirkanBulk', $this->filelist->id), [
        'items' => [
            'masuk:'.$outsideRange->id,
            'keluar:'.$eligible->id,
        ],
    ])->assertRedirect(route('berkas.buka', $this->filelist->id));

    expect($outsideRange->fresh()->filelist_id)->toBeNull()
        ->and($eligible->fresh()->filelist_id)->toBeNull();
});

test('inactive deleted and alih media filelists reject attachments without partial changes', function () {
    $alihMediaStatus = AlihMediaStatus::create(['nama_status' => 'Diproses']);
    $inactiveFilelist = createAttachFilelist($this->classification, 'Berkas Inaktif', [
        'status_id' => $this->inactiveStatus->id,
    ]);
    $lockedFilelist = createAttachFilelist($this->classification, 'Berkas Alih Media', [
        'alih_media_status_id' => $alihMediaStatus->id,
    ]);
    $deletedFilelist = createAttachFilelist($this->classification, 'Berkas Terhapus');
    $deletedFilelist->delete();
    $targets = [$inactiveFilelist, $lockedFilelist, $deletedFilelist];

    foreach ($targets as $index => $target) {
        $incoming = createAttachIncoming(['nomor_surat' => 'IN-TARGET-'.$index]);

        $this->post(route('berkas.lampirkanBulk', $target->id), [
            'items' => ['masuk:'.$incoming->id],
        ])->assertRedirect(route('berkas.buka', $target->id));

        expect($incoming->fresh()->filelist_id)->toBeNull();
    }

    $this->get(route('berkas.buka', $inactiveFilelist->id))
        ->assertOk()
        ->assertDontSee('id="btnOpenAttachModal"', false);
    $this->get(route('berkas.buka', $lockedFilelist->id))
        ->assertOk()
        ->assertDontSee('id="btnOpenAttachModal"', false);
});

test('open filelist page renders the attach modal route and minified asset', function () {
    expect(Route::has('berkas.lampirkanBulk'))->toBeTrue();

    $attachScript = file_get_contents(resource_path('js/berkas-buka.js'));
    expect($attachScript)
        ->toContain("data: 'preview_url'")
        ->toContain('href: String(data)')
        ->toContain("target: '_blank'")
        ->toContain("rel: 'noopener noreferrer'")
        ->toContain("'.detach-letter-form'")
        ->toContain('Keluarkan surat dari berkas?')
        ->not->toContain('attachPdfPreviewFrame');

    $this->get(route('berkas.buka', $this->filelist->id))
        ->assertOk()
        ->assertSee('Lampirkan Surat')
        ->assertSee('Surat Dipilih')
        ->assertSee('id="modalLampirkanSurat"', false)
        ->assertSee('id="attachSelectedTableBody"', false)
        ->assertSee('<th class="text-center">Aksi</th>', false)
        ->assertDontSee('id="attachPdfPreviewFrame"', false)
        ->assertSee(route('berkas.lampirkanBulk', $this->filelist->id), false)
        ->assertSee(asset('js/berkas-buka.min.js'), false);
});

test('pending filing page offers direct filing into an active filelist', function () {
    $incoming = createAttachIncoming(['nomor_surat' => 'IN-DIRECT-FILING']);
    $sentinelFilelistId = 987654321;

    $directFilingScript = file_get_contents(resource_path('js/direct-filing.js'));
    expect($directFilingScript)
        ->toContain("'.open-direct-filing-modal'")
        ->toContain('$filelist.select2')
        ->toContain("data('active-filelists-url')")
        ->toContain('Berkaskan surat ini?')
        ->toContain('HTMLFormElement.prototype.submit.call');

    $this->get(route('surat.belum-diberkaskan'))
        ->assertOk()
        ->assertSee('id="directFilingModal"', false)
        ->assertSee('id="directFilingFilelist"', false)
        ->assertSee('name="items[]"', false)
        ->assertSee(route('berkas.aktif.list'), false)
        ->assertSee(route('berkas.lampirkanBulk', $sentinelFilelistId), false)
        ->assertSee(asset('js/direct-filing.min.js'), false)
        ->assertSee(asset('js/belum-diberkaskan.min.js'), false);

    $this->get(route('surat.belum-diberkaskan'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.id', $incoming->id)
        ->assertJsonPath('data.0.aksi', fn (string $action): bool => str_contains($action, 'open-direct-filing-modal')
            && str_contains($action, 'fa-folder-open')
            && str_contains($action, 'data-letter-key=\'masuk:'.$incoming->id.'\'')
            && ! str_contains($action, 'fa-folder-plus')
        );

    $this->post(route('berkas.lampirkanBulk', $this->filelist->id), [
        'items' => ['masuk:'.$incoming->id],
    ])->assertRedirect(route('berkas.buka', $this->filelist->id));

    expect($incoming->fresh()->filelist_id)->toBe($this->filelist->id);
});

test('incoming and outgoing lists offer direct filing only for eligible letters', function () {
    $eligibleIncoming = createAttachIncoming(['nomor_surat' => 'IN-LIST-ELIGIBLE']);
    $filedIncoming = createAttachIncoming([
        'nomor_surat' => 'IN-LIST-FILED',
        'filelist_id' => $this->filelist->id,
    ]);
    $srikandiIncoming = createAttachIncoming([
        'nomor_surat' => 'IN-LIST-SRIKANDI',
        'is_srikandi' => true,
    ]);
    $eligibleOutgoing = createAttachOutcoming(['nomor_surat' => 'OUT-LIST-ELIGIBLE']);
    $lockedOutgoing = createAttachOutcoming([
        'nomor_surat' => 'OUT-LIST-LOCKED',
        'url_watermarked' => 'dokumen/keluar/locked.pdf',
    ]);

    $incomingResponse = $this->get(
        route('surat.masuk'),
        ['X-Requested-With' => 'XMLHttpRequest']
    )->assertOk();
    $incomingRows = collect($incomingResponse->json('data'));

    expect($incomingRows->firstWhere('id', $eligibleIncoming->id)['aksi'])
        ->toContain('open-direct-filing-modal')
        ->toContain('fa-folder-open')
        ->toContain("data-letter-key='masuk:{$eligibleIncoming->id}'")
        ->and($incomingRows->firstWhere('id', $filedIncoming->id)['aksi'])
        ->not->toContain('open-direct-filing-modal')
        ->and($incomingRows->firstWhere('id', $srikandiIncoming->id)['aksi'])
        ->not->toContain('open-direct-filing-modal');

    $outgoingResponse = $this->get(
        route('surat.keluar'),
        ['X-Requested-With' => 'XMLHttpRequest']
    )->assertOk();
    $outgoingRows = collect($outgoingResponse->json('data'));

    expect($outgoingRows->firstWhere('id', $eligibleOutgoing->id)['aksi'])
        ->toContain('open-direct-filing-modal')
        ->toContain('fa-folder-open')
        ->toContain("data-letter-key='keluar:{$eligibleOutgoing->id}'")
        ->and($outgoingRows->firstWhere('id', $lockedOutgoing->id)['aksi'])
        ->not->toContain('open-direct-filing-modal');

    foreach (['surat.masuk', 'surat.keluar'] as $routeName) {
        $this->get(route($routeName))
            ->assertOk()
            ->assertSee('id="directFilingModal"', false)
            ->assertSee(asset('js/direct-filing.min.js'), false)
            ->assertSee(route('berkas.aktif.list'), false);
    }
});

test('direct filing choices contain only active unlocked filelists', function () {
    $alihMediaStatus = AlihMediaStatus::create(['nama_status' => 'Diproses']);
    $inactiveFilelist = createAttachFilelist($this->classification, 'Berkas Inaktif Pilihan', [
        'status_id' => $this->inactiveStatus->id,
    ]);
    $lockedFilelist = createAttachFilelist($this->classification, 'Berkas Terkunci Pilihan', [
        'alih_media_status_id' => $alihMediaStatus->id,
    ]);
    $deletedFilelist = createAttachFilelist($this->classification, 'Berkas Terhapus Pilihan');
    $deletedFilelist->delete();

    $this->getJson(route('berkas.aktif.list'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->filelist->id)
        ->assertJsonPath('data.0.kode_klasifikasi', $this->classification->kode_klasifikasi)
        ->assertJsonPath('data.0.nama_berkas', $this->filelist->nama_berkas)
        ->assertJsonMissing(['id' => $inactiveFilelist->id])
        ->assertJsonMissing(['id' => $lockedFilelist->id])
        ->assertJsonMissing(['id' => $deletedFilelist->id]);
});

function createAttachFilelist(
    Classification $classification,
    string $name,
    array $attributes = []
): Filelist {
    return Filelist::create(array_merge([
        'classification_id' => $classification->id,
        'nama_berkas' => $name,
        'status_id' => 1,
        'retensi_aktif' => 1,
        'retensi_inaktif' => 1,
        'keterangan_akhir' => 'Permanen',
    ], $attributes));
}

function createAttachIncoming(array $attributes = []): Incoming
{
    return Incoming::create(array_merge([
        'nomor_agenda' => fake()->numberBetween(1, 99999),
        'tanggal_diterima' => '2026-08-10',
        'nomor_surat' => fake()->unique()->bothify('IN-####'),
        'pengirim' => 'Pengirim',
        'tanggal_surat' => '2026-08-09',
        'perihal' => 'Surat Masuk',
        'url' => 'dokumen/masuk/surat.pdf',
        'tahun' => 2026,
        'is_srikandi' => false,
    ], $attributes));
}

function createAttachOutcoming(array $attributes = []): Outcoming
{
    return Outcoming::create(array_merge([
        'tanggal_surat' => '2026-08-09',
        'nomor_surat' => fake()->unique()->bothify('OUT-####'),
        'tujuan' => 'Tujuan',
        'perihal' => 'Surat Keluar',
        'url' => 'dokumen/keluar/surat.pdf',
        'tahun' => 2026,
        'is_digital' => false,
        'is_srikandi' => false,
    ], $attributes));
}
