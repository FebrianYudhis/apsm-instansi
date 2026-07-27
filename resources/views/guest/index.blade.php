@extends('layouts.guest')

@section('konten')
    <section class="guest-hero">
        <div class="container position-relative">
            <span class="guest-eyebrow">Layanan informasi arsip</span>
            <h1>Temukan naskah dinas dengan lebih mudah.</h1>
            <p class="guest-hero-copy">
                Telusuri koleksi surat masuk, surat keluar, dan dokumen digital yang dikelola oleh
                {{ config('app.pencipta_arsip') }}.
            </p>
            <div class="guest-hero-actions">
                <a href="{{ route('guest.masuk') }}" class="guest-btn guest-btn-primary">
                    <i class="fas fa-search" aria-hidden="true"></i> Mulai pencarian
                </a>
                <a href="#koleksi" class="guest-btn guest-btn-light">
                    Lihat koleksi <i class="fas fa-arrow-down" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>

    <section class="guest-stats" aria-label="Ringkasan koleksi">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="guest-stat-card">
                        <div class="guest-stat-head">
                            <span class="guest-stat-icon"><i class="fas fa-inbox" aria-hidden="true"></i></span>
                            <span class="guest-document-type">Masuk</span>
                        </div>
                        <div class="guest-stat-number">{{ number_format($suratMasuk, 0, ',', '.') }}</div>
                        <p class="guest-stat-label">Surat masuk terarsip</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="guest-stat-card">
                        <div class="guest-stat-head">
                            <span class="guest-stat-icon"><i class="fas fa-paper-plane" aria-hidden="true"></i></span>
                            <span class="guest-document-type">Keluar</span>
                        </div>
                        <div class="guest-stat-number">{{ number_format($suratKeluar, 0, ',', '.') }}</div>
                        <p class="guest-stat-label">Surat keluar terarsip</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="guest-stat-card">
                        <div class="guest-stat-head">
                            <span class="guest-stat-icon"><i class="fas fa-file-alt" aria-hidden="true"></i></span>
                            <span class="guest-document-type">Digital</span>
                        </div>
                        <div class="guest-stat-number">{{ number_format($suratDigital, 0, ',', '.') }}</div>
                        <p class="guest-stat-label">Dokumen digital tersedia</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="guest-section" id="koleksi">
        <div class="container">
            <div class="guest-section-heading">
                <h2>Pilih koleksi arsip</h2>
                <p>Setiap koleksi menyediakan pencarian cepat berdasarkan informasi utama pada naskah.</p>
            </div>

            <div class="row">
                <div class="col-lg-4 mb-3">
                    <a href="{{ route('guest.masuk') }}" class="guest-collection-card">
                        <span class="guest-collection-icon"><i class="fas fa-inbox" aria-hidden="true"></i></span>
                        <h3>Surat Masuk</h3>
                        <p>Cari berdasarkan nomor surat, pengirim, atau perihal naskah yang diterima.</p>
                        <span class="guest-card-link">Telusuri surat <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                    </a>
                </div>
                <div class="col-lg-4 mb-3">
                    <a href="{{ route('guest.keluar') }}" class="guest-collection-card">
                        <span class="guest-collection-icon"><i class="fas fa-paper-plane" aria-hidden="true"></i></span>
                        <h3>Surat Keluar</h3>
                        <p>Cari berdasarkan nomor surat, tujuan, atau perihal naskah yang dikirim.</p>
                        <span class="guest-card-link">Telusuri surat <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                    </a>
                </div>
                <div class="col-lg-4 mb-3">
                    <a href="{{ route('guest.digital') }}" class="guest-collection-card">
                        <span class="guest-collection-icon"><i class="fas fa-file-alt" aria-hidden="true"></i></span>
                        <h3>Surat Digital</h3>
                        <p>Temukan dokumen digital berdasarkan perihal dan lokasi penyimpanannya.</p>
                        <span class="guest-card-link">Telusuri dokumen <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                    </a>
                </div>
            </div>

            <div class="guest-security-note">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                <div>
                    <strong>Akses dokumen mengikuti klasifikasi keamanan.</strong>
                    <p>Dokumen dengan akses terbatas memerlukan kode MFA dari petugas yang berwenang.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
