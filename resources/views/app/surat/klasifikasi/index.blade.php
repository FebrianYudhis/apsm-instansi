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
            $('#datatabel').DataTable({
                scrollX: true,
                autoWidth: false,
                paging: true,
                processing: true,
                serverSide: true,
                ajax: `{{ route('surat.klasifikasi') }}`,
                columns: [{
                    data: 'kode_klasifikasi',
                    name: 'kode_klasifikasi',
                },
                {
                    data: 'keterangan',
                    name: 'keterangan'
                },
                {
                    data: 'aksi',
                    name: 'aksi',
                    orderable: false,
                    searchable: false
                },
                ],
                order: [
                    [0, 'asc']
                ]
            });
        });
    </script>

    <script src="{{ asset('js/delete-confirmation.js') }}"></script>
@endpush

@section('konten')
    <div class="mt-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daftar Klasifikasi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="datatabel" class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Kode Klasifikasi</th>
                                <th>Keterangan</th>
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
        'id' => 'klasifikasi-floating-actions',
        'actions' => [
            [
                'url' => route('klasifikasi.tambah'),
                'label' => 'Tambah Data',
                'icon' => 'fa fa-plus',
                'class' => 'btn-primary',
            ],
            [
                'url' => route('surat.klasifikasi.export'),
                'label' => 'Export Klasifikasi ke Excel',
                'icon' => 'fa fa-download',
                'class' => 'btn-success',
            ],
        ],
    ])
@endsection
