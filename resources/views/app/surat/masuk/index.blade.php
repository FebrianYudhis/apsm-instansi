@extends('layouts.main')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/datatables.min.css') }}">
@endpush

@push('js')
    <script src="{{ asset('js/datatables.min.js') }}"></script>
@endpush

@push('js')
    <script>
        $(document).ready(function () {
            const exportBaseUrl = `{{ route('surat.masuk.export-pencatatan') }}`;

            function buildExportUrl() {
                const activeParams = new URLSearchParams(window.location.search);
                const params = new URLSearchParams();

                params.set(
                    'sumber_surat',
                    activeParams.get('sumber_surat') || 'semua'
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
                $('#btnExportSuratMasuk').attr('href', buildExportUrl());
            }

            function syncFilterToUrl() {
                const params = new URLSearchParams(window.location.search);
                const sumberSurat = $('#filterSumberSurat').val();
                const tanggalDari = $('#filterTanggalDari').val();
                const tanggalSampai = $('#filterTanggalSampai').val();

                params.set('sumber_surat', sumberSurat || 'semua');

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
                        text: 'Tanggal Diterima Sampai tidak boleh lebih awal dari Tanggal Diterima Dari.',
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
                    url: `{{ route('surat.masuk') }}`,
                    data: function (d) {
                        d.sumber_surat = $('#filterSumberSurat').val();
                        d.tanggal_dari = $('#filterTanggalDari').val();
                        d.tanggal_sampai = $('#filterTanggalSampai').val();
                    }
                },
                columns: [{
                    data: 'nomor_agenda',
                    name: 'nomor_agenda',
                    render: function (data, type, row) {
                        return row.is_srikandi == 1 ? 'SRIKANDI' : (data || '-');
                    }
                },
                {
                    data: 'tanggal_diterima',
                    name: 'tanggal_diterima'
                },
                {
                    data: 'nomor_surat',
                    name: 'nomor_surat'
                },
                {
                    data: 'pengirim',
                    name: 'pengirim'
                },
                {
                    data: 'tanggal_surat',
                    name: 'tanggal_surat'
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
                            return `<badge class="badge badge-danger">-</badge>`;
                        } else {
                            return `<badge class="badge badge-info">${row.access.sifat_akses}</badge>`;
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
                        } else {
                            if (row.is_srikandi == 1) {
                                return `<badge class="badge badge-success">SRIKANDI</badge>`;
                            } else {
                                return `<badge class="badge badge-warning">Belum</badge>`;
                            }
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
                    [1, 'desc']
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
                $('#modalFilterSuratMasuk').modal('hide');
            });

            $('#resetFilterSuratMasuk').on('click', function () {
                $('#filterSumberSurat').val('semua');
                $('#filterTanggalDari').val('');
                $('#filterTanggalSampai').val('').attr('min', '');
                syncFilterToUrl();
                updateExportUrl();
                table.ajax.reload();
            });

            updateExportUrl();
        });
    </script>

    <script src="{{ asset('js/delete-confirmation.js') }}"></script>
@endpush

@section('konten')
    <div class="mt-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daftar Surat Masuk</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="datatabel" class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Nomor Agenda</th>
                                <th>Tanggal Diterima</th>
                                <th>Nomor Surat</th>
                                <th>Pengirim</th>
                                <th>Tanggal Surat</th>
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
        'id' => 'surat-masuk-floating-actions',
        'actions' => [
            [
                'url' => route('masuk.tambah'),
                'label' => 'Tambah Data',
                'icon' => 'fa fa-plus',
                'class' => 'btn-primary',
            ],
            [
                'label' => 'Filter dan Export Surat Masuk',
                'icon' => 'fa fa-filter',
                'class' => 'btn-info',
                'attributes' => [
                    'data-toggle' => 'modal',
                    'data-target' => '#modalFilterSuratMasuk',
                ],
            ],
        ],
    ])

    <div class="modal fade" id="modalFilterSuratMasuk" tabindex="-1"
        aria-labelledby="modalFilterSuratMasukLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFilterSuratMasukLabel">
                        Filter dan Export Surat Masuk
                    </h5>
                    <button type="button" class="close" data-dismiss="modal"
                        aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="filterSumberSurat">Sumber Surat</label>
                        <select id="filterSumberSurat" name="sumber_surat"
                            class="form-control">
                            <option value="semua"
                                {{ request('sumber_surat', 'semua') === 'semua' ? 'selected' : '' }}>
                                Semua
                            </option>
                            <option value="srikandi"
                                {{ request('sumber_surat') === 'srikandi' ? 'selected' : '' }}>
                                Dari SRIKANDI
                            </option>
                            <option value="non_srikandi"
                                {{ request('sumber_surat') === 'non_srikandi' ? 'selected' : '' }}>
                                Bukan dari SRIKANDI
                            </option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="filterTanggalDari">Tanggal Diterima Dari</label>
                            <input type="date" id="filterTanggalDari"
                                name="tanggal_dari" class="form-control"
                                value="{{ request('tanggal_dari') }}">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="filterTanggalSampai">Tanggal Diterima Sampai</label>
                            <input type="date" id="filterTanggalSampai"
                                name="tanggal_sampai" class="form-control"
                                value="{{ request('tanggal_sampai') }}">
                        </div>
                    </div>
                    <small class="form-text text-muted">
                        Terapkan filter terlebih dahulu. Daftar dan file Excel
                        menggunakan filter aktif yang sama pada tahun aktif.
                    </small>
                    <a id="btnExportSuratMasuk"
                        href="{{ route('surat.masuk.export-pencatatan', [
                            'sumber_surat' => request('sumber_surat', 'semua'),
                            'tanggal_dari' => request('tanggal_dari'),
                            'tanggal_sampai' => request('tanggal_sampai'),
                        ]) }}"
                        class="btn btn-success btn-block mt-4">
                        <i class="fa fa-download mr-1"></i>
                        Unduh Excel dengan Filter Aktif
                    </a>
                </div>
                <div class="modal-footer">
                    <button type="button" id="resetFilterSuratMasuk"
                        class="btn btn-secondary">Reset Filter</button>
                    <button type="button" id="btnCariFilter"
                        class="btn btn-primary">Terapkan Filter</button>
                </div>
            </div>
        </div>
    </div>
@endsection
