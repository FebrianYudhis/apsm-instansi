@extends('layouts.main')

@section('konten')
    @php
        $totalSurat = $suratMasuk + $suratKeluar;
        $totalAlihMedia = $alihMediaMenunggu + $alihMediaDiproses + $alihMediaGagal + $alihMediaSelesai;
        $totalPerhatian = $suratBelumBerkas + $berkasTanpaIsi + $alihMediaGagal;
        $totalRetensi = $berkasPermanen + $berkasMusnah;
    @endphp

    <main class="home-dashboard mt-4">
        <section class="home-hero" aria-labelledby="dashboard-title">
            <div class="home-hero-content">
                <span class="home-eyebrow">
                    <i class="fa fa-layer-group" aria-hidden="true"></i>
                    Pusat Kendali Arsip
                </span>
                <h1 id="dashboard-title">Ringkasan tahun {{ $tahun }}</h1>
                <p>
                    Pantau pencatatan surat, pemberkasan, dan proses alih media dalam satu tampilan yang ringkas.
                </p>
            </div>
            <div class="home-hero-summary" aria-label="Ringkasan surat tahun aktif">
                <span class="home-summary-label">Total naskah tercatat</span>
                <strong>{{ number_format($totalSurat, 0, ',', '.') }}</strong>
                <span>{{ number_format($suratMasuk, 0, ',', '.') }} masuk <span aria-hidden="true">•</span>
                    {{ number_format($suratKeluar, 0, ',', '.') }} keluar</span>
                <div class="home-year-chip">
                    <i class="fa fa-calendar-alt" aria-hidden="true"></i>
                    Tahun aktif {{ $tahun }}
                </div>
            </div>
        </section>

        <section class="home-section" aria-labelledby="statistik-title">
            <div class="home-section-heading">
                <div>
                    <span class="home-section-kicker">Statistik utama</span>
                    <h2 id="statistik-title">Kondisi arsip saat ini</h2>
                </div>
                <span class="home-updated-label">
                    <i class="fa fa-clock" aria-hidden="true"></i>
                    Diperbarui {{ now()->format('d/m/Y H:i') }}
                </span>
            </div>

            <div class="home-stat-grid">
                <a href="{{ route('surat.masuk') }}" class="home-stat-card home-stat-primary">
                    <span class="home-stat-icon"><i class="fa fa-inbox" aria-hidden="true"></i></span>
                    <span class="home-stat-content">
                        <span class="home-stat-label">Surat Masuk</span>
                        <strong>{{ number_format($suratMasuk, 0, ',', '.') }}</strong>
                        <span>Tahun {{ $tahun }}</span>
                    </span>
                    <i class="fa fa-arrow-right home-stat-arrow" aria-hidden="true"></i>
                </a>

                <a href="{{ route('surat.keluar') }}" class="home-stat-card home-stat-success">
                    <span class="home-stat-icon"><i class="fa fa-paper-plane" aria-hidden="true"></i></span>
                    <span class="home-stat-content">
                        <span class="home-stat-label">Surat Keluar</span>
                        <strong>{{ number_format($suratKeluar, 0, ',', '.') }}</strong>
                        <span>Tahun {{ $tahun }}</span>
                    </span>
                    <i class="fa fa-arrow-right home-stat-arrow" aria-hidden="true"></i>
                </a>

                <a href="{{ route('surat.digital') }}" class="home-stat-card home-stat-info">
                    <span class="home-stat-icon"><i class="fa fa-file-pdf" aria-hidden="true"></i></span>
                    <span class="home-stat-content">
                        <span class="home-stat-label">Surat Digital</span>
                        <strong>{{ number_format($suratDigital, 0, ',', '.') }}</strong>
                        <span>Dokumen tersimpan</span>
                    </span>
                    <i class="fa fa-arrow-right home-stat-arrow" aria-hidden="true"></i>
                </a>

                <a href="{{ route('surat.berkas') }}" class="home-stat-card home-stat-warning">
                    <span class="home-stat-icon"><i class="fa fa-archive" aria-hidden="true"></i></span>
                    <span class="home-stat-content">
                        <span class="home-stat-label">Berkas Aktif</span>
                        <strong>{{ number_format($berkasAktif, 0, ',', '.') }}</strong>
                        <span>Siap dikelola</span>
                    </span>
                    <i class="fa fa-arrow-right home-stat-arrow" aria-hidden="true"></i>
                </a>

                <a href="{{ route('surat.berkas') }}" class="home-stat-card home-stat-muted">
                    <span class="home-stat-icon"><i class="fa fa-box" aria-hidden="true"></i></span>
                    <span class="home-stat-content">
                        <span class="home-stat-label">Berkas Inaktif</span>
                        <strong>{{ number_format($berkasInaktif, 0, ',', '.') }}</strong>
                        <span>Masa simpan selesai</span>
                    </span>
                    <i class="fa fa-arrow-right home-stat-arrow" aria-hidden="true"></i>
                </a>
            </div>
        </section>

        <div class="home-main-grid">
            <div class="home-main-column">
                <section class="home-panel home-attention-panel" aria-labelledby="perhatian-title">
                    <div class="home-panel-header">
                        <div>
                            <span class="home-section-kicker">Prioritas kerja</span>
                            <h2 id="perhatian-title">Perlu perhatian</h2>
                        </div>
                        <span class="home-count-badge {{ $totalPerhatian > 0 ? 'is-warning' : 'is-clear' }}">
                            {{ number_format($totalPerhatian, 0, ',', '.') }} temuan
                        </span>
                    </div>

                    <div class="home-attention-grid">
                        <a href="{{ route('surat.berkas') }}" class="home-attention-item">
                            <span class="home-attention-icon is-warning">
                                <i class="fa fa-folder-open" aria-hidden="true"></i>
                            </span>
                            <span>
                                <strong>{{ number_format($suratBelumBerkas, 0, ',', '.') }}</strong>
                                <span>Surat {{ $tahun }} belum diberkaskan</span>
                            </span>
                            <i class="fa fa-chevron-right" aria-hidden="true"></i>
                        </a>

                        <a href="{{ route('surat.berkas') }}" class="home-attention-item">
                            <span class="home-attention-icon is-info">
                                <i class="fa fa-folder" aria-hidden="true"></i>
                            </span>
                            <span>
                                <strong>{{ number_format($berkasTanpaIsi, 0, ',', '.') }}</strong>
                                <span>Berkas belum memiliki isi</span>
                            </span>
                            <i class="fa fa-chevron-right" aria-hidden="true"></i>
                        </a>

                        <a href="{{ route('alih-media.diproses') }}" class="home-attention-item">
                            <span class="home-attention-icon {{ $alihMediaGagal > 0 ? 'is-danger' : 'is-success' }}">
                                <i class="fa {{ $alihMediaGagal > 0 ? 'fa-exclamation-triangle' : 'fa-check' }}"
                                    aria-hidden="true"></i>
                            </span>
                            <span>
                                <strong>{{ number_format($alihMediaGagal, 0, ',', '.') }}</strong>
                                <span>Proses alih media gagal</span>
                            </span>
                            <i class="fa fa-chevron-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </section>

                <section class="home-panel" aria-labelledby="alih-media-title">
                    <div class="home-panel-header">
                        <div>
                            <span class="home-section-kicker">Alur digitalisasi</span>
                            <h2 id="alih-media-title">Status alih media</h2>
                        </div>
                        <span class="home-panel-total">{{ number_format($totalAlihMedia, 0, ',', '.') }} berkas</span>
                    </div>

                    <div class="home-workflow" role="list" aria-label="Tahapan alih media">
                        <a href="{{ route('alih-media.penyeleksian') }}" class="home-workflow-item is-waiting"
                            role="listitem">
                            <span class="home-workflow-marker"><i class="fa fa-hourglass-half" aria-hidden="true"></i></span>
                            <span class="home-workflow-value">{{ number_format($alihMediaMenunggu, 0, ',', '.') }}</span>
                            <span class="home-workflow-label">Menunggu</span>
                        </a>
                        <a href="{{ route('alih-media.diproses') }}" class="home-workflow-item is-processing"
                            role="listitem">
                            <span class="home-workflow-marker"><i class="fa fa-sync-alt" aria-hidden="true"></i></span>
                            <span class="home-workflow-value">{{ number_format($alihMediaDiproses, 0, ',', '.') }}</span>
                            <span class="home-workflow-label">Diproses</span>
                        </a>
                        <a href="{{ route('alih-media.diproses') }}" class="home-workflow-item is-failed"
                            role="listitem">
                            <span class="home-workflow-marker"><i class="fa fa-times" aria-hidden="true"></i></span>
                            <span class="home-workflow-value">{{ number_format($alihMediaGagal, 0, ',', '.') }}</span>
                            <span class="home-workflow-label">Gagal</span>
                        </a>
                        <a href="{{ route('alih-media.selesai') }}" class="home-workflow-item is-complete"
                            role="listitem">
                            <span class="home-workflow-marker"><i class="fa fa-check" aria-hidden="true"></i></span>
                            <span class="home-workflow-value">{{ number_format($alihMediaSelesai, 0, ',', '.') }}</span>
                            <span class="home-workflow-label">Selesai</span>
                        </a>
                    </div>
                </section>
            </div>

            <aside class="home-side-column" aria-label="Akses cepat dan retensi">
                <section class="home-panel" aria-labelledby="akses-cepat-title">
                    <div class="home-panel-header">
                        <div>
                            <span class="home-section-kicker">Pintasan</span>
                            <h2 id="akses-cepat-title">Akses cepat</h2>
                        </div>
                    </div>
                    <div class="home-quick-grid">
                        <a href="{{ route('masuk.tambah') }}" class="home-quick-action">
                            <span class="is-primary"><i class="fa fa-inbox" aria-hidden="true"></i></span>
                            <strong>Surat Masuk</strong>
                            <small>Catat naskah baru</small>
                        </a>
                        <a href="{{ route('keluar.tambah') }}" class="home-quick-action">
                            <span class="is-success"><i class="fa fa-paper-plane" aria-hidden="true"></i></span>
                            <strong>Surat Keluar</strong>
                            <small>Tambah surat keluar</small>
                        </a>
                        <a href="{{ route('berkas.tambah') }}" class="home-quick-action">
                            <span class="is-info"><i class="fa fa-archive" aria-hidden="true"></i></span>
                            <strong>Berkas Baru</strong>
                            <small>Siapkan pemberkasan</small>
                        </a>
                        <a href="{{ route('alih-media.penyeleksian') }}" class="home-quick-action">
                            <span class="is-warning"><i class="fa fa-file" aria-hidden="true"></i></span>
                            <strong>Alih Media</strong>
                            <small>Mulai penyeleksian</small>
                        </a>
                    </div>
                </section>

                <section class="home-panel home-retention-panel" aria-labelledby="retensi-title">
                    <div class="home-panel-header">
                        <div>
                            <span class="home-section-kicker">Penyusutan</span>
                            <h2 id="retensi-title">Retensi akhir</h2>
                        </div>
                        <span class="home-panel-total">{{ number_format($totalRetensi, 0, ',', '.') }} berkas</span>
                    </div>
                    <div class="home-retention-grid">
                        <div>
                            <span class="home-retention-dot is-permanent"></span>
                            <span>Permanen</span>
                            <strong>{{ number_format($berkasPermanen, 0, ',', '.') }}</strong>
                        </div>
                        <div>
                            <span class="home-retention-dot is-destroyed"></span>
                            <span>Musnah</span>
                            <strong>{{ number_format($berkasMusnah, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <section class="home-section home-recent-section" aria-labelledby="terbaru-title">
            <div class="home-section-heading">
                <div>
                    <span class="home-section-kicker">Aktivitas terbaru</span>
                    <h2 id="terbaru-title">Naskah yang baru dicatat</h2>
                </div>
            </div>

            <div class="home-recent-grid">
                <section class="home-panel" aria-labelledby="surat-masuk-terbaru-title">
                    <div class="home-panel-header">
                        <div class="home-panel-title-with-icon">
                            <span class="is-primary"><i class="fa fa-inbox" aria-hidden="true"></i></span>
                            <div>
                                <h3 id="surat-masuk-terbaru-title">Surat Masuk Terbaru</h3>
                                <small>Lima pencatatan terakhir</small>
                            </div>
                        </div>
                        <a href="{{ route('surat.masuk') }}" class="home-text-link">Lihat semua</a>
                    </div>

                    <div class="home-document-list">
                        @forelse ($suratMasukTerbaru as $surat)
                            <a href="{{ route('surat.detailItem', ['masuk', $surat->id]) }}" class="home-document-item">
                                <span class="home-document-number">
                                    {{ $surat->is_srikandi ? 'SRIKANDI' : ($surat->nomor_agenda ?? '-') }}
                                </span>
                                <span class="home-document-content">
                                    <strong>{{ $surat->perihal }}</strong>
                                    <span>
                                        <time datetime="{{ $surat->tanggal_diterima }}">
                                            {{ date('d/m/Y', strtotime($surat->tanggal_diterima)) }}
                                        </time>
                                        <span aria-hidden="true">•</span>
                                        {{ $surat->pengirim }}
                                    </span>
                                </span>
                                <i class="fa fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        @empty
                            <div class="home-empty-state">
                                <i class="fa fa-inbox" aria-hidden="true"></i>
                                <strong>Belum ada surat masuk</strong>
                                <span>Surat yang baru dicatat akan tampil di sini.</span>
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="home-panel" aria-labelledby="surat-keluar-terbaru-title">
                    <div class="home-panel-header">
                        <div class="home-panel-title-with-icon">
                            <span class="is-success"><i class="fa fa-paper-plane" aria-hidden="true"></i></span>
                            <div>
                                <h3 id="surat-keluar-terbaru-title">Surat Keluar Terbaru</h3>
                                <small>Lima pencatatan terakhir</small>
                            </div>
                        </div>
                        <a href="{{ route('surat.keluar') }}" class="home-text-link">Lihat semua</a>
                    </div>

                    <div class="home-document-list">
                        @forelse ($suratKeluarTerbaru as $surat)
                            <a href="{{ route('surat.detailItem', ['keluar', $surat->id]) }}" class="home-document-item">
                                <span class="home-document-number">{{ $surat->nomor_surat }}</span>
                                <span class="home-document-content">
                                    <strong>{{ $surat->perihal }}</strong>
                                    <span>
                                        <time datetime="{{ $surat->tanggal_surat }}">
                                            {{ date('d/m/Y', strtotime($surat->tanggal_surat)) }}
                                        </time>
                                        <span aria-hidden="true">•</span>
                                        {{ $surat->tujuan }}
                                    </span>
                                </span>
                                <i class="fa fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        @empty
                            <div class="home-empty-state">
                                <i class="fa fa-paper-plane" aria-hidden="true"></i>
                                <strong>Belum ada surat keluar</strong>
                                <span>Surat yang baru dicatat akan tampil di sini.</span>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        </section>
    </main>
@endsection
