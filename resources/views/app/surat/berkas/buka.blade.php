@extends('layouts.main')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/datatables.min.css') }}">
    <link href="{{ asset('css/select2.min.css') }}" rel="stylesheet" />
@endpush

@push('js')
    <script src="{{ asset('js/datatables.min.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>
@endpush

@push('js')
    <script>
        $(document).ready(function () {
            const berkasAktifApiUrl = `{{ route('berkas.aktif.list') }}`;
            const berkasAsalId = Number(`{{ $berkas['id'] }}`);
            const datatable = $('#datatabel').DataTable({
                scrollX: true,
                autoWidth: false,
                order: [[3, 'desc']],
            });

            $('#modalPemberkasan').select2({
                width: '100%',
                dropdownParent: $('#modalGantiLokasi'),
                placeholder: '- Pilih Pemberkasan -'
            });

            $('#modalPemberkasanBulk').select2({
                width: '100%',
                dropdownParent: $('#modalBulkPindah'),
                placeholder: '- Pilih Pemberkasan -'
            });

            function loadPemberkasanBulkOptions() {
                const select = $('#modalPemberkasanBulk');
                select.prop('disabled', true);
                select.html('<option value="">Memuat data pemberkasan...</option>').trigger('change');

                return $.ajax({
                    url: berkasAktifApiUrl,
                    method: 'GET',
                    dataType: 'json'
                }).done(function (response) {
                    const items = response && Array.isArray(response.data) ? response.data : [];

                    const availableItems = items.filter(function (item) {
                        return Number(item.id) !== berkasAsalId;
                    });

                    select.empty().append(new Option('- Pilih Pemberkasan -', ''));
                    availableItems.forEach(function (item) {
                        select.append(new Option(
                            String(item.kode_klasifikasi || '-') + ' - ' + String(item.nama_berkas || ''),
                            String(item.id)
                        ));
                    });

                    if (availableItems.length === 0) {
                        select.empty().append(new Option('Tidak ada data pemberkasan aktif', ''));
                    }

                    select.prop('disabled', availableItems.length === 0);
                    select.val('').trigger('change');
                }).fail(function () {
                    select.html('<option value="">Gagal memuat data</option>');
                    select.prop('disabled', true);
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: 'Data pemberkasan tujuan gagal dimuat dari server.'
                    });
                });
            }

            $('#modalKodeKlasifikasi').select2({
                width: '100%',
                dropdownParent: $('#modalEditBerkas'),
                placeholder: '- Pilih Klasifikasi -'
            });

            function refreshBulkUiState() {
                const total = datatable.$('.bulk-item').length;
                const checked = datatable.$('.bulk-item:checked').length;
                $('#bulkSelectedCount').text(checked);
                $('#bulkSelectedCountModal').text(checked);
                $('#btnOpenBulkModal').prop('disabled', checked === 0);
                $('#bulkSelectAll').prop('checked', total > 0 && total === checked);
            }

            $('#bulkSelectAll').on('change', function () {
                datatable.$('.bulk-item').prop('checked', $(this).is(':checked'));
                refreshBulkUiState();
            });

            $('#datatabel tbody').on('change', '.bulk-item', function () {
                refreshBulkUiState();
            });

            $('#btnOpenBulkModal').on('click', function () {
                const selectedItems = datatable.$('.bulk-item:checked').map(function () {
                    return $(this).val();
                }).get();

                const selectedContainer = $('#bulkSelectedContainer');
                selectedContainer.empty();

                selectedItems.forEach(function (value) {
                    selectedContainer.append('<input type="hidden" name="items[]" value="' + value + '">');
                });

                $('#modalBulkPindah').modal('show');
                loadPemberkasanBulkOptions();
            });

            $('#btnOpenEditBerkasModal').on('click', function () {
                $('#modalEditBerkas').modal('show');
            });

            @if ($errors->has('kodeKlasifikasi') || $errors->has('namaBerkas') || $errors->has('retensiAktif') || $errors->has('retensiInaktif') || $errors->has('keteranganAkhir'))
                $('#modalEditBerkas').modal('show');
            @endif

            $('#formBulkPindah').on('submit', function (event) {
                event.preventDefault();
                const form = this;

                Swal.fire({
                    title: 'Pindahkan surat terpilih?',
                    text: 'Pastikan berkas tujuan sudah benar.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, pindahkan',
                    cancelButtonText: 'Batal'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            refreshBulkUiState();
        });
    </script>
@endpush

@section('konten')
    @php
                    $canMoveItems = (int) $berkas['status_id'] === 1 && is_null($berkas['alih_media_status_id']);
    @endphp
    <div class="mt-4">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <span>Daftar Isi Berkas ({{ $berkas['nama_berkas'] }})</span>
                    @if (!$berkas->isAlihMediaLocked())
                        <button type="button" id="btnOpenEditBerkasModal" class="btn btn-primary">
                            Edit Berkas
                        </button>
                    @else
                        <button type="button" class="btn btn-secondary" disabled title="Terkunci karena alih media">
                            <i class="fa fa-lock"></i> Berkas Terkunci
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Kurun Waktu:</strong> {{ $kurunWaktu }}
                </div>

                @if ($canMoveItems)
                    <div class="mb-3 d-flex align-items-center">
                        <button type="button" id="btnOpenBulkModal" class="btn btn-primary mr-2" disabled>
                            Pindahkan Terpilih
                        </button>
                        <span>Dipilih: <strong id="bulkSelectedCount">0</strong></span>
                    </div>
                @endif
                <div class="table-responsive">
                    <table id="datatabel" class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                @if ($canMoveItems)
                                    <th>
                                        <input type="checkbox" id="bulkSelectAll">
                                    </th>
                                @endif
                                <th>Uraian Informasi Arsip</th>
                                <th>Nomor Naskah</th>
                                <th>Tanggal Item</th>
                                <th>SKKAD</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $item)
                                <tr>
                                    @if ($canMoveItems)
                                        <td>
                                            @if (!$item['is_locked'])
                                                <input type="checkbox" class="bulk-item" value="{{ $item['jenis'] . ':' . $item['id'] }}">
                                            @else
                                                <span class="text-muted" title="Terkunci karena alih media">
                                                    <i class="fa fa-lock"></i>
                                                </span>
                                            @endif
                                        </td>
                                    @endif
                                    <td>{{ $item['uraian'] }}</td>
                                    <td>{{ $item['nomor_naskah'] }}</td>
                                    <td>{{ $item['tanggal_item'] }}</td>
                                    <td>{{ $item['skkad'] }}</td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <a href="{{ route('surat.detailItem', [$item['jenis'], $item['id']]) }}"
                                                class="btn btn-info btn-sm"
                                                target="_blank"
                                                rel="noopener noreferrer" title="Lihat Detail">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>

                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalBulkPindah" tabindex="-1" aria-labelledby="modalBulkPindahLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formBulkPindah" method="POST" action="{{ route('berkas.gantiLokasiBulk') }}">
                    @csrf
                    <input type="hidden" name="berkas_asal" value="{{ $berkas['id'] }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalBulkPindahLabel">Pindahkan Surat Terpilih</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">
                            Jumlah surat dipilih: <strong id="bulkSelectedCountModal">0</strong>
                        </p>

                        <div class="form-group">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="modalPemberkasanBulk" class="mb-0">Pemberkasan Tujuan</label>
                                <a href="{{ route('berkas.tambah') }}" target="_blank" rel="noopener noreferrer"
                                    class="btn btn-sm btn-outline-primary">
                                    Tambah Berkas Baru
                                </a>
                            </div>
                            <select class="form-control" id="modalPemberkasanBulk" name="pemberkasan" required>
                                <option value="">- Pilih Pemberkasan -</option>
                            </select>
                        </div>

                        <div id="bulkSelectedContainer"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditBerkas" tabindex="-1" aria-labelledby="modalEditBerkasLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('berkas.edit', $berkas['id']) }}" autocomplete="off">
                    @csrf
                    <input type="hidden" name="redirect_back" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEditBerkasLabel">Edit Berkas</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="modalKodeKlasifikasi">Kode Klasifikasi</label>
                            <select class="form-control" id="modalKodeKlasifikasi" name="kodeKlasifikasi" required>
                                @foreach ($classification as $itemKlasifikasi)
                                    <option value="{{ $itemKlasifikasi->id }}"
                                        {{ (string) old('kodeKlasifikasi', $berkas['classification_id']) === (string) $itemKlasifikasi->id ? 'selected' : '' }}>
                                        {{ $itemKlasifikasi->kode_klasifikasi }}
                                    </option>
                                @endforeach
                            </select>
                            @error('kodeKlasifikasi')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="modalNamaBerkas">Nama Berkas</label>
                            <input type="text"
                                class="form-control"
                                id="modalNamaBerkas"
                                name="namaBerkas"
                                value="{{ old('namaBerkas', $berkas['nama_berkas']) }}"
                                placeholder="Masukkan Nama Berkas"
                                required>
                            @error('namaBerkas')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="modalRetensiAktif">Retensi Aktif (Satuan Tahun)</label>
                            <input type="number"
                                class="form-control"
                                id="modalRetensiAktif"
                                name="retensiAktif"
                                value="{{ old('retensiAktif', $berkas['retensi_aktif']) }}"
                                placeholder="Masukkan Retensi Aktif"
                                required>
                            @error('retensiAktif')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="modalRetensiInaktif">Retensi Inaktif (Satuan Tahun)</label>
                            <input type="number"
                                class="form-control"
                                id="modalRetensiInaktif"
                                name="retensiInaktif"
                                value="{{ old('retensiInaktif', $berkas['retensi_inaktif']) }}"
                                placeholder="Masukkan Retensi Inaktif"
                                required>
                            @error('retensiInaktif')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group mb-0">
                            <label for="modalKeteranganAkhir">Keterangan Akhir</label>
                            <select class="form-control" id="modalKeteranganAkhir" name="keteranganAkhir" required>
                                <option value="Permanen" {{ old('keteranganAkhir', $berkas['keterangan_akhir'] ?? 'Permanen') == 'Permanen' ? 'selected' : '' }}>Permanen</option>
                                <option value="Musnah" {{ old('keteranganAkhir', $berkas['keterangan_akhir'] ?? 'Permanen') == 'Musnah' ? 'selected' : '' }}>Musnah</option>
                            </select>
                            @error('keteranganAkhir')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
