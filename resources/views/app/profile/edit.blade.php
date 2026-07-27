@extends('layouts.main')

@section('konten')
    <div class="card">
        <div class="card-header">
            <h3>Profil Saya</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('profil.update') }}" method="POST" autocomplete="off">
                @csrf
                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <input type="text" class="form-control" id="name" placeholder="Masukkan Nama Lengkap"
                        name="name" value="{{ old('name', auth()->user()->name) }}" REQUIRED>
                    @error('name')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="username">Nama Pengguna (Username)</label>
                    <input type="text" class="form-control" id="username" placeholder="Masukkan Nama Pengguna"
                        name="username" value="{{ old('username', auth()->user()->username) }}" REQUIRED>
                    @error('username')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="password">Kata Sandi Baru</label>
                    <input type="password" class="form-control" id="password" placeholder="Biarkan kosong jika tidak ingin mengubah kata sandi"
                        name="password" autocomplete="new-password">
                    <small class="form-text text-muted">Minimal 8 karakter.</small>
                    @error('password')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div id="password_security_fields" style="display: none;">
                    <div class="form-group">
                        <label for="current_password">Kata Sandi Saat Ini</label>
                        <input type="password" class="form-control @error('current_password') is-invalid @enderror"
                            id="current_password" placeholder="Masukkan kata sandi saat ini"
                            name="current_password" autocomplete="current-password">
                        @error('current_password')
                            <div class="text-danger mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Konfirmasi Kata Sandi Baru</label>
                        <input type="password" class="form-control" id="password_confirmation"
                            placeholder="Ulangi kata sandi baru" name="password_confirmation"
                            autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </form>
        </div>
    </div>
@endsection

@push('js')
<script>
    $(document).ready(function() {
        @if($errors->has('current_password') || $errors->has('password') || $errors->has('password_confirmation'))
            $('#password_security_fields').show();
        @endif

        $('#password').on('input', function() {
            if ($(this).val().length > 0) {
                $('#password_security_fields').slideDown();
            } else {
                $('#password_security_fields').slideUp();
                $('#current_password').val('');
                $('#password_confirmation').val('');
            }
        });
    });
</script>
@endpush
