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
            const table = $('#datatabel').DataTable({
                scrollX: true,
                autoWidth: false,
                paging: true,
                processing: true,
                serverSide: true,
                ajax: {
                    url: `{{ route('alih-media.diproses') }}`,
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

            $('.konfirmasi-tutup-semua-alih-media').on("click", function(event) {
                const form = $(this).closest("form");
                event.preventDefault();

                Swal.fire({
                    title: 'Tutup Semua Proses Alih Media?',
                    text: 'Sistem akan memeriksa semua data pada daftar pemrosesan. Data hanya dipindahkan ke Selesai jika seluruh proses sudah berhasil dan PDF watermark lengkap.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Tutup Semua',
                    cancelButtonText: 'Batal',
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

        });
    </script>
@endpush

@section('konten')
    <div class="mt-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between">
                    <h5 class="mb-2 mb-sm-0 font-weight-bold text-dark">Daftar Pemrosesan Alih Media</h5>
                    <div class="d-flex flex-wrap align-items-center">
                        <a href="{{ route('alih-media.diproses.export-daftar-arsip') }}" class="btn btn-sm btn-outline-success mr-2">
                            <i class="fa fa-download mr-1"></i> Daftar Arsip Alih Media
                        </a>
                        <form action="{{ route('alih-media.diproses.tutup-semua') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary konfirmasi-tutup-semua-alih-media">
                                <i class="fa fa-check mr-1"></i> Tutup Semua Proses
                            </button>
                        </form>
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
