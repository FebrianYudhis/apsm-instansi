@extends('layouts.main')

@push('css')
    <style>
        .detail-header {
            background: linear-gradient(135deg, var(--apsm-primary-dark) 0%, var(--apsm-sidebar) 100%);
            color: #ffffff !important;
            border-radius: 8px 8px 0 0;
            padding: 24px 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .detail-header * {
            color: inherit;
        }

        .detail-header .badge-jenis {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff !important;
            font-size: 13px;
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 600;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .detail-header h5 {
            margin: 12px 0 4px 0;
            font-weight: 800;
            font-size: 22px;
            color: #ffffff !important;
            line-height: 1.3;
        }

        .detail-header .nomor-surat {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
        }

        .detail-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .detail-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            padding: 8px 16px;
            font-weight: 600;
            color: var(--apsm-text) !important; 
        }

        .detail-actions .btn-outline-light {
            color: #ffffff !important;
        }
        
        .detail-actions .btn-outline-light:hover {
            color: var(--apsm-text) !important;
        }

        .detail-section {
            padding: 20px 24px;
        }

        .detail-section-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--apsm-muted);
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--apsm-border);
        }

        .detail-section-title i {
            margin-right: 6px;
            opacity: 0.7;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .detail-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f4f8;
        }

        .detail-item:last-child,
        .detail-item:nth-last-child(2):nth-child(odd) {
            border-bottom: none;
        }

        .detail-item .detail-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--apsm-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 3px;
        }

        .detail-item .detail-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--apsm-text);
            word-break: break-word;
        }

        .detail-item.full-width {
            grid-column: 1 / -1;
        }

        .detail-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .detail-badge-masuk {
            background: rgba(24, 123, 143, 0.1);
            color: var(--apsm-info);
        }

        .detail-badge-keluar {
            background: rgba(183, 121, 31, 0.1);
            color: var(--apsm-warning);
        }

        .detail-badge-digital {
            background: rgba(33, 134, 91, 0.1);
            color: var(--apsm-success);
        }

        .detail-badge-fisik {
            background: rgba(107, 123, 138, 0.1);
            color: var(--apsm-muted);
        }

        .pdf-path-text {
            font-size: 12px;
            font-family: monospace;
            background: #f5f7fb;
            padding: 4px 8px;
            border-radius: 4px;
            color: var(--apsm-muted);
        }

        @media (max-width: 767.98px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }

            .detail-header {
                padding: 16px 18px;
            }

            .detail-section {
                padding: 16px 18px;
            }
        }
    </style>
@endpush

@section('konten')
    @php
        $isMasuk = $jenis === 'masuk';
        $title = $isMasuk ? 'Surat Masuk' : 'Surat Keluar';
        $isAlihMediaLocked = $surat->isAlihMediaLocked();
    @endphp

    <div class="row mt-3">
        <div class="col-lg-10 col-xl-8 mx-auto">
            <div class="card border-0" style="overflow: hidden;">

                {{-- Header --}}
                <div class="detail-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: 10px;">
                        <div>
                            <div class="mb-2">
                                <span class="badge-jenis">
                                    <i class="fa {{ $isMasuk ? 'fa-inbox' : 'fa-paper-plane' }}"></i>
                                    {{ $title }}
                                </span>
                            </div>
                            <h5>{{ $surat->perihal ?? '-' }}</h5>
                            <div class="nomor-surat">
                                <i class="fa fa-hashtag" style="font-size: 11px;"></i>
                                {{ $surat->nomor_surat ?? '-' }}
                            </div>
                        </div>
                        <div class="detail-actions">
                            @if ($isAlihMediaLocked)
                                <button type="button" class="btn btn-sm btn-secondary" title="Terkunci karena alih media" disabled>
                                    <i class="fa fa-lock"></i> Terkunci
                                </button>
                            @elseif ($requiresYearSwitch)
                                <form action="{{ route('pindah-tahun', $surat->tahun) }}" method="POST" class="mb-0">
                                    @csrf
                                    <input type="hidden" name="redirect_to" value="{{ $editPath }}">
                                    <button type="submit" class="btn btn-sm btn-light" title="Edit">
                                        <i class="fa fa-edit"></i> Edit
                                    </button>
                                </form>
                            @else
                                <a href="{{ $editUrl }}" class="btn btn-sm btn-light" title="Edit">
                                    <i class="fa fa-edit"></i> Edit
                                </a>
                            @endif
                            <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-light" title="Kembali">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Alert tahun berbeda --}}
                @if (!$isAlihMediaLocked && $surat->tahun != auth()->user()->tahun)
                    <div class="alert alert-info mb-0" style="border-radius: 0; border-left: 0; border-right: 0;">
                        <i class="fa fa-info-circle mr-1"></i>
                        Tahun aktif saat ini <strong>{{ auth()->user()->tahun }}</strong>. Tombol edit akan memindahkan tahun ke <strong>{{ $surat->tahun }}</strong> terlebih dahulu.
                    </div>
                @endif

                {{-- Section: Informasi Umum --}}
                <div class="detail-section">
                    <div class="detail-section-title">
                        <i class="fa fa-info-circle"></i> Informasi Umum
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Jenis Surat</div>
                            <div class="detail-value">
                                <span class="detail-badge {{ $isMasuk ? 'detail-badge-masuk' : 'detail-badge-keluar' }}">
                                    <i class="fa {{ $isMasuk ? 'fa-inbox' : 'fa-paper-plane' }}"></i>
                                    {{ $title }}
                                </span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tahun</div>
                            <div class="detail-value">{{ $surat->tahun ?? '-' }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Nomor Surat</div>
                            <div class="detail-value">{{ $surat->nomor_surat ?? '-' }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tanggal Surat</div>
                            <div class="detail-value">{{ $surat->tanggal_surat ?? '-' }}</div>
                        </div>
                        <div class="detail-item full-width">
                            <div class="detail-label">Perihal</div>
                            <div class="detail-value">{{ $surat->perihal ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                {{-- Section: Detail Spesifik --}}
                <div class="detail-section" style="border-top: 1px solid var(--apsm-border);">
                    <div class="detail-section-title">
                        <i class="fa fa-file-alt"></i>
                        {{ $isMasuk ? 'Detail Surat Masuk' : 'Detail Surat Keluar' }}
                    </div>
                    <div class="detail-grid">
                        @if ($isMasuk)
                            <div class="detail-item">
                                <div class="detail-label">Nomor Agenda</div>
                                <div class="detail-value">{{ $surat->is_srikandi ? 'SRIKANDI' : ($surat->nomor_agenda ?? '-') }}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Tanggal Diterima</div>
                                <div class="detail-value">{{ $surat->tanggal_diterima ?? '-' }}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Jalur Penerimaan</div>
                                <div class="detail-value">{{ $surat->is_srikandi ? 'SRIKANDI' : 'Manual' }}</div>
                            </div>
                            <div class="detail-item full-width">
                                <div class="detail-label">Pengirim</div>
                                <div class="detail-value">{{ $surat->pengirim ?? '-' }}</div>
                            </div>
                        @else
                            <div class="detail-item">
                                <div class="detail-label">Tujuan</div>
                                <div class="detail-value">{{ $surat->tujuan ?? '-' }}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Jenis Naskah</div>
                                <div class="detail-value">
                                    <span class="detail-badge {{ $surat->is_digital ? 'detail-badge-digital' : 'detail-badge-fisik' }}">
                                        <i class="fa {{ $surat->is_digital ? 'fa-laptop' : 'fa-print' }}"></i>
                                        {{ $surat->is_digital ? 'Digital' : 'Manual' }}
                                    </span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Jalur Pengiriman</div>
                                <div class="detail-value">{{ $surat->is_srikandi ? 'SRIKANDI' : 'Manual' }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Section: Kearsipan --}}
                <div class="detail-section" style="border-top: 1px solid var(--apsm-border);">
                    <div class="detail-section-title">
                        <i class="fa fa-archive"></i> Kearsipan
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">SKKAD</div>
                            <div class="detail-value">{{ optional($surat->access)->sifat_akses ?? '-' }}</div>
                        </div>
                        <div class="detail-item full-width">
                            <div class="detail-label">Pemberkasan</div>
                            <div class="detail-value">
                                @if ($surat->is_srikandi)
                                    <span style="color: var(--apsm-muted);">Tidak berlaku untuk SRIKANDI</span>
                                @elseif (optional($surat->filelist)->nama_berkas)
                                    {{ optional(optional($surat->filelist)->classification)->kode_klasifikasi ?? '-' }}
                                    &mdash;
                                    {{ optional($surat->filelist)->nama_berkas }}
                                @else
                                    <span style="color: var(--apsm-muted);">Belum ditempatkan</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section: Dokumen PDF --}}
                <div class="detail-section" style="border-top: 1px solid var(--apsm-border);">
                    <div class="detail-section-title">
                        <i class="fa fa-file-pdf"></i> Dokumen PDF
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Dokumen Asli</div>
                            <div class="detail-value">
                                @if ($surat->url)
                                    <div class="d-flex align-items-center">
                                        <span class="pdf-path-text">Tersimpan pada storage private</span>
                                        <a href="{{ route('document.admin', ['jenis' => $jenis, 'id' => $surat->id, 'versi' => 'asli']) }}"
                                            target="_blank" rel="noopener noreferrer"
                                            class="btn btn-sm btn-success ml-2 py-1 px-2" style="font-size: 11px;">
                                            <i class="fa fa-external-link-alt"></i> Buka PDF
                                        </a>
                                    </div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Dokumen Watermark</div>
                            <div class="detail-value">
                                @if ($surat->url_watermarked)
                                    <div class="d-flex align-items-center">
                                        <span class="pdf-path-text">Tersimpan pada storage private</span>
                                        <a href="{{ route('document.admin', ['jenis' => $jenis, 'id' => $surat->id, 'versi' => 'watermark']) }}"
                                            target="_blank" rel="noopener noreferrer"
                                            class="btn btn-sm btn-primary ml-2 py-1 px-2" style="font-size: 11px;">
                                            <i class="fa fa-external-link-alt"></i> Buka PDF Watermark
                                        </a>
                                    </div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
