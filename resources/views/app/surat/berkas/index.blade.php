@extends('layouts.main')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/datatables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/select2.min.css') }}">
@endpush

@push('js')
    <script src="{{ asset('js/datatables.min.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>
@endpush

@push('js')
    <script>
        $(document).ready(function() {
            $('#filterKlasifikasi').select2({
                width: '100%',
                dropdownParent: $('#modalFilterBerkas'),
                placeholder: 'Semua Kode',
                allowClear: true
            });

            $('#filterStatus').select2({
                width: '100%',
                dropdownParent: $('#modalFilterBerkas'),
                placeholder: 'Semua Status',
                allowClear: true
            });

            $('#filterKeteranganAkhir').select2({
                width: '100%',
                dropdownParent: $('#modalFilterBerkas'),
                placeholder: 'Semua Keterangan',
                allowClear: true
            });

            const exportBaseUrl = `{{ route('surat.berkas.export') }}`;

            function getAllowedExportTypes(statusId) {
                if (!statusId || statusId === '1' || statusId === '3') {
                    return ['daftar_berkas', 'daftar_isi_berkas'];
                }

                return ['daftar_isi_berkas'];
            }

            function buildExportUrl(jenisExport) {
                const params = new URLSearchParams();
                const activeParams = new URLSearchParams(window.location.search);
                const kodeKlasifikasi = activeParams.get('kode_klasifikasi');
                const statusId = activeParams.get('status_id');
                const keteranganAkhir = activeParams.get('keterangan_akhir');
                const tanggalDari = activeParams.get('tanggal_dari');
                const tanggalSampai = activeParams.get('tanggal_sampai');

                if (jenisExport) params.set('jenis_export', jenisExport);
                if (kodeKlasifikasi) params.set('kode_klasifikasi', kodeKlasifikasi);
                if (statusId) params.set('status_id', statusId);
                if (keteranganAkhir) params.set('keterangan_akhir', keteranganAkhir);
                if (tanggalDari) params.set('tanggal_dari', tanggalDari);
                if (tanggalSampai) params.set('tanggal_sampai', tanggalSampai);

                const query = params.toString();
                return query ? `${exportBaseUrl}?${query}` : exportBaseUrl;
            }

            function updateExportUrl() {
                const activeParams = new URLSearchParams(window.location.search);
                const allowedExportTypes = getAllowedExportTypes(activeParams.get('status_id'));
                const daftarBerkasVisible = allowedExportTypes.includes('daftar_berkas');

                $('#btnExportDaftarBerkas').attr('href', buildExportUrl('daftar_berkas'));
                $('#btnExportDaftarIsiBerkas').attr('href', buildExportUrl('daftar_isi_berkas'));

                $('#exportDaftarBerkasWrapper').toggle(daftarBerkasVisible);
                $('#exportDaftarIsiBerkasWrapper')
                    .toggleClass('col-sm-6', daftarBerkasVisible)
                    .toggleClass('col-sm-12', !daftarBerkasVisible);
            }

            function syncFilterToUrl() {
                const params = new URLSearchParams(window.location.search);
                const kodeKlasifikasi = $('#filterKlasifikasi').val();
                const statusId = $('#filterStatus').val();
                const keteranganAkhir = $('#filterKeteranganAkhir').val();
                const tanggalDari = $('#filterTanggalDari').val();
                const tanggalSampai = $('#filterTanggalSampai').val();

                if (kodeKlasifikasi) {
                    params.set('kode_klasifikasi', kodeKlasifikasi);
                } else {
                    params.delete('kode_klasifikasi');
                }

                if (statusId) {
                    params.set('status_id', statusId);
                } else {
                    params.delete('status_id');
                }

                if (keteranganAkhir) {
                    params.set('keterangan_akhir', keteranganAkhir);
                } else {
                    params.delete('keterangan_akhir');
                }

                if (tanggalDari) {
                    params.set('tanggal_dari', tanggalDari);
                } else {
                    params.delete('tanggal_dari');
                }

                if (tanggalSampai) {
                    params.set('tanggal_sampai', tanggalSampai);
                } else {
                    params.delete('tanggal_sampai');
                }

                const query = params.toString();
                const newUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;
                window.history.replaceState({}, '', newUrl);
            }

            const table = $('#datatabel').DataTable({
                scrollX: true,
                autoWidth: false,
                paging: true,
                processing: true,
                serverSide: true,
                ajax: {
                    url: `{{ route('surat.berkas') }}`,
                    data: function(d) {
                        d.kode_klasifikasi = $('#filterKlasifikasi').val();
                        d.status_id = $('#filterStatus').val();
                        d.keterangan_akhir = $('#filterKeteranganAkhir').val();
                        d.tanggal_dari = $('#filterTanggalDari').val();
                        d.tanggal_sampai = $('#filterTanggalSampai').val();
                    }
                },
                columns: [{
                        data: 'kode_klasifikasi',
                        name: 'classifications.kode_klasifikasi',
                    },
                    {
                        data: 'nama_berkas',
                        name: 'filelists.nama_berkas'
                    },
                    {
                        data: 'nama_status',
                        name: 'statuses.nama_status',
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: 'total_isi',
                        name: 'total_isi',
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: 'null',
                        name: 'retensi',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return row.retensi_aktif + " / " + row.retensi_inaktif;
                        }
                    },
                    {
                        data: null,
                        name: 'keterangan_akhir',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return row.keterangan_akhir || "-";
                        }
                    },
                    {
                        data: 'aksi',
                        name: 'aksi',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        searchable: false
                    },
                ],
            });

            $('#btnOpenFilterModal').on('click', function() {
                $('#modalFilterBerkas').modal('show');
            });

            $('#btnCariFilter').on('click', function() {
                syncFilterToUrl();
                updateExportUrl();
                table.ajax.reload();
                $('#modalFilterBerkas').modal('hide');
            });

            $('#resetFilterItem').on('click', function() {
                $('#filterKlasifikasi').val('').trigger('change');
                $('#filterStatus').val('').trigger('change');
                $('#filterKeteranganAkhir').val('').trigger('change');
                $('#filterTanggalDari').val('');
                $('#filterTanggalSampai').val('');
                syncFilterToUrl();
                updateExportUrl();
                table.ajax.reload();
            });

            updateExportUrl();

            table.on('xhr.dt', function(e, settings, json) {
                if (!json) return;
                $('#totalBerkas').text(json.recordsFiltered ?? 0);
                $('#totalBerkasAll').text(json.recordsTotal ?? 0);
            });
        });
    </script>

    <script src="{{ asset('js/delete-confirmation.js') }}"></script>

    <script>
        $('#datatabel').on("click", ".konfirmasi-pindah", function(event) {
            const form = $(this).closest("form");
            event.preventDefault();

            MfaCodeInput.prompt({
                title: 'Pindah Status Berkas',
                description: 'Masukkan kode MFA 6 digit untuk melanjutkan perubahan status berkas.',
                confirmButtonText: 'Lanjutkan',
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                form.find('input[name="password_status_berkas"]').remove();
                form.append(
                    `<input type="hidden" name="password_status_berkas" value="${$('<div>').text(result.value).html()}">`
                );
                form.submit();
            });
        });
    </script>
@endpush

@section('konten')
    <div class="mt-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daftar Berkas</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    Total Berkas: <strong id="totalBerkas">0</strong>
                    <span class="ml-2">(Total Keseluruhan: <strong id="totalBerkasAll">0</strong>)</span>
                </div>
                <div class="table-responsive">
                    <table id="datatabel" class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Kode Klasifikasi</th>
                                <th>Nama Berkas</th>
                                <th>Status Berkas</th>
                                <th>Jumlah Isi Berkas</th>
                                <th>Retensi Aktif / Inaktif</th>
                                <th>Keterangan Akhir</th>
                                <th>Aksi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('components.floating-actions', [
        'id' => 'berkas-floating-actions',
        'actions' => [
            [
                'label' => 'Filter Data',
                'icon' => 'fa fa-filter',
                'class' => 'btn-info',
                'attributes' => [
                    'id' => 'btnOpenFilterModal',
                ],
            ],
            [
                'url' => route('berkas.tambah'),
                'label' => 'Tambah Data',
                'icon' => 'fa fa-plus',
                'class' => 'btn-primary',
            ],
        ],
    ])

    <div class="modal fade" id="modalFilterBerkas" tabindex="-1" aria-labelledby="modalFilterBerkasLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFilterBerkasLabel">Filter Berkas</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="filterKlasifikasi" class="mb-1">Filter Kode Klasifikasi</label>
                            <select id="filterKlasifikasi" class="form-control">
                                <option value=""></option>
                                @foreach ($classifications as $classification)
                                    <option value="{{ $classification->kode_klasifikasi }}"
                                        {{ request('kode_klasifikasi') == $classification->kode_klasifikasi ? 'selected' : '' }}>
                                        {{ $classification->kode_klasifikasi }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="filterStatus" class="mb-1">Filter Status Berkas</label>
                            <select id="filterStatus" class="form-control">
                                <option value=""></option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->id }}"
                                        {{ request('status_id') == $status->id ? 'selected' : '' }}>
                                        {{ $status->nama_status }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <label for="filterKeteranganAkhir" class="mb-1">Filter Keterangan Akhir</label>
                            <select id="filterKeteranganAkhir" class="form-control">
                                <option value=""></option>
                                <option value="Permanen" {{ request('keterangan_akhir') == 'Permanen' ? 'selected' : '' }}>
                                    Permanen</option>
                                <option value="Musnah" {{ request('keterangan_akhir') == 'Musnah' ? 'selected' : '' }}>
                                    Musnah</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="filterTanggalDari" class="mb-1">Tanggal Item Dari</label>
                            <input type="date" id="filterTanggalDari" class="form-control"
                                value="{{ request('tanggal_dari') }}">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <label for="filterTanggalSampai" class="mb-1">Tanggal Item Sampai</label>
                            <input type="date" id="filterTanggalSampai" class="form-control"
                                value="{{ request('tanggal_sampai') }}">
                        </div>
                    </div>
                    <div class="row">
                        <div id="exportDaftarBerkasWrapper" class="col-sm-6 mt-4">
                            <a id="btnExportDaftarBerkas"
                                href="{{ route('surat.berkas.export', ['jenis_export' => 'daftar_berkas']) }}"
                                class="btn btn-success w-100">Export Daftar Berkas</a>
                        </div>
                        <div id="exportDaftarIsiBerkasWrapper" class="col-sm-6 mt-4">
                            <a id="btnExportDaftarIsiBerkas"
                                href="{{ route('surat.berkas.export', ['jenis_export' => 'daftar_isi_berkas']) }}"
                                class="btn btn-success w-100">Export Daftar Isi Berkas</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="resetFilterItem" class="btn btn-secondary">Reset Filter Item</button>
                    <button type="button" id="btnCariFilter" class="btn btn-primary">Terapkan Filter</button>
                </div>
            </div>
        </div>
    </div>
@endsection
