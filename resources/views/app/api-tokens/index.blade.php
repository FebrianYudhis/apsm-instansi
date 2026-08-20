@extends('layouts.main')

@section('konten')
    @if (session('plain_text_token'))
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm p-4 mb-4" role="alert"
            style="border-left: 5px solid #ffc107 !important; background-color: #fffdf5; border-radius: 8px;">
            <button type="button" class="close" data-dismiss="alert" aria-label="Tutup" style="top: 12px; right: 15px;">
                <span aria-hidden="true">&times;</span>
            </button>
            <div class="d-flex align-items-center pr-5 mb-3">
                <span class="mr-3 rounded-circle bg-warning text-white d-inline-flex align-items-center justify-content-center shadow-sm flex-shrink-0"
                    style="width: 40px; height: 40px; min-width: 40px; font-size: 1.1rem;">
                    <i class="fa fa-key"></i>
                </span>
                <div>
                    <div class="d-flex flex-wrap align-items-center">
                        <h4 class="alert-heading mb-0 mr-2 font-weight-bold text-dark" style="font-size: 1.15rem;">
                            Personal Access Token Berhasil Dibuat
                        </h4>
                        <span class="badge badge-warning py-1 px-2 font-weight-bold text-uppercase mt-1 mt-sm-0" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            <i class="fa fa-exclamation-triangle mr-1"></i>Hanya Ditampilkan Sekali
                        </span>
                    </div>
                    <span class="text-muted small">Salin dan amankan token Anda sekarang</span>
                </div>
            </div>

            <p class="text-secondary mb-3" style="font-size: 0.92rem; line-height: 1.5;">
                Demi keamanan akun, token ini <strong>tidak akan pernah ditampilkan lagi</strong> setelah Anda meninggalkan atau memuat ulang halaman ini. Pastikan Anda menyalin dan menyimpannya di tempat yang aman.
            </p>

            <div class="input-group shadow-sm mb-2">
                <div class="input-group-prepend">
                    <span class="input-group-text bg-white border-right-0 text-muted px-3">
                        <i class="fa fa-terminal"></i>
                    </span>
                </div>
                <input type="text" class="form-control text-monospace bg-white border-left-0 border-right-0 font-weight-bold"
                    id="plain-text-token" value="{{ session('plain_text_token') }}" readonly autocomplete="off"
                    spellcheck="false" aria-label="Personal Access Token baru"
                    onclick="this.select();"
                    style="font-size: 0.95rem; color: #1e293b; letter-spacing: 0.5px; height: calc(2.25rem + 10px);">
                <div class="input-group-append">
                    <button type="button" class="btn btn-primary px-3 copy-api-token font-weight-bold d-flex align-items-center"
                        data-target="plain-text-token" title="Salin token ke clipboard">
                        <i class="fa fa-copy mr-1"></i>
                        <span>Salin token</span>
                    </button>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-between text-muted small mt-2">
                <div>
                    <i class="fa fa-info-circle text-primary mr-1"></i>
                    Gunakan pada header: <code>Authorization: Bearer &lt;token&gt;</code>
                </div>
                <div class="mt-1 mt-sm-0">
                    <i class="fa fa-shield-alt text-success mr-1"></i>
                    Jangan bagikan token ini kepada pihak yang tidak berwenang
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-xl-5 col-lg-6 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Buat Personal Access Token</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Token ini hanya dapat digunakan untuk menambahkan surat masuk dan surat keluar melalui API.
                    </p>

                    <form action="{{ route('api-tokens.store') }}" method="POST" autocomplete="off">
                        @csrf
                        <div class="form-group">
                            <label for="name">Nama token</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                id="name" name="name" value="{{ old('name') }}"
                                placeholder="Contoh: Integrasi SRIKANDI" maxlength="100" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="expires_in_days">Masa berlaku</label>
                            <select class="form-control @error('expires_in_days') is-invalid @enderror"
                                id="expires_in_days" name="expires_in_days" required>
                                <option value="30" @selected(old('expires_in_days') === '30')>30 hari</option>
                                <option value="90" @selected(old('expires_in_days') === '90')>90 hari</option>
                                <option value="365" @selected(old('expires_in_days', '365') === '365')>1 tahun</option>
                            </select>
                            @error('expires_in_days')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="current_password">Kata sandi saat ini</label>
                            <input type="password"
                                class="form-control @error('current_password') is-invalid @enderror"
                                id="current_password" name="current_password"
                                autocomplete="current-password" required>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-key mr-1"></i>Buat token
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-7 col-lg-6 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Cara menggunakan API</h3>
                </div>
                <div class="card-body">
                    <p>Kirim PDF sebagai <code>multipart/form-data</code> dan sertakan header berikut:</p>
                    <pre class="bg-light border rounded p-3"><code>Authorization: Bearer TOKEN_ANDA
Accept: application/json</code></pre>
                    <dl class="row mb-3">
                        <dt class="col-sm-4">Surat masuk</dt>
                        <dd class="col-sm-8"><code>POST {{ route('api.v1.surat.masuk.store') }}</code></dd>
                        <dt class="col-sm-4">Surat keluar</dt>
                        <dd class="col-sm-8"><code>POST {{ route('api.v1.surat.keluar.store') }}</code></dd>
                        <dt class="col-sm-4">Identitas token</dt>
                        <dd class="col-sm-8"><code>GET {{ route('api.v1.me') }}</code></dd>
                        <dt class="col-sm-4">Sifat akses</dt>
                        <dd class="col-sm-8"><code>GET {{ route('api.v1.references.accesses') }}</code></dd>
                        <dt class="col-sm-4">Berkas aktif</dt>
                        <dd class="col-sm-8"><code>GET {{ route('api.v1.references.active-filelists') }}</code></dd>
                        <dt class="col-sm-4">Detail seluruh berkas</dt>
                        <dd class="col-sm-8"><code>GET {{ route('api.v1.berkas.index') }}</code></dd>
                    </dl>
                    <button type="button" class="btn btn-outline-primary" data-toggle="modal"
                        data-target="#apiGuideModal">
                        <i class="fa fa-book mr-1"></i>Buka panduan pengisian
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
            <div class="d-flex align-items-center">
                <span class="mr-2 text-primary font-size-18">
                    <i class="fa fa-key"></i>
                </span>
                <h3 class="mb-0 font-weight-bold" style="font-size: 1.15rem;">Token Aktif</h3>
            </div>
            <span class="badge badge-pill badge-primary px-3 py-2 font-weight-bold">
                {{ $tokens->count() }} Token
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-secondary" style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            <th class="py-3 px-4 border-top-0" style="width: 25%;">Nama Token</th>
                            <th class="py-3 px-3 border-top-0">Kemampuan</th>
                            <th class="py-3 px-3 border-top-0">Dibuat</th>
                            <th class="py-3 px-3 border-top-0">Terakhir Digunakan</th>
                            <th class="py-3 px-3 border-top-0">Kedaluwarsa</th>
                            <th class="py-3 px-4 border-top-0 text-right" style="width: 10%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            <tr>
                                <td class="py-3 px-4 align-middle">
                                    <div class="d-flex align-items-center">
                                        <div class="mr-2 text-muted">
                                            <i class="fa fa-shield-alt text-primary"></i>
                                        </div>
                                        <div>
                                            <strong class="text-dark d-block" style="font-size: 0.95rem;">{{ $token->name }}</strong>
                                            <small class="text-muted text-monospace">ID: #{{ $token->getKey() }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 align-middle">
                                    @forelse ($token->abilities ?? [] as $ability)
                                        <span class="badge badge-info px-2 py-1">
                                            {{ $ability }}
                                        </span>
                                    @empty
                                        <span class="text-muted small">-</span>
                                    @endforelse
                                </td>
                                <td class="py-3 px-3 align-middle text-secondary small">
                                    <i class="fa fa-calendar-alt text-muted mr-1"></i>
                                    {{ $token->created_at?->translatedFormat('d M Y H:i') ?? '-' }}
                                </td>
                                <td class="py-3 px-3 align-middle small">
                                    @if ($token->last_used_at)
                                        <span class="text-dark">
                                            <i class="fa fa-clock text-info mr-1"></i>
                                            {{ $token->last_used_at->translatedFormat('d M Y H:i') }}
                                        </span>
                                    @else
                                        <span class="badge badge-light text-muted border px-2 py-1">Belum pernah</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 align-middle small">
                                    @if ($token->expires_at)
                                        @if ($token->expires_at->isPast())
                                            <span class="badge badge-danger px-2 py-1">
                                                <i class="fa fa-exclamation-circle mr-1"></i>Kedaluwarsa
                                            </span>
                                        @else
                                            <span class="text-dark">
                                                <i class="fa fa-hourglass-half text-warning mr-1"></i>
                                                {{ $token->expires_at->translatedFormat('d M Y H:i') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="badge badge-light text-muted border px-2 py-1">Tidak dibatasi</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 align-middle text-right">
                                    <form action="{{ route('api-tokens.destroy', $token->getKey()) }}"
                                        method="POST" class="d-inline revoke-api-token-form"
                                        data-token-name="{{ $token->name }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger shadow-none"
                                            title="Cabut token ini">
                                            <i class="fa fa-trash-alt mr-1"></i> Cabut
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <div class="mb-3">
                                            <span class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center"
                                                style="width: 54px; height: 54px;">
                                                <i class="fa fa-key fa-2x text-muted opacity-50"></i>
                                            </span>
                                        </div>
                                        <h5 class="font-weight-bold text-secondary mb-1">Belum Ada Token API</h5>
                                        <p class="small text-muted mb-0">Buat token baru melalui formulir di atas untuk menghubungkan integrasi aplikasi.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-api-guide-modal />
@endsection

@push('js')
    <script src="{{ asset('js/api-tokens.min.js') }}"></script>
@endpush
