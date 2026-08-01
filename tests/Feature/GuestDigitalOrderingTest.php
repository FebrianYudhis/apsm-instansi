<?php

use App\Models\Digital;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('daftar dan pencarian surat digital publik diurutkan berdasarkan perihal', function () {
    foreach ([
        'Arsip Zulu',
        'Arsip Alfa',
        'Arsip Mike',
    ] as $perihal) {
        Digital::create([
            'perihal' => $perihal,
            'url' => 'dokumen/digital/'.str($perihal)->slug().'.pdf',
        ]);
    }

    $expectedOrder = [
        'Arsip Alfa',
        'Arsip Mike',
        'Arsip Zulu',
    ];

    $this->get(route('guest.digital'))
        ->assertOk()
        ->assertSeeInOrder($expectedOrder);

    $searchResponse = $this->get(route('guest.digital', [
        'pencarian' => 'Arsip',
    ]), [
        'X-Requested-With' => 'XMLHttpRequest',
    ])->assertOk();

    expect(array_column($searchResponse->json('data'), 'perihal'))
        ->toBe($expectedOrder);
});
