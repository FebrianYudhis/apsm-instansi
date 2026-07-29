<?php

namespace App\Http\Controllers\Surat;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteDataRequest;
use App\Models\Access;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Rules\ValidPdf;
use App\Services\ActiveYear;
use App\Services\DocumentService;
use App\Services\FilelistMutationLock;
use App\Services\SuratFilterQuery;
use App\Services\SuratPencatatanExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;
use Throwable;

class SuratMasukController extends Controller
{
    public function __construct(private ActiveYear $activeYear) {}

    public function tambah()
    {
        $data = [
            'judul' => 'Tambah Surat Masuk',
            'filelist' => Filelist::where('status_id', 1)->whereNull('alih_media_status_id')->with('classification')->get(),
            'access' => Access::all(),
        ];

        return view('app.surat.masuk.tambah', $data);
    }

    public function store()
    {
        $isSrikandi = request()->boolean('isSrikandi');
        if ($isSrikandi) {
            request()->merge(['nomorAgenda' => null, 'pemberkasan' => null]);
        } elseif (request('pemberkasan') === 'null' || request('pemberkasan') === '') {
            request()->merge(['pemberkasan' => null]);
        }

        request()->validate([
            'isSrikandi' => ['sometimes', 'boolean'],
            'nomorAgenda' => $isSrikandi
                ? ['nullable']
                : ['required', 'integer', 'min:1'],
            'tanggalDiterima' => ['required', 'date'],
            'tanggalSurat' => ['nullable', 'date'],
            'nomorSurat' => ['required', 'string', 'max:255'],
            'pengirim' => ['required', 'string', 'max:65535'],
            'perihal' => ['required', 'string', 'max:65535'],
            'sifat' => ['required', 'integer', Rule::exists('accesses', 'id')],
            'pemberkasan' => [
                'nullable',
                'integer',
                Rule::exists('filelists', 'id')->where(function ($query) {
                    $query->where('status_id', 1)
                        ->whereNull('alih_media_status_id')
                        ->whereNull('deleted_at');
                }),
            ],
            'berkas' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:'.config('documents.max_upload_kb'),
                new ValidPdf,
            ],
        ]);

        if ($isSrikandi) {
            $nomorAgenda = null;
        } else {
            $agendaSudahDigunakan = Incoming::withTrashed()
                ->where('nomor_agenda', request('nomorAgenda'))
                ->where('tahun', $this->activeYear->current())
                ->exists();
            if ($agendaSudahDigunakan) {
                Alert::error('Gagal', 'Nomor Agenda Sudah Digunakan');

                return redirect()->route('masuk.tambah');
            }

            $nomorAgenda = request('nomorAgenda');
        }

        if (! $this->isValidPemberkasanTujuan(request('pemberkasan'))) {
            Alert::error('Gagal', 'Berkas tujuan sudah dialihmediakan atau tidak valid');

            return redirect()->route('masuk.tambah')->withInput();
        }

        $dokumen = app(DocumentService::class)->storeOriginal(
            DocumentService::TYPE_INCOMING,
            request()->file('berkas')
        );

        try {
            $masukkan = new Incoming([
                'nomor_agenda' => $nomorAgenda,
                'tanggal_diterima' => request('tanggalDiterima'),
                'nomor_surat' => request('nomorSurat'),
                'pengirim' => request('pengirim'),
                'tanggal_surat' => request('tanggalSurat'),
                'perihal' => request('perihal'),
                'url' => $dokumen,
                'tahun' => $this->activeYear->current(),
                'is_srikandi' => $isSrikandi,
                'filelist_id' => request('pemberkasan') == 'null' ? null : request('pemberkasan'),
                'access_id' => request('sifat'),
            ]);
            DB::transaction(function () use ($masukkan, $nomorAgenda) {
                app(FilelistMutationLock::class)->lock(null, $masukkan->filelist_id);
                $this->ensureAgendaAvailable($nomorAgenda, $this->activeYear->current());
                $masukkan->saveOrFail();
            });
        } catch (Throwable $exception) {
            Storage::disk(config('documents.disk'))->delete($dokumen);
            throw $exception;
        }

        Alert::success('Berhasil', 'Surat Masuk Berhasil Ditambahkan');

        return redirect()->route('surat.masuk');
    }

    public function hapus(DeleteDataRequest $request, $id)
    {
        $surat = Incoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Masuk Tidak Ditemukan');

            return redirect()->route('surat.masuk');
        }

        if ($surat->tahun != $this->activeYear->current()) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.masuk');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat dihapus');

            return redirect()->route('surat.masuk');
        }

        $currentFilelistId = $surat->filelist_id;
        $deletedBy = $request->user();
        $deletionReason = $request->deletionReason();

        try {
            $deleted = DB::transaction(function () use ($id, $currentFilelistId, $deletedBy, $deletionReason) {
                $filelists = app(FilelistMutationLock::class)->lock($currentFilelistId, null);
                $lockedSurat = Incoming::lockForUpdate()->find($id);

                if (
                    ! $lockedSurat
                    || (int) $lockedSurat->tahun !== $this->activeYear->current()
                    || (int) $lockedSurat->filelist_id !== (int) $currentFilelistId
                ) {
                    return false;
                }

                if ($currentFilelistId !== null) {
                    $lockedSurat->setRelation('filelist', $filelists->get($currentFilelistId));
                }

                return ! $lockedSurat->isAlihMediaLocked()
                    && $lockedSurat->deleteWithAudit($deletedBy, $deletionReason);
            });
        } catch (ValidationException $exception) {
            $deleted = false;
        }

        if ($deleted) {
            Alert::success('Berhasil', 'Surat Masuk Berhasil Dihapus');

            return redirect()->route('surat.masuk');
        } else {
            Alert::error('Gagal', 'Surat Masuk gagal dihapus karena datanya berubah atau masuk proses alih media');

            return redirect()->route('surat.masuk');
        }
    }

    public function edit($id)
    {
        $surat = Incoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Masuk Tidak Ditemukan');

            return redirect()->route('surat.masuk');
        } elseif ($surat->tahun != $this->activeYear->current()) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.masuk');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat diedit');

            return redirect()->route('surat.masuk');
        }

        $data = [
            'judul' => 'Edit Surat Masuk',
            'data' => $surat,
            'filelist' => Filelist::where('status_id', 1)->whereNull('alih_media_status_id')->with('classification')->get(),
            'access' => Access::all(),
        ];

        return view('app.surat.masuk.edit', $data);
    }

    public function update($id)
    {
        $surat = Incoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Masuk Tidak Ditemukan');

            return redirect()->route('surat.masuk');
        }

        if ($surat->tahun != $this->activeYear->current()) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.masuk');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat diedit');

            return redirect()->route('surat.masuk');
        }

        $isSrikandi = request()->boolean('isSrikandi');
        if ($isSrikandi) {
            request()->merge(['nomorAgenda' => null, 'pemberkasan' => null]);
        } elseif (request('pemberkasan') === 'null' || request('pemberkasan') === '') {
            request()->merge(['pemberkasan' => null]);
        }

        request()->validate([
            'isSrikandi' => ['sometimes', 'boolean'],
            'nomorAgenda' => $isSrikandi
                ? ['nullable']
                : ['required', 'integer', 'min:1'],
            'tanggalDiterima' => ['required', 'date'],
            'tanggalSurat' => ['nullable', 'date'],
            'nomorSurat' => ['required', 'string', 'max:255'],
            'pengirim' => ['required', 'string', 'max:65535'],
            'perihal' => ['required', 'string', 'max:65535'],
            'sifat' => ['required', 'integer', Rule::exists('accesses', 'id')],
            'pemberkasan' => [
                'nullable',
                'integer',
                Rule::exists('filelists', 'id')->where(function ($query) {
                    $query->where('status_id', 1)
                        ->whereNull('alih_media_status_id')
                        ->whereNull('deleted_at');
                }),
            ],
            'berkas' => [
                'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:'.config('documents.max_upload_kb'),
                new ValidPdf,
            ],
        ]);

        if ($isSrikandi) {
            $nomorAgenda = null;
        } else {
            $agendaSudahDigunakan = Incoming::withTrashed()
                ->where('nomor_agenda', request('nomorAgenda'))
                ->where('tahun', $this->activeYear->current())
                ->where('id', '!=', $id)
                ->exists();
            if ($agendaSudahDigunakan) {
                Alert::error('Gagal', 'Nomor Agenda Sudah Digunakan');

                return redirect()->route('masuk.edit', $id);
            }

            $nomorAgenda = request('nomorAgenda');
        }

        if (! $this->isValidPemberkasanTujuan(request('pemberkasan'))) {
            Alert::error('Gagal', 'Berkas tujuan sudah dialihmediakan atau tidak valid');

            return redirect()->route('masuk.edit', $id)->withInput();
        }

        $dokumenBaru = request()->file('berkas')
            ? app(DocumentService::class)->storeOriginal(
                DocumentService::TYPE_INCOMING,
                request()->file('berkas')
            )
            : null;
        $dokumenLama = $surat->url;
        $currentFilelistId = $surat->filelist_id;

        $surat->fill([
            'nomor_agenda' => $nomorAgenda,
            'tanggal_diterima' => request('tanggalDiterima'),
            'nomor_surat' => request('nomorSurat'),
            'pengirim' => request('pengirim'),
            'tanggal_surat' => request('tanggalSurat'),
            'perihal' => request('perihal'),
            'url' => $dokumenBaru ?: $dokumenLama,
            'is_srikandi' => $isSrikandi,
            'filelist_id' => request('pemberkasan') == 'null' ? null : request('pemberkasan'),
            'access_id' => request('sifat'),
        ]);
        $changes = $surat->getDirty();

        try {
            DB::transaction(function () use (&$surat, $changes, $nomorAgenda, $id, $currentFilelistId) {
                $filelists = app(FilelistMutationLock::class)->lock(
                    $currentFilelistId,
                    $changes['filelist_id'] ?? $surat->filelist_id
                );
                $lockedSurat = Incoming::lockForUpdate()->find($id);

                if (
                    ! $lockedSurat
                    || (int) $lockedSurat->tahun !== $this->activeYear->current()
                    || (int) $lockedSurat->filelist_id !== (int) $currentFilelistId
                ) {
                    throw ValidationException::withMessages([
                        'pemberkasan' => 'Data surat berubah saat diproses. Muat ulang halaman dan coba kembali.',
                    ]);
                }

                if ($currentFilelistId !== null) {
                    $lockedSurat->setRelation('filelist', $filelists->get($currentFilelistId));
                }

                if ($lockedSurat->isAlihMediaLocked()) {
                    throw ValidationException::withMessages([
                        'pemberkasan' => 'Surat sudah masuk proses alih media dan tidak dapat diedit.',
                    ]);
                }

                $this->ensureAgendaAvailable($nomorAgenda, $this->activeYear->current(), $id);
                $lockedSurat->fill($changes);
                $lockedSurat->saveOrFail();
                $surat = $lockedSurat;
            });
        } catch (Throwable $exception) {
            if ($dokumenBaru) {
                Storage::disk(config('documents.disk'))->delete($dokumenBaru);
            }
            throw $exception;
        }

        if ($dokumenBaru && $dokumenLama !== $dokumenBaru) {
            app(DocumentService::class)->delete(
                DocumentService::TYPE_INCOMING,
                $dokumenLama
            );
        }

        Alert::success('Berhasil', 'Surat Masuk Berhasil Diubah');

        return redirect()->route('surat.masuk');
    }

    private function isValidPemberkasanTujuan($filelistId): bool
    {
        if ($filelistId === null || $filelistId === '' || $filelistId === 'null') {
            return true;
        }

        return Filelist::where('id', $filelistId)
            ->where('status_id', 1)
            ->whereNull('alih_media_status_id')
            ->exists();
    }

    private function ensureAgendaAvailable($nomorAgenda, int $tahun, ?int $ignoreId = null): void
    {
        if ($nomorAgenda === null) {
            return;
        }

        $query = Incoming::withTrashed()
            ->where('nomor_agenda', $nomorAgenda)
            ->where('tahun', $tahun);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'nomorAgenda' => 'Nomor Agenda Sudah Digunakan',
            ]);
        }
    }

    public function exportPencatatanExcel(
        Request $request,
        SuratFilterQuery $suratFilter,
        SuratPencatatanExporter $exporter
    ) {
        $filters = $suratFilter->validateIncoming($request);

        return $exporter->incoming($this->activeYear->current(), $filters);
    }
}
