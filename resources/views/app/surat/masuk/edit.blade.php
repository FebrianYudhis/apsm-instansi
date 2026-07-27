@extends('layouts.main')

@section('konten')
    <div class="card">
        <div class="card-header">
            <h3>Edit Data</h3>
        </div>
        <div class="card-body">
            <form class="konfirmasi-ubah-surat" action="{{ route('masuk.edit', [$data['id']]) }}" method="POST" enctype="multipart/form-data"
                autocomplete="off">
                @csrf
                <div class="form-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="isSrikandi"
                            name="isSrikandi"
                            {{ ($errors->any() ? old('isSrikandi') : $data['is_srikandi']) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isSrikandi">
                            Dari SRIKANDI ?
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="nomorAgenda">Nomor Agenda</label>
                    <input type="number" class="form-control" id="nomorAgenda" placeholder="Masukkan Nomor Agenda"
                        name="nomorAgenda" value="{{ old('nomorAgenda') ?? $data['nomor_agenda'] }}" REQUIRED>
                    @error('nomorAgenda')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                    <small class="text-danger">Harap Berhati-hati Jika Mengubah Nomor Agenda</small>
                </div>
                <div class="form-group">
                    <label for="tanggalDiterima">Tanggal Diterima</label>
                    <input type="date" class="form-control" id="tanggalDiterima" name="tanggalDiterima"
                        value="{{ old('tanggalDiterima') ?? $data['tanggal_diterima'] }}" REQUIRED>
                    @error('tanggalDiterima')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="nomorSurat">Nomor Surat</label>
                    <input type="text" class="form-control" id="nomorSurat" placeholder="Masukkan Nomor Surat"
                        name="nomorSurat" value="{{ old('nomorSurat') ?? $data['nomor_surat'] }}" REQUIRED>
                    @error('nomorSurat')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="pengirim">Pengirim</label>
                    <input type="text" class="form-control" id="pengirim" placeholder="Masukkan Pengirim" name="pengirim"
                        value="{{ old('pengirim') ?? $data['pengirim'] }}" REQUIRED>
                    @error('pengirim')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="tanggalSurat">Tanggal Surat</label>
                    <input type="date" class="form-control" id="tanggalSurat" name="tanggalSurat"
                        value="{{ old('tanggalSurat') ?? $data['tanggal_surat'] }}">
                </div>
                <div class="form-group">
                    <label for="perihal">Perihal</label>
                    <input type="text" class="form-control" id="perihal" placeholder="Masukkan Perihal" name="perihal"
                        value="{{ old('perihal') ?? $data['perihal'] }}" REQUIRED>
                    @error('perihal')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="sifat">Sifat Naskah</label>
                    <select class="form-control" id="sifat" name="sifat">
                        @foreach ($access as $item)
                            <option value="{{ $item->id }}" {{ $data['access_id'] == $item->id ? 'selected' : '' }}>
                                {{ $item->sifat_akses }}
                            </option>
                        @endforeach
                    </select>
                    @error('sifat')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="pemberkasan">Pemberkasan</label>
                    <select class="form-control" id="pemberkasan" name="pemberkasan">
                        <option value="null" {{ $data['filelist_id'] == NULL ? 'selected' : '' }}>-Kosongkan-</option>
                        @foreach ($filelist as $item)
                            <option value="{{ $item->id }}" {{ $data['filelist_id'] == $item->id ? 'selected' : '' }}>
                                {{ $item->classification->kode_klasifikasi }} -
                                {{ $item->nama_berkas }}
                            </option>
                        @endforeach
                    </select>
                    @error('pemberkasan')
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

@push('css')
    <link href="{{ asset('css/select2.min.css') }}" rel="stylesheet" />
@endpush

@push('js')
    <script src="{{ asset('js/select2.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            function syncSrikandiFields() {
                if ($('#isSrikandi').is(':checked')) {
                    $('#nomorAgenda').parent().hide();
                    $('#pemberkasan').parent().hide();
                    $('#nomorAgenda').val('').prop('required', false);
                    $('#pemberkasan').val($('#pemberkasan option:first').val()).trigger('change');
                } else {
                    $('#nomorAgenda').parent().show();
                    $('#pemberkasan').parent().show();
                    $('#nomorAgenda').prop('required', true);
                }
            }

            $('#pemberkasan').select2();
            $('#isSrikandi').on('change', syncSrikandiFields);
            syncSrikandiFields();

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
