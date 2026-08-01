<?php

test('route berkas menolak parameter id yang bukan angka', function (string $method, string $uri) {
    $response = $method === 'GET'
        ? $this->get($uri)
        : $this->post($uri);

    $response->assertNotFound();
})->with([
    'id buka berkas' => ['GET', '/surat/berkas/buka/abc'],
    'id berkas saat mengeluarkan surat' => ['POST', '/surat/berkas/keluarkan/abc/masuk/1'],
    'id surat saat mengeluarkan surat' => ['POST', '/surat/berkas/keluarkan/1/masuk/abc'],
    'id berkas saat memindahkan status' => ['POST', '/surat/berkas/pindah/abc/1'],
]);
