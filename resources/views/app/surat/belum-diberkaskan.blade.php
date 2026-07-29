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
            const table = $('#datatabel').DataTable({
                scrollX: true,
                autoWidth: false,
                paging: true,
                processing: true,
                serverSide: true,
                ajax: {
                    url: `{{ route('surat.belum-diberkaskan') }}`,
                },
                columns: [
                    {
                        data: 'jenis',
                        name: 'jenis',
                        render: function (data) {
                            const badgeClass = data === 'masuk' ? 'badge-info' : 'badge-success';
                            const label = data === 'masuk' ? 'Surat Masuk' : 'Surat Keluar';

                            return `<span class="badge ${badgeClass}">${label}</span>`;
                        },
                    },
                    {
                        data: 'tanggal_pencatatan',
                        name: 'tanggal_pencatatan',
                    },
                    {
                        data: 'tanggal_surat',
                        name: 'tanggal_surat',
                    },
                    {
                        data: 'nomor_surat',
                        name: 'nomor_surat',
                    },
                    {
                        data: 'pihak',
                        name: 'pihak',
                    },
                    {
                        data: 'perihal',
                        name: 'perihal',
                    },
                    {
                        data: 'aksi',
                        name: 'aksi',
                        orderable: false,
                        searchable: false,
                    },
                ],
                order: [[1, 'desc']],
            });

            table.on('xhr.dt', function (event, settings, json) {
                if (!json) {
                    return;
                }

                $('#totalSuratBelumDiberkaskan').text(json.recordsFiltered ?? 0);
            });
        });
    </script>
@endpush

@section('konten')
    <div class="mt-4">
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
@endsection
