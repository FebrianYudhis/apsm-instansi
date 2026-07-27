@extends('layouts.main')

@section('konten')
<div class="card">
    <div class="card-header">
        <h3>Edit Data</h3>
    </div>
    <div class="card-body">
        <form class="konfirmasi-ubah-surat" action="{{ route('digital.edit',[$data['id']]) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="perihal">Perihal</label>
                <input type="text" class="form-control" id="perihal" placeholder="Masukkan Perihal" name="perihal"
                    value="{{ old('perihal') ?? $data['perihal'] }}" REQUIRED>
                @error('perihal')
                <div class="text-danger mt-2">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="berkas">Berkas (PDF)</label>
                <input type="file" class="form-control-file" id="berkas" accept="application/pdf" name="berkas">
                @error('berkas')
                <div class="text-danger mt-2">{{ $message }}</div>
                @enderror
                <small class="text-danger">Biarkan Kosong Jika Berkas Tidak Diganti</small>
            </div>
            <button type="submit" class="btn btn-primary">Edit</button>
        </form>
    </div>
</div>
@endsection

@push('js')
    <script>
        $(document).ready(function () {
            $('.konfirmasi-ubah-surat').on('submit', function (event) {
                event.preventDefault();
                const form = this;

                Swal.fire({
                    title: 'Simpan perubahan?',
                    text: 'Pastikan data dan PDF yang dipilih sudah benar.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, simpan',
                    cancelButtonText: 'Batal'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush
