@extends('layouts.main')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/datatables.min.css') }}">
    <style>
        .json-viewer {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
@endpush

@push('js')
    <script src="{{ asset('js/datatables.min.js') }}"></script>
@endpush

@push('js')
    <script>
        $(document).ready(function () {
            var table = $('#datatabel').DataTable({
                scrollX: true,
                autoWidth: false,
                paging: true,
                processing: true,
                serverSide: true,
                ajax: {
                    url: `{{ route('activity-log') }}`,
                    data: function (d) {
                        d.user_id = $('#filterPelaku').val();
                        d.subject_type = $('#filterKategori').val();
                        d.event = $('#filterAksi').val();
                        d.bulan = $('#filterBulan').val();
                        d.tahun = $('#filterTahun').val();
                    }
                },
                columns: [
                    { data: 'waktu', name: 'created_at' },
                    { data: 'pelaku', name: 'causer_id', orderable: false, searchable: false },
                    { data: 'description', name: 'description' },
                    { data: 'model', name: 'subject_type', orderable: false, searchable: false },
                    { data: 'perubahan', name: 'properties', orderable: false, searchable: false }
                ],
                order: [[0, 'desc']]
            });

            // Modal untuk JSON Viewer
            $('#datatabel').on('click', '.btn-detail', function () {
                var propertiesStr = $(this).attr('data-properties');
                try {
                    var propertiesObj = JSON.parse(propertiesStr);
                    var formattedJson = JSON.stringify(propertiesObj, null, 4);
                    $('#jsonViewer').text(formattedJson);
                    $('#modalDetail').modal('show');
                } catch (e) {
                    console.error("Error parsing JSON", e);
                    Swal.fire('Error', 'Data perubahan tidak valid.', 'error');
                }
            });

            $('#filterPelaku, #filterKategori, #filterAksi, #filterBulan, #filterTahun').change(function() {
                table.ajax.reload();
            });

            $('#btnResetFilter').click(function() {
                $('#filterPelaku').val('');
                $('#filterKategori').val('');
                $('#filterAksi').val('');
                $('#filterBulan').val('');
                $('#filterTahun').val('');
                table.ajax.reload();
            });
        });
    </script>
@endpush

@section('konten')
    <div class="mt-4">
        <!-- Filter Card -->
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-white py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                <div>
                    <h6 class="mb-0 font-weight-bold text-dark">
                        <i class="fa fa-filter mr-1 text-primary"></i> Filter Log Aktivitas
                    </h6>
                </div>
                <div class="mt-2 mt-md-0">
                    <a href="{{ route('activity-log.ringkasan') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-chart-line mr-1"></i> Buka Ringkasan Aktivitas
                    </a>
                </div>
            </div>
            <div class="card-body bg-light py-3">
                <div class="form-row">
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
                        <label for="filterPelaku" class="small font-weight-bold text-muted mb-1">Pelaku (Pengguna)</label>
                        <select class="form-control form-control-sm custom-select" id="filterPelaku">
                            <option value="">Semua Pelaku</option>
                            <option value="sistem" {{ $filterUserId === 'sistem' ? 'selected' : '' }}>Sistem (Aksi Latar Belakang)</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ (string)$filterUserId === (string)$user->id ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->username }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
                        <label for="filterKategori" class="small font-weight-bold text-muted mb-1">Kategori / Modul</label>
                        <select class="form-control form-control-sm custom-select" id="filterKategori">
                            <option value="">Semua Kategori</option>
                            <option value="incoming" {{ $filterSubjectType === 'incoming' ? 'selected' : '' }}>Surat Masuk</option>
                            <option value="outcoming" {{ $filterSubjectType === 'outcoming' ? 'selected' : '' }}>Surat Keluar</option>
                            <option value="digital" {{ $filterSubjectType === 'digital' ? 'selected' : '' }}>Surat Digital</option>
                            <option value="filelist" {{ $filterSubjectType === 'filelist' ? 'selected' : '' }}>Berkas</option>
                            <option value="classification" {{ $filterSubjectType === 'classification' ? 'selected' : '' }}>Klasifikasi</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                        <label for="filterAksi" class="small font-weight-bold text-muted mb-1">Aksi / Event</label>
                        <select class="form-control form-control-sm custom-select" id="filterAksi">
                            <option value="">Semua Aksi</option>
                            <option value="created" {{ $filterEvent === 'created' ? 'selected' : '' }}>Data Dibuat (Tambah)</option>
                            <option value="updated" {{ $filterEvent === 'updated' ? 'selected' : '' }}>Data Diubah</option>
                            <option value="deleted" {{ $filterEvent === 'deleted' ? 'selected' : '' }}>Data Dihapus</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6 col-sm-6 mb-2">
                        <label for="filterBulan" class="small font-weight-bold text-muted mb-1">Bulan</label>
                        <select class="form-control form-control-sm custom-select" id="filterBulan">
                            <option value="">Semua Bulan</option>
                            @foreach($daftarBulan as $num => $name)
                                <option value="{{ $num }}" {{ (string)$filterBulan === (string)$num ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6 col-sm-6 mb-2">
                        <label for="filterTahun" class="small font-weight-bold text-muted mb-1">Tahun</label>
                        <select class="form-control form-control-sm custom-select" id="filterTahun">
                            <option value="">Semua Tahun</option>
                            @foreach($daftarTahun as $th)
                                <option value="{{ $th }}" {{ (string)$filterTahun === (string)$th ? 'selected' : '' }}>{{ $th }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-1 d-flex justify-content-between align-items-center">
                    <span class="small text-muted">
                        @if($filterSubjectType || $filterEvent || $filterTahun || $filterBulan || $filterUserId)
                            <i class="fa fa-info-circle text-primary mr-1"></i> Filter aktif dari Ringkasan Aktivitas diterapkan.
                        @endif
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetFilter">
                        <i class="fa fa-undo mr-1"></i> Reset Filter
                    </button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold text-dark">Daftar Log Aktivitas Sistem</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="datatabel" class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Waktu Kejadian</th>
                                <th>Pelaku (User)</th>
                                <th>Deskripsi (Aksi)</th>
                                <th>Bagian Data</th>
                                <th>Perubahan Data</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalDetail" tabindex="-1" role="dialog" aria-labelledby="modalDetailLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalDetailLabel">Detail Perubahan Data</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <pre id="jsonViewer" class="json-viewer"></pre>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>
@endsection
