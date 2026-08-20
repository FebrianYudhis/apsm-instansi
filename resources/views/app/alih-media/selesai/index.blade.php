@extends('layouts.main')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/datatables.min.css') }}">
@endpush

@push('js')
    <script src="{{ asset('js/datatables.min.js') }}"></script>
@endpush

@push('js')
    <script>
        $(document).ready(function() {
            $('#datatabel').DataTable({
                scrollX: true,
                autoWidth: false,
                paging: true,
                processing: true,
                serverSide: true,
                ajax: {
                    url: `{{ route('alih-media.selesai') }}`,
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
                        data: 'status_alih_media',
                        name: 'status_alih_media',
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: null,
                        name: 'retensi',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return row.retensi_aktif + " / " + row.retensi_inaktif;
                        }
                    },
                    {
                        data: 'aksi',
                        name: 'aksi',
                        orderable: false,
                        searchable: false
                    },
                ],
            });
        });
    </script>
@endpush

@section('konten')
    <div class="mt-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between">
                    <h5 class="mb-2 mb-sm-0 font-weight-bold text-dark">Daftar Selesai Alih Media</h5>
                    <div class="d-flex flex-wrap align-items-center">
                        <a href="{{ route('alih-media.selesai.export-daftar-arsip') }}" class="btn btn-sm btn-outline-success">
                            <i class="fa fa-download mr-1"></i> Export Daftar Arsip Alih Media
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="datatabel" class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Kode Klasifikasi</th>
                                <th>Nama Berkas</th>
                                <th>Status Berkas</th>
                                <th>Jumlah Isi Berkas</th>
                                <th>Status Alih Media</th>
                                <th>Retensi Aktif / Inaktif</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
