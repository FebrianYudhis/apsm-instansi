@extends('layouts.main')

@section('konten')
    <div class="mt-4">
        <div class="page-header mb-4 border-bottom pb-3">
            <h2 class="pageheader-title">Beranda (Dashboard)</h2>
            <span class="text-muted">Ringkasan statistik dan akses cepat pengelolaan surat Anda.</span>
        </div>

        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Surat Masuk</h6>
                                <h2 class="mb-0 text-primary">{{ $suratMasuk }}</h2>
                            </div>
                            <i class="fa fa-inbox fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Surat Keluar</h6>
                                <h2 class="mb-0 text-success">{{ $suratKeluar }}</h2>
                            </div>
                            <i class="fa fa-paper-plane fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Berkas Aktif</h6>
                                <h2 class="mb-0 text-info">{{ $berkasAktif }}</h2>
                            </div>
                            <i class="fa fa-archive fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Berkas Inaktif</h6>
                                <h2 class="mb-0 text-secondary">{{ $berkasInaktif }}</h2>
                            </div>
                            <i class="fa fa-box fa-2x text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Status Alih Media</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 col-6 mb-3 mb-md-0">
                                <h3 class="mb-1">{{ $alihMediaMenunggu }}</h3>
                                <span class="text-muted">Menunggu</span>
                            </div>
                            <div class="col-md-3 col-6 mb-3 mb-md-0">
                                <h3 class="mb-1">{{ $alihMediaDiproses }}</h3>
                                <span class="text-muted">Diproses</span>
                            </div>
                            <div class="col-md-3 col-6">
                                <h3 class="mb-1 text-danger">{{ $alihMediaGagal }}</h3>
                                <span class="text-muted">Gagal</span>
                            </div>
                            <div class="col-md-3 col-6">
                                <h3 class="mb-1 text-success">{{ $alihMediaSelesai }}</h3>
                                <span class="text-muted">Selesai</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Perlu Perhatian</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                            <span>
                                Surat tahun {{ $tahun }} belum masuk pemberkasan
                            </span>
                            <strong>{{ $suratBelumBerkas }}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>
                                Berkas tanpa isi surat
                            </span>
                            <strong>{{ $berkasTanpaIsi }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Akses Cepat</h5>
                    </div>
                    <div class="card-body">
                        <a href="{{ route('masuk.tambah') }}" class="btn btn-primary btn-block mb-2">
                            <i class="fa fa-plus mr-1"></i> Surat Masuk
                        </a>
                        <a href="{{ route('keluar.tambah') }}" class="btn btn-success btn-block mb-2">
                            <i class="fa fa-plus mr-1"></i> Surat Keluar
                        </a>
                        <a href="{{ route('berkas.tambah') }}" class="btn btn-info btn-block mb-2">
                            <i class="fa fa-plus mr-1"></i> Berkas
                        </a>
                        <a href="{{ route('alih-media.penyeleksian') }}" class="btn btn-secondary btn-block">
                            <i class="fa fa-file mr-1"></i> Penyeleksian Alih Media
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Retensi Akhir</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Permanen</span>
                            <strong>{{ $berkasPermanen }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Musnah</span>
                            <strong>{{ $berkasMusnah }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Surat Masuk Terbaru</h5>
                    </div>
                    <div class="card-body">
                        @forelse ($suratMasukTerbaru as $surat)
                            <div class="border-bottom pb-2 mb-2">
                                <div class="font-weight-bold">{{ $surat->is_srikandi ? 'SRIKANDI' : ($surat->nomor_agenda ?? '-') }}</div>
                                <div>{{ $surat->perihal }}</div>
                                <small class="text-muted">{{ $surat->tanggal_diterima }} · {{ $surat->pengirim }}</small>
                            </div>
                        @empty
                            <span class="text-muted">Belum ada surat masuk.</span>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Surat Keluar Terbaru</h5>
                    </div>
                    <div class="card-body">
                        @forelse ($suratKeluarTerbaru as $surat)
                            <div class="border-bottom pb-2 mb-2">
                                <div class="font-weight-bold">{{ $surat->nomor_surat }}</div>
                                <div>{{ $surat->perihal }}</div>
                                <small class="text-muted">{{ $surat->tanggal_surat }} · {{ $surat->tujuan }}</small>
                            </div>
                        @empty
                            <span class="text-muted">Belum ada surat keluar.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
