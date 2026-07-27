<?php

namespace App\Http\Controllers;

use App\Models\Classification;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use RealRashid\SweetAlert\Facades\Alert;

class BerkasController extends Controller
{
    private function formatTanggalIndonesia($tanggal): string
    {
        if (empty($tanggal) || $tanggal === '-') {
            return '-';
        }

        try {
            return Carbon::parse($tanggal)->format('d-m-Y');
        } catch (\Throwable $th) {
            return (string) $tanggal;
        }
    }

    public function tambah()
    {
        $data = [
            'judul' => 'Tambah Berkas',
            'classification' => Classification::all(),
        ];

        return view('app.surat.berkas.tambah', $data);
    }

    public function store()
    {
        $validated = request()->validate([
            'kodeKlasifikasi' => [
                'required',
                'integer',
                Rule::exists('classifications', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
            ],
            'namaBerkas' => ['required', 'string', 'max:255'],
            'retensiAktif' => ['required', 'integer', 'min:0'],
            'retensiInaktif' => ['required', 'integer', 'min:0'],
            'keteranganAkhir' => ['required', Rule::in(['Permanen', 'Musnah'])],
        ]);

        $masukkan = DB::transaction(function () use ($validated) {
            $classification = Classification::lockForUpdate()
                ->find($validated['kodeKlasifikasi']);

            if (! $classification) {
                throw ValidationException::withMessages([
                    'kodeKlasifikasi' => 'Klasifikasi sudah dihapus atau tidak valid.',
                ]);
            }

            return Filelist::create([
                'classification_id' => $classification->id,
                'nama_berkas' => $validated['namaBerkas'],
                'retensi_aktif' => $validated['retensiAktif'],
                'retensi_inaktif' => $validated['retensiInaktif'],
                'keterangan_akhir' => $validated['keteranganAkhir'],
            ]);
        }, 3);

        if ($masukkan) {
            Alert::success('Berhasil', 'Berkas Berhasil Ditambahkan');

            return redirect()->route('surat.berkas');
        } else {
            Alert::error('Gagal', 'Berkas Gagal Ditambahkan');

            return redirect()->route('surat.berkas');
        }
    }

    public function hapus($id)
    {
        $result = DB::transaction(function () use ($id) {
            $berkas = Filelist::lockForUpdate()->find($id);
            if (! $berkas) {
                return 'not_found';
            }

            if ($berkas->isAlihMediaLocked()) {
                return 'locked';
            }

            if ($berkas->incomings()->exists() || $berkas->outcomings()->exists()) {
                return 'used';
            }

            return $berkas->delete() ? 'deleted' : 'failed';
        });

        if ($result === 'not_found') {
            Alert::error('Gagal', 'Berkas Tidak Ditemukan');

            return redirect()->route('surat.berkas');
        }

        if ($result === 'locked') {
            Alert::error('Gagal', 'Berkas yang sudah masuk proses alih media tidak dapat dihapus');

            return redirect()->route('surat.berkas');
        }

        if ($result === 'used') {
            Alert::error('Gagal', 'Berkas Tidak Dapat Dihapus, Karena Masih Digunakan');

            return redirect()->route('surat.berkas');
        }

        if ($result === 'deleted') {
            Alert::success('Berhasil', 'Berkas Berhasil Dihapus');
        } else {
            Alert::error('Gagal', 'Berkas Gagal Dihapus');
        }

        return redirect()->route('surat.berkas');
    }

    public function edit($id)
    {
        $berkas = Filelist::find($id);
        if (! $berkas) {
            Alert::error('Gagal', 'Berkas Tidak Ditemukan');

            return redirect()->route('surat.berkas');
        }

        if ($berkas->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Berkas yang sudah masuk proses alih media tidak dapat diedit');

            return redirect()->route('surat.berkas');
        }

        $data = [
            'judul' => 'Edit Berkas',
            'data' => $berkas,
            'classification' => Classification::all(),
        ];

        return view('app.surat.berkas.edit', $data);
    }

    public function update($id)
    {
        $validated = request()->validate([
            'kodeKlasifikasi' => [
                'required',
                'integer',
                Rule::exists('classifications', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
            ],
            'namaBerkas' => ['required', 'string', 'max:255'],
            'retensiAktif' => ['required', 'integer', 'min:0'],
            'retensiInaktif' => ['required', 'integer', 'min:0'],
            'keteranganAkhir' => ['required', Rule::in(['Permanen', 'Musnah'])],
        ]);

        $result = DB::transaction(function () use ($id, $validated) {
            $classification = Classification::lockForUpdate()
                ->find($validated['kodeKlasifikasi']);

            if (! $classification) {
                throw ValidationException::withMessages([
                    'kodeKlasifikasi' => 'Klasifikasi sudah dihapus atau tidak valid.',
                ]);
            }

            $berkas = Filelist::lockForUpdate()->find($id);
            if (! $berkas) {
                return 'not_found';
            }

            if ($berkas->isAlihMediaLocked()) {
                return 'locked';
            }

            $berkas->classification_id = $classification->id;
            $berkas->nama_berkas = $validated['namaBerkas'];
            $berkas->retensi_aktif = $validated['retensiAktif'];
            $berkas->retensi_inaktif = $validated['retensiInaktif'];
            $berkas->keterangan_akhir = $validated['keteranganAkhir'];
            $berkas->saveOrFail();

            return 'updated';
        }, 3);

        if ($result === 'not_found') {
            Alert::error('Gagal', 'Berkas Tidak Ditemukan');

            return redirect()->route('surat.berkas');
        }

        $redirectResponse = request()->boolean('redirect_back')
            ? redirect()->back()
            : redirect()->route('surat.berkas');

        if ($result === 'locked') {
            Alert::error('Gagal', 'Berkas yang sudah masuk proses alih media tidak dapat diedit');

            return $redirectResponse;
        }

        Alert::success('Berhasil', 'Berkas Berhasil Diubah');

        return $redirectResponse;
    }

    public function buka($id)
    {
        $berkas = Filelist::find($id);
        if (! $berkas) {
            Alert::error('Gagal', 'Berkas Tidak Ditemukan');

            return redirect()->route('surat.berkas');
        }

        $tanggalDari = request('tanggal_dari');
        $tanggalSampai = request('tanggal_sampai');

        request()->validate([
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => ['nullable', 'date', 'after_or_equal:tanggal_dari'],
        ]);

        $incomingsQuery = $berkas->incomings()->with(['access', 'filelist']);
        $outcomingsQuery = $berkas->outcomings()->with(['access', 'filelist']);

        if (! empty($tanggalDari)) {
            $incomingsQuery->whereDate('tanggal_surat', '>=', $tanggalDari);
            $outcomingsQuery->whereDate('tanggal_surat', '>=', $tanggalDari);
        }

        if (! empty($tanggalSampai)) {
            $incomingsQuery->whereDate('tanggal_surat', '<=', $tanggalSampai);
            $outcomingsQuery->whereDate('tanggal_surat', '<=', $tanggalSampai);
        }

        $incomings = $incomingsQuery->get();
        $outcomings = $outcomingsQuery->get();
        $combinedData = collect();

        foreach ($incomings as $incoming) {
            $sifatAksesIncoming = $incoming->access ? $incoming->access->sifat_akses : null;

            $combinedData->push([
                'id' => $incoming->id,
                'jenis' => 'masuk',
                'uraian' => $incoming->perihal,
                'nomor_naskah' => $incoming->nomor_surat,
                'tanggal_item' => $incoming->tanggal_surat,
                'skkad' => $sifatAksesIncoming,
                'filelist_id' => $incoming->filelist_id,
                'tahun' => $incoming->tahun,
                'is_locked' => $incoming->isAlihMediaLocked(),
            ]);
        }

        foreach ($outcomings as $outcoming) {
            $sifatAksesOutgoing = $outcoming->access ? $outcoming->access->sifat_akses : null;

            $combinedData->push([
                'id' => $outcoming->id,
                'jenis' => 'keluar',
                'uraian' => $outcoming->perihal,
                'nomor_naskah' => $outcoming->nomor_surat,
                'tanggal_item' => $outcoming->tanggal_surat,
                'skkad' => $sifatAksesOutgoing,
                'filelist_id' => $outcoming->filelist_id,
                'tahun' => $outcoming->tahun,
                'is_locked' => $outcoming->isAlihMediaLocked(),
            ]);
        }

        $tanggalItems = $combinedData->filter(function ($item) {
            return ! empty($item['tanggal_item']);
        })->sortBy('tanggal_item')->values();

        $kurunWaktu = '-';
        if ($tanggalItems->isNotEmpty()) {
            $tanggalAwal = $this->formatTanggalIndonesia($tanggalItems->first()['tanggal_item']);
            $tanggalAkhir = $this->formatTanggalIndonesia($tanggalItems->last()['tanggal_item']);
            $kurunWaktu = $tanggalAwal === $tanggalAkhir ? $tanggalAwal : $tanggalAwal.' s/d '.$tanggalAkhir;
        }

        $data = [
            'judul' => 'Daftar Isi Berkas',
            'data' => $combinedData,
            'berkas' => $berkas,
            'kurunWaktu' => $kurunWaktu,
            'classification' => Classification::orderBy('kode_klasifikasi')->get(),
        ];

        return view('app.surat.berkas.buka', $data);
    }

    public function daftarBerkasAktif()
    {
        $filelist = Filelist::where('status_id', 1)
            ->whereNull('alih_media_status_id')
            ->with('classification:id,kode_klasifikasi')
            ->orderBy('nama_berkas')
            ->get(['id', 'classification_id', 'nama_berkas']);

        $data = $filelist->map(function ($item) {
            return [
                'id' => $item->id,
                'kode_klasifikasi' => optional($item->classification)->kode_klasifikasi ?? '-',
                'nama_berkas' => $item->nama_berkas,
            ];
        })->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function gantiLokasiBulk(Request $request)
    {
        $request->validate([
            'berkas_asal' => [
                'required',
                'integer',
                Rule::exists('filelists', 'id'),
            ],
            'pemberkasan' => [
                'required',
                'integer',
                Rule::exists('filelists', 'id')->where(function ($query) {
                    $query->where('status_id', 1)
                        ->whereNull('alih_media_status_id')
                        ->whereNull('deleted_at');
                }),
            ],
            'items' => 'required|array|min:1',
            'items.*' => ['required', 'regex:/^(masuk|keluar):\d+$/'],
        ]);

        $incomingIds = [];
        $outcomingIds = [];

        foreach ($request->items as $item) {
            [$jenis, $id] = explode(':', $item);
            if ($jenis === 'masuk') {
                $incomingIds[] = (int) $id;
            } elseif ($jenis === 'keluar') {
                $outcomingIds[] = (int) $id;
            }
        }

        $incomingIds = array_values(array_unique($incomingIds));
        $outcomingIds = array_values(array_unique($outcomingIds));

        $result = DB::transaction(function () use ($incomingIds, $outcomingIds, $request) {
            $berkas = Filelist::whereIn('id', [
                (int) $request->berkas_asal,
                (int) $request->pemberkasan,
            ])->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $berkasAsal = $berkas->get((int) $request->berkas_asal);
            $berkasTujuan = $berkas->get((int) $request->pemberkasan);

            if (
                ! $berkasAsal
                || (int) $berkasAsal->status_id !== 1
                || $berkasAsal->isAlihMediaLocked()
            ) {
                return ['status' => 'source_invalid'];
            }

            if (
                ! $berkasTujuan
                || (int) $berkasTujuan->status_id !== 1
                || $berkasTujuan->isAlihMediaLocked()
            ) {
                return ['status' => 'target_invalid'];
            }

            if ((int) $berkasAsal->id === (int) $berkasTujuan->id) {
                return ['status' => 'same_filelist'];
            }

            $incomingItems = count($incomingIds) > 0
                ? Incoming::whereIn('id', $incomingIds)
                    ->where('filelist_id', $berkasAsal->id)
                    ->lockForUpdate()
                    ->get()
                : collect();

            if ($incomingItems->count() !== count($incomingIds)) {
                return ['status' => 'incoming_invalid'];
            }

            $outcomingItems = count($outcomingIds) > 0
                ? Outcoming::whereIn('id', $outcomingIds)
                    ->where('filelist_id', $berkasAsal->id)
                    ->lockForUpdate()
                    ->get()
                : collect();

            if ($outcomingItems->count() !== count($outcomingIds)) {
                return ['status' => 'outcoming_invalid'];
            }

            $lockedItem = $incomingItems->concat($outcomingItems)->contains(function ($surat) use ($berkasAsal) {
                $surat->setRelation('filelist', $berkasAsal);

                return $surat->isAlihMediaLocked();
            });

            if ($lockedItem) {
                return ['status' => 'item_locked'];
            }

            $updatedIncoming = 0;
            foreach ($incomingItems as $incoming) {
                $incoming->filelist_id = $berkasTujuan->id;
                $incoming->saveOrFail();
                $updatedIncoming++;
            }

            $updatedOutcoming = 0;
            foreach ($outcomingItems as $outcoming) {
                $outcoming->filelist_id = $berkasTujuan->id;
                $outcoming->saveOrFail();
                $updatedOutcoming++;
            }

            return [
                'status' => 'updated',
                'count' => $updatedIncoming + $updatedOutcoming,
            ];
        });

        $messages = [
            'source_invalid' => 'Berkas asal tidak valid untuk pemindahan surat',
            'target_invalid' => 'Berkas tujuan sudah dialihmediakan atau tidak valid',
            'same_filelist' => 'Berkas tujuan harus berbeda dari berkas asal',
            'incoming_invalid' => 'Terdapat surat masuk yang tidak berada di berkas asal',
            'outcoming_invalid' => 'Terdapat surat keluar yang tidak berada di berkas asal',
            'item_locked' => 'Surat yang sudah masuk proses alih media tidak dapat dipindahkan',
        ];

        if ($result['status'] !== 'updated') {
            Alert::error('Gagal', $messages[$result['status']] ?? 'Pemindahan surat gagal');

            return redirect()->back();
        }

        if ($result['count'] < 1) {
            Alert::error('Gagal', 'Tidak ada surat yang berhasil dipindahkan');

            return redirect()->back();
        }

        Alert::success('Berhasil', $result['count'].' surat berhasil dipindahkan');

        return redirect()->back();
    }

    public function keluarkan($idBerkas, $jenis, $idSurat)
    {
        if (! in_array($jenis, ['masuk', 'keluar'], true)) {
            Alert::error('Gagal', 'Jenis Surat Tidak Valid');

            return redirect()->back();
        }

        $result = DB::transaction(function () use ($idBerkas, $jenis, $idSurat) {
            $berkas = Filelist::lockForUpdate()->find($idBerkas);
            if (! $berkas) {
                return 'filelist_not_found';
            }

            if ((int) $berkas->status_id !== 1 || $berkas->isAlihMediaLocked()) {
                return 'filelist_locked';
            }

            $surat = $jenis === 'masuk'
                ? Incoming::lockForUpdate()->find($idSurat)
                : Outcoming::lockForUpdate()->find($idSurat);

            if (! $surat || (int) $surat->filelist_id !== (int) $berkas->id) {
                return 'letter_not_found';
            }

            $surat->setRelation('filelist', $berkas);
            if ($surat->isAlihMediaLocked()) {
                return 'letter_locked';
            }

            $surat->filelist_id = null;
            $surat->saveOrFail();

            return 'updated';
        });

        if ($result !== 'updated') {
            $messages = [
                'filelist_not_found' => 'Berkas Tidak Ditemukan',
                'filelist_locked' => 'Berkas tidak valid atau sudah masuk proses alih media',
                'letter_not_found' => 'Surat Tidak Ditemukan Pada Berkas Ini',
                'letter_locked' => 'Surat yang sudah masuk proses alih media tidak dapat dipindahkan',
            ];
            Alert::error('Gagal', $messages[$result] ?? 'Surat gagal dikeluarkan dari berkas');

            return redirect()->back();
        }

        Alert::success('Berhasil', 'Surat Berhasil Dikeluarkan Dari Berkas');

        return redirect()->back();
    }

    public function pindah(Request $request, $id, $status)
    {
        $request->merge(['status_target' => $status]);
        $request->validate([
            'password_status_berkas' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'status_target' => ['required', 'integer', Rule::exists('statuses', 'id')],
        ]);

        $mfaSecret = config('services.mfa.secret');
        if (empty($mfaSecret)) {
            Alert::error('Gagal', 'MFA_SECRET belum dikonfigurasi');

            return redirect()->route('surat.berkas');
        }

        $google2fa = new Google2FA;
        $valid = false;
        try {
            if (! empty($request->password_status_berkas)) {
                $valid = $google2fa->verifyKey($mfaSecret, $request->password_status_berkas);
            }
        } catch (\Exception $e) {
            $valid = false;
        }

        if (! $valid) {
            Alert::error('Gagal', 'Kode Token MFA salah');

            return redirect()->route('surat.berkas');
        }

        $result = DB::transaction(function () use ($id, $status) {
            $berkas = Filelist::with('status')->lockForUpdate()->find($id);
            if (! $berkas) {
                return 'not_found';
            }

            if ($berkas->isAlihMediaLocked()) {
                return 'locked';
            }

            $targetStatus = Status::find($status);
            if (! $berkas->status || ! $targetStatus || ! $berkas->status->canTransitionTo($targetStatus)) {
                return 'invalid_transition';
            }

            $berkas->status_id = $targetStatus->id;
            $berkas->saveOrFail();

            return 'updated';
        });

        if ($result === 'not_found') {
            Alert::error('Gagal', 'Berkas Tidak Ditemukan');

            return redirect()->route('surat.berkas');
        }

        if ($result === 'locked') {
            Alert::error('Gagal', 'Status berkas yang sudah masuk proses alih media tidak dapat diubah');

            return redirect()->route('surat.berkas');
        }

        if ($result === 'invalid_transition') {
            Alert::error('Gagal', 'Perubahan status berkas tidak mengikuti alur yang diizinkan');

            return redirect()->route('surat.berkas');
        }

        Alert::success('Berhasil', 'Status Berkas Berhasil Diubah');

        return redirect()->route('surat.berkas');
    }
}
