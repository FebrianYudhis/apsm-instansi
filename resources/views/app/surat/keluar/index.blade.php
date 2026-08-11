@extends('layouts.main')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/datatables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/select2.min.css') }}">
@endpush

@push('js')
    <script src="{{ asset('js/datatables.min.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>
    <script src="{{ asset('js/direct-filing.min.js') }}"></script>
@endpush

@push('js')
    <script>
        $(document).ready(function () {
            const exportBaseUrl = `{{ route('surat.keluar.export-pencatatan') }}`;

            function buildExportUrl() {
                const activeParams = new URLSearchParams(window.location.search);
                const params = new URLSearchParams();

                params.set(
                    'jalur_pengiriman',
                    activeParams.get('jalur_pengiriman') || 'semua'
                );

                if (activeParams.get('tanggal_dari')) {
                    params.set('tanggal_dari', activeParams.get('tanggal_dari'));
                }

                if (activeParams.get('tanggal_sampai')) {
                    params.set('tanggal_sampai', activeParams.get('tanggal_sampai'));
                }

                return `${exportBaseUrl}?${params.toString()}`;
            }

            function updateExportUrl() {
                $('#btnExportSuratKeluar').attr('href', buildExportUrl());
            }

            function syncFilterToUrl() {
                const params = new URLSearchParams(window.location.search);
                const jalurPengiriman = $('#filterJalurPengiriman').val();
                const tanggalDari = $('#filterTanggalDari').val();
                const tanggalSampai = $('#filterTanggalSampai').val();

                params.set('jalur_pengiriman', jalurPengiriman || 'semua');

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
                const newUrl = query
                    ? `${window.location.pathname}?${query}`
                    : window.location.pathname;
                window.history.replaceState({}, '', newUrl);
            }

            function tanggalValid() {
                const tanggalDari = $('#filterTanggalDari').val();
                const tanggalSampai = $('#filterTanggalSampai').val();

                if (tanggalDari && tanggalSampai && tanggalSampai < tanggalDari) {
                    Swal.fire({
                        title: 'Filter Tidak Valid',
                        text: 'Tanggal Surat Sampai tidak boleh lebih awal dari Tanggal Surat Dari.',
                        icon: 'error',
                    });
                    return false;
                }

                return true;
            }

            const table = $('#datatabel').DataTable({
                scrollX: true,
                autoWidth: false,
                paging: true,
                processing: true,
                serverSide: true,
                ajax: {
                    url: `{{ route('surat.keluar') }}`,
                    data: function (d) {
                        d.jalur_pengiriman = $('#filterJalurPengiriman').val();
                        d.tanggal_dari = $('#filterTanggalDari').val();
                        d.tanggal_sampai = $('#filterTanggalSampai').val();
                    }
                },
                columns: [
                    {
                        data: null,
                        name: 'jenis',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (row.is_digital == 1) {
                                return `<span class="badge badge-primary">Digital</span>`;
                            } else {
                                return `<span class="badge badge-secondary">Manual</span>`;
                            }
                        }
                    },
                    {
                        data: null,
                        name: 'jalur_pengiriman',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (row.is_srikandi == 1) {
                                return `<span class="badge badge-success">SRIKANDI</span>`;
                            }

                            return `<span class="badge badge-secondary">Manual</span>`;
                        }
                    },
                    {
                        data: 'tanggal_surat',
                        name: 'tanggal_surat'
                    },
                    {
                        data: 'nomor_surat',
                        name: 'nomor_surat'
                    },
                    {
                        data: 'tujuan',
                        name: 'tujuan'
                    },
                    {
                        data: 'perihal',
                        name: 'perihal'
                    },
                    {
                        data: null,
                        name: 'sifat_naskah',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (row.access_id == null) {
                                return `<span class="badge badge-danger">-</span>`;
                            } else {
                                return `<span class="badge badge-info">${row.access.sifat_akses}</span>`;
                            }
                        }
                    },
                    {
                        data: null,
                        name: 'pemberkasan',
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            if (row.filelist) {
                                return row.filelist.classification.kode_klasifikasi + ' - ' + row.filelist.nama_berkas;
                            } else if (row.is_srikandi == 1) {
                                return `<span class="badge badge-secondary">Tidak berlaku</span>`;
                            } else {
                                return `<span class="badge badge-warning">Belum</span>`;
                            }
                        },
                    },
                    {
                        data: 'aksi',
                        name: 'aksi',
                        orderable: false,
                        searchable: false
                    },
                ],
                order: [
                    [2, 'desc']
                ]
            });

            $('#filterTanggalDari').on('change', function () {
                $('#filterTanggalSampai').attr('min', this.value || '');
            }).trigger('change');

            $('#btnCariFilter').on('click', function () {
                if (!tanggalValid()) {
                    return;
                }

                syncFilterToUrl();
                updateExportUrl();
                table.ajax.reload();
                $('#modalFilterSuratKeluar').modal('hide');
            });

            $('#resetFilterSuratKeluar').on('click', function () {
                $('#filterJalurPengiriman').val('semua');
                $('#filterTanggalDari').val('');
                $('#filterTanggalSampai').val('').attr('min', '');
                syncFilterToUrl();
                updateExportUrl();
                table.ajax.reload();
            });

            updateExportUrl();
        });
    </script>

    <script src="{{ asset('js/delete-confirmation.min.js') }}"></script>
@endpush

@section('konten')
    <div class="mt-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daftar Surat Keluar</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="datatabel" class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Jenis</th>
                                <th>Jalur Pengiriman</th>
                                <th>Tanggal Surat</th>
                                <th>Nomor Surat</th>
                                <th>Tujuan</th>
                                <th>Perihal</th>
                                <th>Sifat Naskah</th>
                                <th>Pemberkasan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('components.floating-actions', [
        'id' => 'surat-keluar-floating-actions',
        'actions' => [
            [
                'url' => route('keluar.tambah'),
                'label' => 'Tambah Data',
                'icon' => 'fa fa-plus',
                'class' => 'btn-primary',
            ],
            [
                'label' => 'Filter dan Export Surat Keluar',
                'icon' => 'fa fa-filter',
                'class' => 'btn-info',
                'attributes' => [
                    'data-toggle' => 'modal',
                    'data-target' => '#modalFilterSuratKeluar',
                ],
            ],
        ],
    ])

    @include('components.direct-filing-modal')

    <div class="modal fade" id="modalFilterSuratKeluar" tabindex="-1"
        aria-labelledby="modalFilterSuratKeluarLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFilterSuratKeluarLabel">
                        Filter dan Export Surat Keluar
                    </h5>
                    <button type="button" class="close" data-dismiss="modal"
                        aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="filterJalurPengiriman">Jalur Pengiriman</label>
                        <select id="filterJalurPengiriman" name="jalur_pengiriman"
                            class="form-control">
                            <option value="semua"
                                {{ request('jalur_pengiriman', 'semua') === 'semua' ? 'selected' : '' }}>
                                Semua
                            </option>
                            <option value="srikandi"
                                {{ request('jalur_pengiriman') === 'srikandi' ? 'selected' : '' }}>
                                SRIKANDI
                            </option>
                            <option value="non_srikandi"
                                {{ request('jalur_pengiriman') === 'non_srikandi' ? 'selected' : '' }}>
                                Tanpa SRIKANDI
                            </option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="filterTanggalDari">Tanggal Surat Dari</label>
                            <input type="date" id="filterTanggalDari"
                                name="tanggal_dari" class="form-control"
                                value="{{ request('tanggal_dari') }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="filterTanggalSampai">Tanggal Surat Sampai</label>
                            <input type="date" id="filterTanggalSampai"
                                name="tanggal_sampai" class="form-control"
                                value="{{ request('tanggal_sampai') }}">
                        </div>
                    </div>
                    <small class="form-text text-muted">
                        Terapkan filter terlebih dahulu. Daftar dan file Excel
                        menggunakan filter aktif yang sama pada tahun aktif.
                    </small>
                    <a id="btnExportSuratKeluar"
                        href="{{ route('surat.keluar.export-pencatatan', [
                            'jalur_pengiriman' => request('jalur_pengiriman', 'semua'),
                            'tanggal_dari' => request('tanggal_dari'),
                            'tanggal_sampai' => request('tanggal_sampai'),
                        ]) }}"
                        class="btn btn-success btn-block mt-4">
                        <i class="fa fa-download mr-1"></i>
                        Unduh Excel dengan Filter Aktif
                    </a>
                </div>
                <div class="modal-footer">
                    <button type="button" id="resetFilterSuratKeluar"
                        class="btn btn-secondary">Reset Filter</button>
                    <button type="button" id="btnCariFilter"
                        class="btn btn-primary">Terapkan Filter</button>
                </div>
            </div>
        </div>
    </div>
@endsection
