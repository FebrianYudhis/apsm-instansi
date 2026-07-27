@extends('layouts.main')

@section('konten')
    <div class="card">
        <div class="card-header">
            <h3>Edit Data</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('klasifikasi.edit', $data['id']) }}" method="POST" autocomplete="off">
                @csrf
                <div class="form-group">
                    <label for="kodeKlasifikasi">Kode Klasifikasi</label>
                    <input type="text" class="form-control" id="kodeKlasifikasi" placeholder="Masukkan Kode Klasifikasi"
                        name="kodeKlasifikasi" value="{{ old('kodeKlasifikasi') ?? $data['kode_klasifikasi'] }}" REQUIRED>
                    @error('kodeKlasifikasi')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="keterangan">Keterangan</label>
                    <textarea class="form-control" id="keterangan" rows="3" name="keterangan"
                        REQUIRED>{{ old('keterangan') ?? $data['keterangan'] }}</textarea>
                    @error('keterangan')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">Edit</button>
            </form>
        </div>
    </div>
@endsection