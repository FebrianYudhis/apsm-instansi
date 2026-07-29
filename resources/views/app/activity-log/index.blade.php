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
            $('#datatabel').DataTable({
                scrollX: true,
                autoWidth: false,
                paging: true,
                processing: true,
                serverSide: true,
                ajax: {
                    url: `{{ route('activity-log') }}`,
                    data: function (d) {
                        d.user_id = $('#filterPelaku').val();
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

            $('#filterPelaku').change(function() {
                $('#datatabel').DataTable().ajax.reload();
            });
        });
    </script>
@endpush

@section('konten')
    <div class="mt-4">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label for="filterPelaku">Filter Berdasarkan Pelaku</label>
                        <select class="form-control" id="filterPelaku">
                            <option value="">Semua Pelaku</option>
                            <option value="sistem">Sistem (Aksi Latar Belakang)</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daftar Log Aktivitas Sistem</h5>
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
