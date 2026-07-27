@extends('layouts.main')

@section('konten')
    <div class="card">
        <div class="card-header">
            <h3>Tambah Data</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('berkas.tambah') }}" method="POST" autocomplete="off">
                @csrf
                <div class="form-group">
                    <label for="kodeKlasifikasi">Kode Klasifikasi</label>
                    <select class="form-control" id="kodeKlasifikasi" name="kodeKlasifikasi">
                        @foreach ($classification as $item)
                            <option value="{{ $item->id }}">{{ $item->kode_klasifikasi }}</option>
                        @endforeach
                    </select>
                    @error('kodeKlasifikasi')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="namaBerkas">Nama Berkas</label>
                    <input type="text" class="form-control" id="namaBerkas" placeholder="Masukkan Nama Berkas"
                        name="namaBerkas" value="{{ old('namaBerkas') }}" REQUIRED>
                    @error('namaBerkas')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="retensiAktif">Retensi Aktif (Satuan Tahun)</label>
                    <input type="number" class="form-control" id="retensiAktif" placeholder="Masukkan Retensi Aktif"
                        name="retensiAktif" value="{{ old('retensiAktif') }}" REQUIRED>
                    @error('retensiAktif')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="retensiInaktif">Retensi Inaktif (Satuan Tahun)</label>
                    <input type="number" class="form-control" id="retensiInaktif" placeholder="Masukkan Retensi Aktif"
                        name="retensiInaktif" value="{{ old('retensiInaktif') }}" REQUIRED>
                    @error('retensiInaktif')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="keteranganAkhir">Keterangan Akhir</label>
                    <select class="form-control" id="keteranganAkhir" name="keteranganAkhir" required>
                        <option value="Permanen" {{ old('keteranganAkhir') == 'Permanen' ? 'selected' : '' }}>Permanen</option>
                        <option value="Musnah" {{ old('keteranganAkhir') == 'Musnah' ? 'selected' : '' }}>Musnah</option>
                    </select>
                    @error('keteranganAkhir')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">Tambah</button>
            </form>
        </div>
    </div>
@endsection

@push('css')
    <link href="{{ asset('css/select2.min.css') }}" rel="stylesheet" />
@endpush

@push('js')
    <script src="{{ asset('js/select2.min.js') }}"></script>
    <script>
        $(document).ready(function () { $('#kodeKlasifikasi').select2(); });
    </script>
@endpush
