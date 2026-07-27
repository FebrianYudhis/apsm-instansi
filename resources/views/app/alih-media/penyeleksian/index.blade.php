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
                    url: `{{ route('alih-media.penyeleksian') }}`,
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

            $('#datatabel').on("click", ".konfirmasi-proses-alih-media", function(event) {
                const form = $(this).closest("form");
                event.preventDefault();

                MfaCodeInput.prompt({
                    title: 'Proses Alih Media',
                    description: 'PDF asli akan dibaca dan salinan ber-watermark akan dibuat untuk semua isi berkas ini. Tindakan ini tidak bisa dikembalikan.',
                    confirmButtonText: 'Proses',
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.find('input[name="passcode_access"]').remove();
                        form.append(
                            `<input type="hidden" name="passcode_access" value="${$('<div>').text(result.value).html()}">`
                        );
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
            <div class="card-header">
                <h5 class="mb-0">Daftar Penyeleksian Alih Media</h5>
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
