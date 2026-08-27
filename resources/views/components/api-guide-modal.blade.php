<div class="modal fade" id="apiGuideModal" tabindex="-1" role="dialog"
    aria-labelledby="apiGuideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h4 class="modal-title" id="apiGuideModalLabel">Panduan Pengisian API Surat</h4>
                    <p class="text-muted mb-0">
                        Pilih jenis surat, lalu pilih sumber SRIKANDI atau non-SRIKANDI.
                    </p>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info">
                    Semua request menggunakan <code>multipart/form-data</code>. Nilai boolean dikirim sebagai
                    <code>1</code> atau <code>0</code>, tanggal menggunakan <code>YYYY-MM-DD</code>,
                    dan user penanggung jawab otomatis diambil dari Bearer Token.
                </div>

                <div class="accordion" id="apiGuideLetterTypes">
                    <div class="card">
                        <div class="card-header" id="apiGuideIncomingHeading">
                            <button class="btn btn-link btn-block text-left collapsed" type="button"
                                data-toggle="collapse" data-target="#apiGuideIncoming"
                                aria-expanded="false" aria-controls="apiGuideIncoming">
                                <i class="fa fa-inbox mr-2"></i>
                                <strong>Surat Masuk</strong>
                                <span class="text-muted ml-2">Pilih SRIKANDI atau non-SRIKANDI</span>
                            </button>
                        </div>

                        <div id="apiGuideIncoming" class="collapse"
                            aria-labelledby="apiGuideIncomingHeading" data-parent="#apiGuideLetterTypes">
                            <div class="card-body">
                                <p>
                                    Endpoint: <code>POST {{ route('api.v1.surat.masuk.store') }}</code>
                                </p>

                                <div class="accordion" id="apiGuideIncomingScenarios">
                                    <div class="card">
                                        <div class="card-header" id="apiGuideIncomingSrikandiHeading">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button"
                                                data-toggle="collapse" data-target="#apiGuideIncomingSrikandi"
                                                aria-expanded="false" aria-controls="apiGuideIncomingSrikandi">
                                                Surat Masuk SRIKANDI
                                                <span class="badge badge-primary ml-2">is_srikandi = 1</span>
                                            </button>
                                        </div>
                                        <div id="apiGuideIncomingSrikandi" class="collapse"
                                            aria-labelledby="apiGuideIncomingSrikandiHeading"
                                            data-parent="#apiGuideIncomingScenarios">
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Field</th>
                                                                <th>Wajib</th>
                                                                <th>Tipe / contoh</th>
                                                                <th>Panduan</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><code>is_srikandi</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>1</code></td>
                                                                <td>Menandai surat berasal dari SRIKANDI.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>nomor_agenda</code></td>
                                                                <td>Tidak</td>
                                                                <td>—</td>
                                                                <td>Jangan dikirim; request ditolak jika field ini berisi nilai.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tanggal_diterima</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>2026-07-29</code></td>
                                                                <td>Tanggal surat diterima instansi.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tanggal_surat</code></td>
                                                                <td>Tidak</td>
                                                                <td><code>2026-07-28</code></td>
                                                                <td>Boleh dikosongkan jika tidak tersedia.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>nomor_surat</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>SRK/M/001/2026</code></td>
                                                                <td>Nomor surat, maksimal 255 karakter.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>pengirim</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>Sekretariat Utama</code></td>
                                                                <td>Nama instansi atau pihak pengirim.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>perihal</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>Undangan rapat</code></td>
                                                                <td>Ringkasan perihal surat.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tahun</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>{{ now()->year }}</code></td>
                                                                <td>
                                                                    Rentang {{ config('app.start_year', 2025) }}–{{ now()->year }}.
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>access_id</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>1</code></td>
                                                                <td>Ambil ID dari endpoint referensi sifat akses.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>filelist_id</code></td>
                                                                <td>Tidak</td>
                                                                <td>—</td>
                                                                <td>Jangan dikirim; request ditolak jika field ini berisi nilai.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>berkas</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>surat.pdf</code></td>
                                                                <td>
                                                                    PDF valid, maksimal
                                                                    {{ (int) ceil(config('documents.max_upload_kb') / 1024) }} MB.
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card">
                                        <div class="card-header" id="apiGuideIncomingManualHeading">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button"
                                                data-toggle="collapse" data-target="#apiGuideIncomingManual"
                                                aria-expanded="false" aria-controls="apiGuideIncomingManual">
                                                Surat Masuk Non-SRIKANDI
                                                <span class="badge badge-secondary ml-2">is_srikandi = 0</span>
                                            </button>
                                        </div>
                                        <div id="apiGuideIncomingManual" class="collapse"
                                            aria-labelledby="apiGuideIncomingManualHeading"
                                            data-parent="#apiGuideIncomingScenarios">
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Field</th>
                                                                <th>Wajib</th>
                                                                <th>Tipe / contoh</th>
                                                                <th>Panduan</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><code>is_srikandi</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>0</code></td>
                                                                <td>Menandai pencatatan dilakukan di luar SRIKANDI.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>nomor_agenda</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>125</code></td>
                                                                <td>
                                                                    Integer minimal 1 dan unik dalam tahun yang sama. Dapat dicek terlebih dahulu via endpoint pengecekan agenda.
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tanggal_diterima</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>2026-07-29</code></td>
                                                                <td>Tanggal surat diterima instansi.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tanggal_surat</code></td>
                                                                <td>Tidak</td>
                                                                <td><code>2026-07-28</code></td>
                                                                <td>Boleh dikosongkan jika tidak tersedia.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>nomor_surat</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>BMKG/123/2026</code></td>
                                                                <td>Nomor surat, maksimal 255 karakter.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>pengirim</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>Kantor Pusat BMKG</code></td>
                                                                <td>Nama instansi atau pihak pengirim.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>perihal</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>Permintaan data</code></td>
                                                                <td>Ringkasan perihal surat.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tahun</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>{{ now()->year }}</code></td>
                                                                <td>
                                                                    Rentang {{ config('app.start_year', 2025) }}–{{ now()->year }}.
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>access_id</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>1</code></td>
                                                                <td>Ambil ID dari endpoint referensi sifat akses.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>filelist_id</code></td>
                                                                <td>Tidak</td>
                                                                <td><code>12</code></td>
                                                                <td>
                                                                    Boleh kosong. Jika diisi, gunakan berkas aktif yang belum
                                                                    masuk alih media.
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>berkas</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>surat.pdf</code></td>
                                                                <td>
                                                                    PDF valid, maksimal
                                                                    {{ (int) ceil(config('documents.max_upload_kb') / 1024) }} MB.
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card">
                                        <div class="card-header" id="apiGuideIncomingCekAgendaHeading">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button"
                                                data-toggle="collapse" data-target="#apiGuideIncomingCekAgenda"
                                                aria-expanded="false" aria-controls="apiGuideIncomingCekAgenda">
                                                <i class="fa fa-search mr-1 text-info"></i>
                                                Pengecekan Ketersediaan Nomor Agenda
                                                <span class="badge badge-info ml-2">GET cek-agenda</span>
                                            </button>
                                        </div>
                                        <div id="apiGuideIncomingCekAgenda" class="collapse"
                                            aria-labelledby="apiGuideIncomingCekAgendaHeading"
                                            data-parent="#apiGuideIncomingScenarios">
                                            <div class="card-body">
                                                <p class="mb-2 small text-muted">
                                                    Gunakan endpoint ini untuk memastikan nomor agenda belum terpakai pada tahun yang bersangkutan sebelum mengirim data surat masuk non-SRIKANDI.
                                                </p>
                                                <p class="mb-3">
                                                    Endpoint: <code>GET {{ route('api.v1.surat.masuk.cek-agenda') }}</code>
                                                </p>
                                                <div class="table-responsive mb-3">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Parameter (Query)</th>
                                                                <th>Wajib</th>
                                                                <th>Tipe / Contoh</th>
                                                                <th>Panduan</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><code>nomor_agenda</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>15</code></td>
                                                                <td>Nomor agenda yang ingin dicek ketersediaannya (integer minimal 1).</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tahun</code></td>
                                                                <td>Tidak</td>
                                                                <td><code>{{ now()->year }}</code></td>
                                                                <td>Tahun arsip surat (default: tahun berjalan). Rentang {{ config('app.start_year', 2025) }}–{{ now()->year }}.</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="card bg-light border">
                                                    <div class="card-body p-3">
                                                        <strong class="d-block mb-1 small text-dark">Contoh Response (Tersedia):</strong>
                                                        <pre class="bg-dark text-white p-2 rounded small mb-2"><code>{
  "available": true,
  "message": "Nomor agenda 15 tersedia untuk tahun 2026."
}</code></pre>
                                                        <strong class="d-block mb-1 small text-dark">Contoh Response (Sudah Digunakan):</strong>
                                                        <pre class="bg-dark text-white p-2 rounded small mb-0"><code>{
  "available": false,
  "message": "Nomor agenda 15 sudah digunakan pada tahun 2026.",
  "data": {
    "id": 1,
    "nomor_agenda": 15,
    "nomor_surat": "001/BMKG/VIII/2026",
    "pengirim": "Stasiun Meteorologi",
    "perihal": "Laporan Iklim Bulanan",
    "tanggal_surat": "10/08/2026",
    "tanggal_diterima": "12/08/2026",
    "is_deleted": false
  }
}</code></pre>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header" id="apiGuideOutgoingHeading">
                            <button class="btn btn-link btn-block text-left collapsed" type="button"
                                data-toggle="collapse" data-target="#apiGuideOutgoing"
                                aria-expanded="false" aria-controls="apiGuideOutgoing">
                                <i class="fa fa-paper-plane mr-2"></i>
                                <strong>Surat Keluar</strong>
                                <span class="text-muted ml-2">Pilih SRIKANDI atau non-SRIKANDI</span>
                            </button>
                        </div>

                        <div id="apiGuideOutgoing" class="collapse"
                            aria-labelledby="apiGuideOutgoingHeading" data-parent="#apiGuideLetterTypes">
                            <div class="card-body">
                                <p>
                                    Endpoint: <code>POST {{ route('api.v1.surat.keluar.store') }}</code>
                                </p>

                                <div class="accordion" id="apiGuideOutgoingScenarios">
                                    <div class="card">
                                        <div class="card-header" id="apiGuideOutgoingSrikandiHeading">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button"
                                                data-toggle="collapse" data-target="#apiGuideOutgoingSrikandi"
                                                aria-expanded="false" aria-controls="apiGuideOutgoingSrikandi">
                                                Surat Keluar SRIKANDI
                                                <span class="badge badge-primary ml-2">is_srikandi = 1</span>
                                            </button>
                                        </div>
                                        <div id="apiGuideOutgoingSrikandi" class="collapse"
                                            aria-labelledby="apiGuideOutgoingSrikandiHeading"
                                            data-parent="#apiGuideOutgoingScenarios">
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Field</th>
                                                                <th>Wajib</th>
                                                                <th>Tipe / contoh</th>
                                                                <th>Panduan</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><code>is_srikandi</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>1</code></td>
                                                                <td>Menandai surat dibuat melalui SRIKANDI.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>is_digital</code></td>
                                                                <td>Tidak</td>
                                                                <td>—</td>
                                                                <td>Jangan dikirim; request ditolak jika field ini berisi nilai dan sistem otomatis menyimpan <code>true</code>.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tanggal_surat</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>2026-07-29</code></td>
                                                                <td>Tanggal yang tercantum pada surat.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>nomor_surat</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>SRK/K/001/2026</code></td>
                                                                <td>Nomor surat, maksimal 255 karakter.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tujuan</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>Kantor Pusat BMKG</code></td>
                                                                <td>Nama instansi atau pihak tujuan.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>perihal</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>Penyampaian laporan</code></td>
                                                                <td>Ringkasan perihal surat.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tahun</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>{{ now()->year }}</code></td>
                                                                <td>
                                                                    Rentang {{ config('app.start_year', 2025) }}–{{ now()->year }}.
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>access_id</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>1</code></td>
                                                                <td>Ambil ID dari endpoint referensi sifat akses.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>filelist_id</code></td>
                                                                <td>Tidak</td>
                                                                <td>—</td>
                                                                <td>Jangan dikirim; request ditolak jika field ini berisi nilai.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>berkas</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>surat.pdf</code></td>
                                                                <td>
                                                                    PDF valid, maksimal
                                                                    {{ (int) ceil(config('documents.max_upload_kb') / 1024) }} MB.
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card">
                                        <div class="card-header" id="apiGuideOutgoingManualHeading">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button"
                                                data-toggle="collapse" data-target="#apiGuideOutgoingManual"
                                                aria-expanded="false" aria-controls="apiGuideOutgoingManual">
                                                Surat Keluar Non-SRIKANDI
                                                <span class="badge badge-secondary ml-2">is_srikandi = 0</span>
                                            </button>
                                        </div>
                                        <div id="apiGuideOutgoingManual" class="collapse"
                                            aria-labelledby="apiGuideOutgoingManualHeading"
                                            data-parent="#apiGuideOutgoingScenarios">
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Field</th>
                                                                <th>Wajib</th>
                                                                <th>Tipe / contoh</th>
                                                                <th>Panduan</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><code>is_srikandi</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>0</code></td>
                                                                <td>Menandai pencatatan dilakukan di luar SRIKANDI.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>is_digital</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>0</code> atau <code>1</code></td>
                                                                <td><code>0</code> untuk manual, <code>1</code> untuk digital.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tanggal_surat</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>2026-07-29</code></td>
                                                                <td>Tanggal yang tercantum pada surat.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>nomor_surat</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>BMKG/K/123/2026</code></td>
                                                                <td>Nomor surat, maksimal 255 karakter.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tujuan</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>Pemerintah Kabupaten</code></td>
                                                                <td>Nama instansi atau pihak tujuan.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>perihal</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>Penyampaian informasi</code></td>
                                                                <td>Ringkasan perihal surat.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>tahun</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>{{ now()->year }}</code></td>
                                                                <td>
                                                                    Rentang {{ config('app.start_year', 2025) }}–{{ now()->year }}.
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>access_id</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>1</code></td>
                                                                <td>Ambil ID dari endpoint referensi sifat akses.</td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>filelist_id</code></td>
                                                                <td>Tidak</td>
                                                                <td><code>12</code></td>
                                                                <td>
                                                                    Boleh kosong. Jika diisi, gunakan berkas aktif yang belum
                                                                    masuk alih media.
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><code>berkas</code></td>
                                                                <td><span class="badge badge-danger">Ya</span></td>
                                                                <td><code>surat.pdf</code></td>
                                                                <td>
                                                                    PDF valid, maksimal
                                                                    {{ (int) ceil(config('documents.max_upload_kb') / 1024) }} MB.
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-secondary mt-3 mb-0">
                    Ambil <code>access_id</code> melalui
                    <code>GET {{ route('api.v1.references.accesses') }}</code>,
                    <code>filelist_id</code> melalui
                    <code>GET {{ route('api.v1.references.active-filelists') }}</code>, dan cek ketersediaan nomor agenda melalui
                    <code>GET {{ route('api.v1.surat.masuk.cek-agenda') }}</code>.
                    Detail seluruh berkas tersedia melalui
                    <code>GET {{ route('api.v1.berkas.index') }}</code>.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
