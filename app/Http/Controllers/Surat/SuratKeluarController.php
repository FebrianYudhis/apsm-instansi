<?php

namespace App\Http\Controllers\Surat;

use App\Actions\CreateOutgoingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteDataRequest;
use App\Models\Access;
use App\Models\Filelist;
use App\Models\Outcoming;
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

class SuratKeluarController extends Controller
{
    public function __construct(private ActiveYear $activeYear) {}

    public function tambah()
    {
        $data = [
            'judul' => 'Tambah Surat Keluar',
            'filelist' => Filelist::where('status_id', 1)->whereNull('alih_media_status_id')->with('classification')->get(),
            'access' => Access::all(),
        ];

        return view('app.surat.keluar.tambah', $data);
    }

    public function store(CreateOutgoingAction $createOutgoing)
    {
        $data = $this->validateRequest(true);

        $createOutgoing->handle(
            [
                'tanggal_surat' => $data['tanggalSurat'],
                'nomor_surat' => $data['nomorSurat'],
                'tujuan' => $data['tujuan'],
                'perihal' => $data['perihal'],
                'tahun' => $this->activeYear->current(),
                'is_digital' => $data['is_digital'],
                'is_srikandi' => $data['is_srikandi'],
                'filelist_id' => $data['pemberkasan'],
                'access_id' => $data['sifat'],
            ],
            request()->file('berkas'),
            'pemberkasan'
        );

        Alert::success('Berhasil', 'Surat Keluar Berhasil Ditambahkan');

        return redirect()->route('surat.keluar');
    }

    public function hapus(DeleteDataRequest $request, $id)
    {
        $surat = Outcoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Keluar Tidak Ditemukan');

            return redirect()->route('surat.keluar');
        }

        if ($surat->tahun != $this->activeYear->current()) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.keluar');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat dihapus');

            return redirect()->route('surat.keluar');
        }

        $currentFilelistId = $surat->filelist_id;
        $deletedBy = $request->user();
        $deletionReason = $request->deletionReason();

        try {
            $deleted = DB::transaction(function () use ($id, $currentFilelistId, $deletedBy, $deletionReason) {
                $filelists = app(FilelistMutationLock::class)->lock($currentFilelistId, null);
                $lockedSurat = Outcoming::lockForUpdate()->find($id);

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
            Alert::success('Berhasil', 'Surat Keluar Berhasil Dihapus');

            return redirect()->route('surat.keluar');
        } else {
            Alert::error('Gagal', 'Surat Keluar gagal dihapus karena datanya berubah atau masuk proses alih media');

            return redirect()->route('surat.keluar');
        }
    }

    public function edit($id)
    {
        $surat = Outcoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Keluar Tidak Ditemukan');

            return redirect()->route('surat.keluar');
        } elseif ($surat->tahun != $this->activeYear->current()) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.keluar');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat diedit');

            return redirect()->route('surat.keluar');
        }

        $data = [
            'judul' => 'Edit Surat Keluar',
            'data' => $surat,
            'filelist' => Filelist::where('status_id', 1)->whereNull('alih_media_status_id')->with('classification')->get(),
            'access' => Access::all(),
        ];

        return view('app.surat.keluar.edit', $data);
    }

    public function update($id)
    {
        $surat = Outcoming::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Keluar Tidak Ditemukan');

            return redirect()->route('surat.keluar');
        }

        if ($surat->tahun != $this->activeYear->current()) {
            Alert::error('Gagal', 'Anda Tidak Memiliki Akses');

            return redirect()->route('surat.keluar');
        }

        if ($surat->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Surat yang sudah masuk proses alih media tidak dapat diedit');

            return redirect()->route('surat.keluar');
        }

        $data = $this->validateRequest(false);
        $dokumenBaru = request()->file('berkas')
            ? app(DocumentService::class)->storeOriginal(
                DocumentService::TYPE_OUTGOING,
                request()->file('berkas')
            )
            : null;
        $urlLama = $surat->url;
        $watermarkLama = $surat->url_watermarked;
        $currentFilelistId = $surat->filelist_id;

        $surat->fill([
            'tanggal_surat' => $data['tanggalSurat'],
            'nomor_surat' => $data['nomorSurat'],
            'tujuan' => $data['tujuan'],
            'perihal' => $data['perihal'],
            'url' => $dokumenBaru ?: $urlLama,
            'is_digital' => $data['is_digital'],
            'is_srikandi' => $data['is_srikandi'],
            'filelist_id' => $data['pemberkasan'],
            'access_id' => $data['sifat'],
        ]);

        $watermarkTidakBerlaku = $dokumenBaru !== null || $surat->isDirty([
            'tanggal_surat',
            'nomor_surat',
            'tujuan',
            'perihal',
            'url',
            'is_digital',
            'is_srikandi',
            'filelist_id',
            'access_id',
        ]);

        if ($watermarkTidakBerlaku) {
            $surat->url_watermarked = null;
        }
        $changes = $surat->getDirty();

        try {
            DB::transaction(function () use (&$surat, $changes, $id, $currentFilelistId) {
                $filelists = app(FilelistMutationLock::class)->lock(
                    $currentFilelistId,
                    $changes['filelist_id'] ?? $surat->filelist_id
                );
                $lockedSurat = Outcoming::lockForUpdate()->find($id);

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

        if ($dokumenBaru && $urlLama !== $dokumenBaru) {
            app(DocumentService::class)->delete(
                DocumentService::TYPE_OUTGOING,
                $urlLama
            );
        }
        if ($watermarkTidakBerlaku && $watermarkLama && $watermarkLama !== $surat->url) {
            app(DocumentService::class)->delete(
                DocumentService::TYPE_OUTGOING,
                $watermarkLama,
                DocumentService::VARIANT_WATERMARK
            );
        }

        Alert::success('Berhasil', 'Surat Keluar Berhasil Diubah');

        return redirect()->route('surat.keluar');
    }

    private function validateRequest(bool $berkasWajib): array
    {
        $isSrikandi = request()->boolean('isSrikandi');
        $pemberkasan = request('pemberkasan');

        if ($isSrikandi) {
            request()->merge([
                'pemberkasan' => null,
            ]);
        } elseif ($pemberkasan === '' || $pemberkasan === 'null') {
            request()->merge(['pemberkasan' => null]);
        }

        $data = request()->validate([
            'jenis' => ['required', Rule::in([0, 1])],
            'isSrikandi' => ['sometimes', 'boolean'],
            'tanggalSurat' => ['required', 'date'],
            'nomorSurat' => ['required', 'string', 'max:255'],
            'tujuan' => ['required', 'string', 'max:65535'],
            'perihal' => ['required', 'string', 'max:65535'],
            'sifat' => ['required', 'integer', Rule::exists('accesses', 'id')],
            'pemberkasan' => [
                'nullable',
                Rule::exists('filelists', 'id')->where(function ($query) {
                    $query->where('status_id', 1)
                        ->whereNull('alih_media_status_id')
                        ->whereNull('deleted_at');
                }),
            ],
            'berkas' => array_filter([
                $berkasWajib ? 'required' : 'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:'.config('documents.max_upload_kb'),
                new ValidPdf,
            ]),
        ]);

        $data['is_srikandi'] = $isSrikandi;
        $data['is_digital'] = $isSrikandi || (int) $data['jenis'] === 1;
        $data['pemberkasan'] = $isSrikandi ? null : ($data['pemberkasan'] ?? null);

        return $data;
    }

    public function exportPencatatanExcel(
        Request $request,
        SuratFilterQuery $suratFilter,
        SuratPencatatanExporter $exporter
    ) {
        $filters = $suratFilter->validateOutgoing($request);

        return $exporter->outgoing($this->activeYear->current(), $filters);
    }
}
