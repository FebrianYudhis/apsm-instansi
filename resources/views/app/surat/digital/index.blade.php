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
                ajax: '{{ route('surat.digital') }}',
                columns: [
                    { data: 'perihal', name: 'perihal' },
                    { data: 'aksi', name: 'aksi', orderable: false, searchable: false },
                ],
                columnDefs: [
                    { width: '85%', targets: 0 }
                ],
                order: [[0, 'asc']]
            });
        });
    </script>

    <script src="{{ asset('js/delete-confirmation.min.js') }}"></script>
@endpush

@section('konten')
    <div class="mt-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between">
                    <h5 class="mb-2 mb-sm-0 font-weight-bold text-dark">Daftar Surat Digital</h5>
                    <div class="d-flex flex-wrap align-items-center">
                        <a href="{{ route('digital.tambah') }}" class="btn btn-sm btn-primary">
                            <i class="fa fa-plus mr-1"></i> Tambah Data
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="datatabel" class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Perihal</th>
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
