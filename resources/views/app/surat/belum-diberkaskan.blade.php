@extends('layouts.main')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/datatables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/select2.min.css') }}">
@endpush

@push('js')
    <script src="{{ asset('js/datatables.min.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>
    <script src="{{ asset('js/direct-filing.min.js') }}"></script>
    <script src="{{ asset('js/belum-diberkaskan.min.js') }}"></script>
@endpush

@section('konten')
    <div
        id="pendingFilingPage"
        class="mt-4"
        data-list-url="{{ route('surat.belum-diberkaskan') }}"
    >
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Surat Belum Diberkaskan</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    Menampilkan surat masuk dan surat keluar non-SRIKANDI pada tahun aktif
                    yang belum ditempatkan ke dalam berkas.
                    Total: <strong id="totalSuratBelumDiberkaskan">0</strong>
                </div>
                <div class="table-responsive">
                    <table id="datatabel" class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Jenis</th>
                                <th>Tanggal Pencatatan</th>
                                <th>Tanggal Surat</th>
                                <th>Nomor Surat</th>
                                <th>Pengirim / Tujuan</th>
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

    @include('components.direct-filing-modal')
@endsection
