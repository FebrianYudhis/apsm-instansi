@extends('layouts.main')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/datatables.min.css') }}">
    <link href="{{ asset('css/select2.min.css') }}" rel="stylesheet" />
@endpush

@push('js')
    <script src="{{ asset('js/datatables.min.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>
    <script src="{{ asset('js/berkas-buka.min.js') }}"></script>
@endpush

@section('konten')
    @php
        $canMoveItems = (int) $berkas['status_id'] === 1 && is_null($berkas['alih_media_status_id']);
        $hasEditErrors = $errors->has('kodeKlasifikasi') || $errors->has('namaBerkas') || $errors->has('retensiAktif') || $errors->has('retensiInaktif') || $errors->has('keteranganAkhir');
        $hasAttachErrors = $errors->has('items') || $errors->has('items.*');
    @endphp
    <div id="berkasBukaPage"
        class="mt-4"
        data-filelist-id="{{ $berkas['id'] }}"
        data-active-filelists-url="{{ route('berkas.aktif.list') }}"
        data-pending-letters-url="{{ route('surat.belum-diberkaskan') }}"
        data-open-edit-modal="{{ $hasEditErrors ? '1' : '0' }}"
        data-open-attach-modal="{{ $hasAttachErrors ? '1' : '0' }}">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <span>Daftar Isi Berkas ({{ $berkas['nama_berkas'] }})</span>
                    @if (!$berkas->isAlihMediaLocked())
                        <button type="button" id="btnOpenEditBerkasModal" class="btn btn-primary">
                            Edit Berkas
                        </button>
                    @else
                        <button type="button" class="btn btn-secondary" disabled title="Terkunci karena alih media">
                            <i class="fa fa-lock"></i> Berkas Terkunci
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Kurun Waktu:</strong> {{ $kurunWaktu }}
                </div>

                @if ($canMoveItems)
                    <div class="mb-3 d-flex flex-wrap align-items-center">
                        <button type="button" id="btnOpenAttachModal"
                            class="btn btn-success mr-2 mb-2 d-inline-flex align-items-center justify-content-center">
                            <i class="fa fa-paperclip mr-1"></i> Lampirkan Surat
                        </button>
                        <button type="button" id="btnOpenBulkModal"
                            class="btn btn-primary mr-2 mb-2 d-inline-flex align-items-center justify-content-center"
                            disabled>
                            <i class="fa fa-exchange-alt mr-1"></i> Pindahkan Terpilih
                        </button>
                        <span>Dipilih: <strong id="bulkSelectedCount">0</strong></span>
                    </div>
                @endif
                <div class="table-responsive">
                    <table id="datatabel" class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                @if ($canMoveItems)
                                    <th>
                                        <input type="checkbox" id="bulkSelectAll">
                                    </th>
                                @endif
                                <th>Uraian Informasi Arsip</th>
                                <th>Nomor Naskah</th>
                                <th>Tanggal Item</th>
                                <th>SKKAD</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $item)
                                <tr>
                                    @if ($canMoveItems)
                                        <td>
                                            @if (!$item['is_locked'])
                                                <input type="checkbox" class="bulk-item" value="{{ $item['jenis'] . ':' . $item['id'] }}">
                                            @else
                                                <span class="text-muted" title="Terkunci karena alih media">
                                                    <i class="fa fa-lock"></i>
                                                </span>
                                            @endif
                                        </td>
                                    @endif
                                    <td>{{ $item['uraian'] }}</td>
                                    <td>{{ $item['nomor_naskah'] }}</td>
                                    <td>{{ $item['tanggal_item'] }}</td>
                                    <td>{{ $item['skkad'] }}</td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <a href="{{ route('surat.detailItem', [$item['jenis'], $item['id']]) }}"
                                                class="btn btn-info btn-sm{{ $canMoveItems && !$item['is_locked'] ? ' mr-1' : '' }}"
                                                target="_blank"
                                                rel="noopener noreferrer" title="Lihat Detail">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            @if ($canMoveItems && !$item['is_locked'])
                                                <form method="POST"
                                                    action="{{ route('berkas.keluarkan', [$berkas['id'], $item['jenis'], $item['id']]) }}"
                                                    class="m-0 detach-letter-form"
                                                    data-letter-number="{{ $item['nomor_naskah'] }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-sm"
                                                        title="Keluarkan dari Berkas" aria-label="Keluarkan dari Berkas">
                                                        <i class="fa fa-unlink"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($canMoveItems)
        <div class="modal fade" id="modalLampirkanSurat" tabindex="-1" aria-labelledby="modalLampirkanSuratLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <form id="formLampirkanSurat" method="POST" action="{{ route('berkas.lampirkanBulk', $berkas['id']) }}" autocomplete="off">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLampirkanSuratLabel">Lampirkan Surat ke Berkas</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            @if ($hasAttachErrors)
                                <div class="alert alert-danger">
                                    Data lampiran belum valid. Pilih kembali surat yang akan dilampirkan.
                                </div>
                            @endif

                            <ul class="nav nav-tabs mb-3" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="attachSearchTab" data-toggle="tab" href="#attachSearchPane"
                                        role="tab" aria-controls="attachSearchPane" aria-selected="true">
                                        <i class="fa fa-search mr-1"></i> Cari Surat
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="attachSelectedTab" data-toggle="tab" href="#attachSelectedPane"
                                        role="tab" aria-controls="attachSelectedPane" aria-selected="false">
                                        <i class="fa fa-paperclip mr-1"></i> Surat Dipilih
                                        <span class="badge badge-primary ml-1" id="attachSelectedMenuCount">0</span>
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="attachSearchPane" role="tabpanel" aria-labelledby="attachSearchTab">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="attachJenis">Jenis Surat</label>
                                                <select class="form-control" id="attachJenis">
                                                    <option value="">- Pilih Jenis Surat -</option>
                                                    <option value="masuk">Surat Masuk</option>
                                                    <option value="keluar">Surat Keluar</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="attachTahun">Tahun</label>
                                                <select class="form-control" id="attachTahun">
                                                    <option value="">- Pilih Tahun -</option>
                                                    @foreach ($years as $year)
                                                        <option value="{{ $year }}">{{ $year }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="attachFilterHint" class="alert alert-info mb-0">
                                        Pilih jenis surat dan tahun. Pilihan sebelumnya tetap tersimpan saat filter diganti.
                                    </div>

                                    <div id="attachTableContainer" class="d-none">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Dipilih sementara: <strong id="attachSelectedCount">0</strong> surat</span>
                                            <span class="text-muted small">Pilih semua hanya berlaku untuk halaman tabel yang terlihat.</span>
                                        </div>
                                        <div class="table-responsive">
                                            <table id="attachLettersTable" class="table table-bordered table-striped w-100">
                                                <thead class="thead-dark">
                                                    <tr>
                                                        <th><input type="checkbox" id="attachSelectAll" aria-label="Pilih semua surat pada halaman ini"></th>
                                                        <th>Tanggal Surat</th>
                                                        <th>Nomor Surat</th>
                                                        <th>Pengirim / Tujuan</th>
                                                        <th>Perihal</th>
                                                        <th class="text-center">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="attachSelectedPane" role="tabpanel" aria-labelledby="attachSelectedTab">
                                    <div id="attachSelectedEmpty" class="alert alert-secondary mb-0">
                                        Belum ada surat yang dipilih. Pilih surat dari menu Cari Surat.
                                    </div>
                                    <div id="attachSelectedTableContainer" class="d-none">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>Daftar sementara surat yang akan dilampirkan</strong>
                                            <button type="button" id="btnClearAttachSelection" class="btn btn-sm btn-outline-danger">
                                                <i class="fa fa-times mr-1"></i> Bersihkan Pilihan
                                            </button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped">
                                                <thead class="thead-dark">
                                                    <tr>
                                                        <th>Tanggal Surat</th>
                                                        <th>Nomor Surat</th>
                                                        <th>Pengirim / Tujuan</th>
                                                        <th>Perihal</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="attachSelectedTableBody"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="attachSelectedContainer"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" id="btnAttachSelected" class="btn btn-success" disabled>
                                Lampirkan Surat Terpilih
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade" id="modalBulkPindah" tabindex="-1" aria-labelledby="modalBulkPindahLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formBulkPindah" method="POST" action="{{ route('berkas.gantiLokasiBulk') }}">
                    @csrf
                    <input type="hidden" name="berkas_asal" value="{{ $berkas['id'] }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalBulkPindahLabel">Pindahkan Surat Terpilih</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">
                            Jumlah surat dipilih: <strong id="bulkSelectedCountModal">0</strong>
                        </p>

                        <div class="form-group">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="modalPemberkasanBulk" class="mb-0">Pemberkasan Tujuan</label>
                                <a href="{{ route('berkas.tambah') }}" target="_blank" rel="noopener noreferrer"
                                    class="btn btn-sm btn-outline-primary">
                                    Tambah Berkas Baru
                                </a>
                            </div>
                            <select class="form-control" id="modalPemberkasanBulk" name="pemberkasan" required>
                                <option value="">- Pilih Pemberkasan -</option>
                            </select>
                        </div>

                        <div id="bulkSelectedContainer"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditBerkas" tabindex="-1" aria-labelledby="modalEditBerkasLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('berkas.edit', $berkas['id']) }}" autocomplete="off">
                    @csrf
                    <input type="hidden" name="redirect_back" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEditBerkasLabel">Edit Berkas</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="modalKodeKlasifikasi">Kode Klasifikasi</label>
                            <select class="form-control" id="modalKodeKlasifikasi" name="kodeKlasifikasi" required>
                                @foreach ($classification as $itemKlasifikasi)
                                    <option value="{{ $itemKlasifikasi->id }}"
                                        {{ (string) old('kodeKlasifikasi', $berkas['classification_id']) === (string) $itemKlasifikasi->id ? 'selected' : '' }}>
                                        {{ $itemKlasifikasi->kode_klasifikasi }}
                                    </option>
                                @endforeach
                            </select>
                            @error('kodeKlasifikasi')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="modalNamaBerkas">Nama Berkas</label>
                            <input type="text"
                                class="form-control"
                                id="modalNamaBerkas"
                                name="namaBerkas"
                                value="{{ old('namaBerkas', $berkas['nama_berkas']) }}"
                                placeholder="Masukkan Nama Berkas"
                                required>
                            @error('namaBerkas')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="modalRetensiAktif">Retensi Aktif (Satuan Tahun)</label>
                            <input type="number"
                                class="form-control"
                                id="modalRetensiAktif"
                                name="retensiAktif"
                                value="{{ old('retensiAktif', $berkas['retensi_aktif']) }}"
                                placeholder="Masukkan Retensi Aktif"
                                required>
                            @error('retensiAktif')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="modalRetensiInaktif">Retensi Inaktif (Satuan Tahun)</label>
                            <input type="number"
                                class="form-control"
                                id="modalRetensiInaktif"
                                name="retensiInaktif"
                                value="{{ old('retensiInaktif', $berkas['retensi_inaktif']) }}"
                                placeholder="Masukkan Retensi Inaktif"
                                required>
                            @error('retensiInaktif')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group mb-0">
                            <label for="modalKeteranganAkhir">Keterangan Akhir</label>
                            <select class="form-control" id="modalKeteranganAkhir" name="keteranganAkhir" required>
                                <option value="Permanen" {{ old('keteranganAkhir', $berkas['keterangan_akhir'] ?? 'Permanen') == 'Permanen' ? 'selected' : '' }}>Permanen</option>
                                <option value="Musnah" {{ old('keteranganAkhir', $berkas['keterangan_akhir'] ?? 'Permanen') == 'Musnah' ? 'selected' : '' }}>Musnah</option>
                            </select>
                            @error('keteranganAkhir')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
