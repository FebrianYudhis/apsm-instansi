@extends('layouts.main')

@section('konten')
    @if (session('plain_text_token'))
        <div class="alert alert-warning" role="alert">
            <h4 class="alert-heading">Salin token sekarang</h4>
            <p>Token hanya ditampilkan satu kali. Simpan di tempat yang aman dan jangan memasukkannya ke URL atau log aplikasi.</p>
            <div class="input-group">
                <input type="text" class="form-control text-monospace" id="plain-text-token"
                    value="{{ session('plain_text_token') }}" readonly autocomplete="off"
                    spellcheck="false" aria-label="Personal Access Token baru">
                <div class="input-group-append">
                    <button type="button" class="btn btn-dark copy-api-token" data-target="plain-text-token">
                        Salin token
                    </button>
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
                    </dl>
                    <button type="button" class="btn btn-outline-primary" data-toggle="modal"
                        data-target="#apiGuideModal">
                        <i class="fa fa-book mr-1"></i>Buka panduan pengisian
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="mb-0">Token aktif</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Kemampuan</th>
                            <th>Dibuat</th>
                            <th>Terakhir digunakan</th>
                            <th>Kedaluwarsa</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            <tr>
                                <td>{{ $token->name }}</td>
                                <td>
                                    @foreach ($token->abilities ?? [] as $ability)
                                        <span class="badge badge-info">{{ $ability }}</span>
                                    @endforeach
                                </td>
                                <td>{{ $token->created_at?->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    {{ $token->last_used_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah' }}
                                </td>
                                <td>{{ $token->expires_at?->translatedFormat('d M Y H:i') ?? 'Tidak dibatasi' }}</td>
                                <td class="text-right">
                                    <form action="{{ route('api-tokens.destroy', $token->getKey()) }}"
                                        method="POST" class="d-inline revoke-api-token-form"
                                        data-token-name="{{ $token->name }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            Cabut
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Belum ada token API.
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
