@extends('layouts.main')

@section('konten')
    <div class="card">
        <div class="card-header">
            <h3>Edit Data</h3>
        </div>
        <div class="card-body">
            <form class="konfirmasi-ubah-surat" action="{{ route('keluar.edit', [$data['id']]) }}" method="POST" enctype="multipart/form-data"
                autocomplete="off">
                @csrf
                <div class="form-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="isSrikandi"
                            name="isSrikandi"
                            {{ ($errors->any() ? old('isSrikandi') : $data['is_srikandi']) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isSrikandi">
                            Dikirim melalui SRIKANDI?
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="jenis">Jenis Naskah</label>
                    <select class="form-control" id="jenis" name="jenis">
                        <option value="0" {{ old('jenis', $data['is_digital'] ? '1' : '0') == '0' ? 'selected' : '' }}>Manual</option>
                        <option value="1" {{ old('jenis', $data['is_digital'] ? '1' : '0') == '1' ? 'selected' : '' }}>Digital</option>
                    </select>
                    @error('jenis')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="tanggalSurat">Tanggal Surat</label>
                    <input type="date" class="form-control" id="tanggalSurat" name="tanggalSurat"
                        value="{{ old('tanggalSurat') ?? $data['tanggal_surat'] }}" REQUIRED>
                    @error('tanggalSurat')
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
                    <label for="tujuan">Tujuan</label>
                    <input type="text" class="form-control" id="tujuan" placeholder="Masukkan Tujuan" name="tujuan"
                        value="{{ old('tujuan') ?? $data['tujuan'] }}" REQUIRED>
                    @error('tujuan')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
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
                            <option value="{{ $item->id }}" {{ old('sifat', $data['access_id']) == $item->id ? 'selected' : '' }}>
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
                        <option value="" {{ old('pemberkasan', $data['filelist_id']) ? '' : 'selected' }}>-Kosongkan-</option>
                        @foreach ($filelist->sortBy(['classification.kode_klasifikasi', 'nama_berkas'])->groupBy(fn ($item) => $item->classification->kode_klasifikasi . ' - ' . ($item->classification->keterangan ?? 'Tanpa Keterangan')) as $namaKlasifikasi => $berkasList)
                            <optgroup label="{{ $namaKlasifikasi }}">
                                @foreach ($berkasList as $item)
                                    <option value="{{ $item->id }}" {{ old('pemberkasan', $data['filelist_id']) == $item->id ? 'selected' : '' }}>
                                        {{ $item->nama_berkas }}
                                    </option>
                                @endforeach
                            </optgroup>
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
            function matchOptgroup(params, data) {
                if ($.trim(params.term) === '') {
                    return data;
                }
                if (typeof data.text === 'undefined') {
                    return null;
                }
                var term = params.term.toUpperCase();
                var text = data.text.toUpperCase();
                if (data.children && data.children.length > 0) {
                    if (text.indexOf(term) > -1) {
                        return data;
                    }
                    var matchedChildren = [];
                    for (var i = 0; i < data.children.length; i++) {
                        var child = data.children[i];
                        if (child.text && child.text.toUpperCase().indexOf(term) > -1) {
                            matchedChildren.push(child);
                        }
                    }
                    if (matchedChildren.length > 0) {
                        var modifiedData = $.extend({}, data, true);
                        modifiedData.children = matchedChildren;
                        return modifiedData;
                    }
                    return null;
                }
                if (text.indexOf(term) > -1) {
                    return data;
                }
                return null;
            }

            $('#pemberkasan').select2({
                matcher: matchOptgroup,
                width: '100%'
            });

            function toggleSrikandi() {
                if ($('#isSrikandi').is(':checked')) {
                    $('#jenis').closest('.form-group').hide();
                    $('#jenis').val("1").trigger('change');
                    $('#pemberkasan').closest('.form-group').hide();
                    $('#pemberkasan').val('').trigger('change');
                } else {
                    $('#jenis').closest('.form-group').show();
                    $('#pemberkasan').closest('.form-group').show();
                }
            }

            $('#isSrikandi').on('change', toggleSrikandi);
            toggleSrikandi();

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
