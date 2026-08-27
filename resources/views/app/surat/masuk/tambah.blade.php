@extends('layouts.main')

@section('konten')
    <div class="card">
        <div class="card-header">
            <h3>Tambah Data</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('masuk.tambah') }}" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="form-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="isSrikandi"
                            name="isSrikandi" {{ old('isSrikandi') ? 'checked' : '' }}>
                        <label class="form-check-label" for="isSrikandi">
                            Dari SRIKANDI ?
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="nomorAgenda">Nomor Agenda</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="nomorAgenda" placeholder="Masukkan Nomor Agenda"
                            name="nomorAgenda" value="{{ old('nomorAgenda') }}" min="1" REQUIRED>
                        <div class="input-group-append" id="agendaCheckingSpinner" style="display: none;">
                            <span class="input-group-text bg-white">
                                <i class="fa fa-spinner fa-spin text-primary"></i>
                            </span>
                        </div>
                    </div>
                    <div id="agendaFeedback" class="mt-2" style="display: none;"></div>
                    @error('nomorAgenda')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="tanggalDiterima">Tanggal Diterima</label>
                    <input type="date" class="form-control" id="tanggalDiterima" name="tanggalDiterima"
                        value="{{ old('tanggalDiterima') }}" REQUIRED>
                    @error('tanggalDiterima')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="nomorSurat">Nomor Surat</label>
                    <input type="text" class="form-control" id="nomorSurat" placeholder="Masukkan Nomor Surat"
                        name="nomorSurat" value="{{ old('nomorSurat') }}" REQUIRED>
                    @error('nomorSurat')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="pengirim">Pengirim</label>
                    <input type="text" class="form-control" id="pengirim" placeholder="Masukkan Pengirim" name="pengirim"
                        value="{{ old('pengirim') }}" REQUIRED>
                    @error('pengirim')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="tanggalSurat">Tanggal Surat</label>
                    <input type="date" class="form-control" id="tanggalSurat" name="tanggalSurat"
                        value="{{ old('tanggalSurat') }}">
                </div>
                <div class="form-group">
                    <label for="perihal">Perihal</label>
                    <input type="text" class="form-control" id="perihal" placeholder="Masukkan Perihal" name="perihal"
                        value="{{ old('perihal') }}" REQUIRED>
                    @error('perihal')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="sifat">Sifat Naskah</label>
                    <select class="form-control" id="sifat" name="sifat">
                        @foreach ($access as $item)
                            <option value="{{ $item->id }}">{{ $item->sifat_akses }}</option>
                        @endforeach
                    </select>
                    @error('sifat')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="pemberkasan">Pemberkasan</label>
                    <select class="form-control" id="pemberkasan" name="pemberkasan">
                        <option value="null" {{ old('pemberkasan', 'null') === 'null' ? 'selected' : '' }}>-Kosongkan-</option>
                        @foreach ($filelist->sortBy(['classification.kode_klasifikasi', 'nama_berkas'])->groupBy(fn ($item) => $item->classification->kode_klasifikasi . ' - ' . ($item->classification->keterangan ?? 'Tanpa Keterangan')) as $namaKlasifikasi => $berkasList)
                            <optgroup label="{{ $namaKlasifikasi }}">
                                @foreach ($berkasList as $item)
                                    <option value="{{ $item->id }}" {{ old('pemberkasan') == $item->id ? 'selected' : '' }}>
                                        {{ $item->nama_berkas }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('pemberkasan')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="berkas">Berkas (PDF)</label>
                    <input type="file" class="form-control-file" id="berkas" accept="application/pdf" name="berkas"
                        REQUIRED>
                    @error('berkas')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">Tambah</button>
            </form>
        </div>
    </div>
@endsection

@push('css')
    <link href="{{ asset('css/select2.min.css') }}" rel="stylesheet" />
@endpush

@push('js')
    <script src="{{ asset('js/select2.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            function matchOptgroup(params, data) {
                if ($.trim(params.term) === '') {
                    return data;
                }
                if (typeof data.text === 'undefined') {
                    return null;
                }
                var term = params.term.toUpperCase();
                var text = data.text.toUpperCase();
                if (data.children && data.children.length > 0) {
                    if (text.indexOf(term) > -1) {
                        return data;
                    }
                    var matchedChildren = [];
                    for (var i = 0; i < data.children.length; i++) {
                        var child = data.children[i];
                        if (child.text && child.text.toUpperCase().indexOf(term) > -1) {
                            matchedChildren.push(child);
                        }
                    }
                    if (matchedChildren.length > 0) {
                        var modifiedData = $.extend({}, data, true);
                        modifiedData.children = matchedChildren;
                        return modifiedData;
                    }
                    return null;
                }
                if (text.indexOf(term) > -1) {
                    return data;
                }
                return null;
            }

            function syncSrikandiFields() {
                if ($('#isSrikandi').is(':checked')) {
                    $('#nomorAgenda').closest('.form-group').hide();
                    $('#pemberkasan').closest('.form-group').hide();
                    $('#nomorAgenda').val('').prop('required', false);
                    $('#agendaFeedback').hide().empty();
                    $('#agendaCheckingSpinner').hide();
                    $('#pemberkasan').val($('#pemberkasan option:first').val()).trigger('change');
                } else {
                    $('#nomorAgenda').closest('.form-group').show();
                    $('#pemberkasan').closest('.form-group').show();
                    $('#nomorAgenda').prop('required', true);
                    if ($('#nomorAgenda').val()) {
                        checkNomorAgenda($('#nomorAgenda').val());
                    }
                }
            }

            var agendaCheckTimeout = null;

            function escapeHtml(str) {
                if (str === null || str === undefined) return '-';
                return $('<div>').text(str).html();
            }

            function checkNomorAgenda(nomor) {
                if ($('#isSrikandi').is(':checked')) {
                    $('#agendaFeedback').hide().empty();
                    $('#agendaCheckingSpinner').hide();
                    return;
                }

                nomor = $.trim(nomor);
                if (!nomor || parseInt(nomor, 10) < 1) {
                    $('#agendaFeedback').hide().empty();
                    $('#agendaCheckingSpinner').hide();
                    return;
                }

                $('#agendaCheckingSpinner').show();

                $.ajax({
                    url: `{{ route('masuk.cek-agenda') }}`,
                    type: 'GET',
                    data: {
                        nomor_agenda: nomor
                    },
                    success: function (res) {
                        $('#agendaCheckingSpinner').hide();
                        if (res.available) {
                            $('#agendaFeedback')
                                .html('<div class="alert alert-success py-2 px-3 mb-0 small d-flex align-items-center"><i class="fa fa-check-circle mr-2 text-success"></i> <span>' + escapeHtml(res.message) + '</span></div>')
                                .show();
                        } else {
                            var d = res.data;
                            var html = '<div class="alert alert-warning py-2 px-3 mb-0 small shadow-sm border">';
                            html += '<div class="font-weight-bold text-dark mb-1"><i class="fa fa-exclamation-triangle text-warning mr-1"></i> ' + escapeHtml(res.message) + '</div>';
                            if (d) {
                                html += '<div class="text-muted mb-2">';
                                html += '<div><strong>No. Surat:</strong> ' + escapeHtml(d.nomor_surat) + ' | <strong>Tgl Diterima:</strong> ' + escapeHtml(d.tanggal_diterima) + '</div>';
                                html += '<div><strong>Pengirim:</strong> ' + escapeHtml(d.pengirim) + '</div>';
                                html += '<div><strong>Perihal:</strong> ' + escapeHtml(d.perihal) + '</div>';
                                if (d.is_deleted) {
                                    html += '<div class="text-danger mt-1"><i class="fa fa-trash-alt mr-1"></i> <em>Status: Berada di tong sampah (soft-deleted).</em></div>';
                                }
                                html += '</div>';
                                if (d.detail_url) {
                                    html += '<a href="' + d.detail_url + '" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-primary"><i class="fa fa-eye mr-1"></i> Lihat Data Surat Ini (Tab Baru)</a>';
                                }
                            }
                            html += '</div>';
                            $('#agendaFeedback').html(html).show();
                        }
                    },
                    error: function () {
                        $('#agendaCheckingSpinner').hide();
                    }
                });
            }

            $('#nomorAgenda').on('input change', function () {
                var val = $(this).val();
                clearTimeout(agendaCheckTimeout);
                agendaCheckTimeout = setTimeout(function () {
                    checkNomorAgenda(val);
                }, 350);
            });

            $('#pemberkasan').select2({
                matcher: matchOptgroup,
                width: '100%'
            });
            $('#isSrikandi').on('change', syncSrikandiFields);
            syncSrikandiFields();
        });
    </script>
@endpush
