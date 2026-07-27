<?php

namespace App\Http\Controllers\Surat;

use App\Http\Controllers\Controller;
use App\Models\Digital;
use App\Rules\ValidPdf;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;
use Throwable;

class SuratDigitalController extends Controller
{
    public function tambah()
    {
        $data = [
            'judul' => 'Tambah Surat Digital',
        ];

        return view('app.surat.digital.tambah', $data);
    }

    public function store()
    {
        request()->validate([
            'perihal' => ['required', 'string', 'max:65535'],
            'berkas' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:'.config('documents.max_upload_kb'),
                new ValidPdf,
            ],
        ]);

        $dokumen = app(DocumentService::class)->storeOriginal(
            DocumentService::TYPE_DIGITAL,
            request()->file('berkas')
        );

        try {
            $masukkan = new Digital([
                'perihal' => request('perihal'),
                'url' => $dokumen,
            ]);
            $masukkan->saveOrFail();
        } catch (Throwable $exception) {
            Storage::disk(config('documents.disk'))->delete($dokumen);
            throw $exception;
        }

        Alert::success('Berhasil', 'Surat Digital Berhasil Ditambahkan');

        return redirect()->route('surat.digital');
    }

    public function hapus($id)
    {
        $surat = Digital::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Digital Tidak Ditemukan');

            return redirect()->route('surat.digital');
        }

        $data = $surat->delete();
        if ($data) {
            Alert::success('Berhasil', 'Surat Digital Berhasil Dihapus');

            return redirect()->route('surat.digital');
        } else {
            Alert::error('Gagal', 'Surat Digital Gagal Dihapus');

            return redirect()->route('surat.digital');
        }
    }

    public function edit($id)
    {
        $surat = Digital::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Digital Tidak Ditemukan');

            return redirect()->route('surat.digital');
        }

        $data = [
            'judul' => 'Edit Surat Digital',
            'data' => $surat,
        ];

        return view('app.surat.digital.edit', $data);
    }

    public function update($id)
    {
        $surat = Digital::find($id);
        if (! $surat) {
            Alert::error('Gagal', 'Surat Digital Tidak Ditemukan');

            return redirect()->route('surat.digital');
        }

        request()->validate([
            'perihal' => ['required', 'string', 'max:65535'],
            'berkas' => [
                'nullable',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:'.config('documents.max_upload_kb'),
                new ValidPdf,
            ],
        ]);

        $dokumenBaru = request()->file('berkas')
            ? app(DocumentService::class)->storeOriginal(
                DocumentService::TYPE_DIGITAL,
                request()->file('berkas')
            )
            : null;
        $dokumenLama = $surat->url;

        $surat->fill([
            'perihal' => request('perihal'),
            'url' => $dokumenBaru ?: $dokumenLama,
        ]);

        try {
            $surat->saveOrFail();
        } catch (Throwable $exception) {
            if ($dokumenBaru) {
                Storage::disk(config('documents.disk'))->delete($dokumenBaru);
            }
            throw $exception;
        }

        if ($dokumenBaru && $dokumenLama !== $dokumenBaru) {
            app(DocumentService::class)->delete(
                DocumentService::TYPE_DIGITAL,
                $dokumenLama
            );
        }

        Alert::success('Berhasil', 'Surat Digital Berhasil Diubah');

        return redirect()->route('surat.digital');
    }
}
