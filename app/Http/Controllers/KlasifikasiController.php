<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteDataRequest;
use App\Models\Classification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class KlasifikasiController extends Controller
{
    private const ACTIVE_UNIQUE_COLUMN = 'active_unique_key';

    private const ACTIVE_UNIQUE_INDEX = 'classifications_active_code_unique';

    public function tambah()
    {
        $data = [
            'judul' => 'Tambah Klasifikasi',
        ];

        return view('app.surat.klasifikasi.tambah', $data);
    }

    public function store()
    {
        request()->merge([
            'kodeKlasifikasi' => $this->normalizeCode(request('kodeKlasifikasi')),
        ]);

        $validated = request()->validate([
            'kodeKlasifikasi' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classifications', 'kode_klasifikasi')
                    ->where(function ($query) {
                        $query->whereNull('deleted_at');
                    }),
            ],
            'keterangan' => ['required', 'string', 'max:255'],
        ]);

        try {
            $masukkan = Classification::create([
                'kode_klasifikasi' => $validated['kodeKlasifikasi'],
                'keterangan' => $validated['keterangan'],
            ]);
        } catch (QueryException $exception) {
            $this->throwIfDuplicateCode($exception);
            throw $exception;
        }

        if ($masukkan) {
            Alert::success('Berhasil', 'Klasifikasi Berhasil Ditambahkan');

            return redirect()->route('surat.klasifikasi');
        } else {
            Alert::error('Gagal', 'Klasifikasi Gagal Ditambahkan');

            return redirect()->route('surat.klasifikasi');
        }
    }

    public function hapus(DeleteDataRequest $request, $id)
    {
        $result = DB::transaction(function () use ($id, $request) {
            $klasifikasi = Classification::lockForUpdate()->find($id);

            if (! $klasifikasi) {
                return 'not_found';
            }

            if ($klasifikasi->filelists()->lockForUpdate()->first(['id'])) {
                return 'used';
            }

            return $klasifikasi->deleteWithAudit(
                $request->user(),
                $request->deletionReason()
            ) ? 'deleted' : 'failed';
        }, 3);

        if ($result === 'not_found') {
            Alert::error('Gagal', 'Klasifikasi Tidak Ditemukan');

            return redirect()->route('surat.klasifikasi');
        }

        if ($result === 'used') {
            Alert::error('Gagal', 'Klasifikasi Tidak Dapat Dihapus, Karena Masih Digunakan');

            return redirect()->route('surat.klasifikasi');
        }

        if ($result === 'deleted') {
            Alert::success('Berhasil', 'Klasifikasi Berhasil Dihapus');
        } else {
            Alert::error('Gagal', 'Klasifikasi Gagal Dihapus');
        }

        return redirect()->route('surat.klasifikasi');
    }

    public function edit($id)
    {
        $klasifikasi = Classification::find($id);
        if (! $klasifikasi) {
            Alert::error('Gagal', 'Klasifikasi Tidak Ditemukan');

            return redirect()->route('surat.klasifikasi');
        }

        $data = [
            'judul' => 'Edit Klasifikasi',
            'data' => $klasifikasi,
        ];

        return view('app.surat.klasifikasi.edit', $data);
    }

    public function update($id)
    {
        $masukkan = Classification::find($id);
        if (! $masukkan) {
            Alert::error('Gagal', 'Klasifikasi Tidak Ditemukan');

            return redirect()->route('surat.klasifikasi');
        }

        request()->merge([
            'kodeKlasifikasi' => $this->normalizeCode(request('kodeKlasifikasi')),
        ]);

        $validated = request()->validate([
            'kodeKlasifikasi' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classifications', 'kode_klasifikasi')
                    ->where(function ($query) {
                        $query->whereNull('deleted_at');
                    })
                    ->ignore($masukkan->getKey()),
            ],
            'keterangan' => ['required', 'string', 'max:255'],
        ]);

        try {
            $masukkan->kode_klasifikasi = $validated['kodeKlasifikasi'];
            $masukkan->keterangan = $validated['keterangan'];
            $masukkan->saveOrFail();
        } catch (QueryException $exception) {
            $this->throwIfDuplicateCode($exception);
            throw $exception;
        }

        if ($masukkan) {
            Alert::success('Berhasil', 'Klasifikasi Berhasil Diubah');

            return redirect()->route('surat.klasifikasi');
        } else {
            Alert::error('Gagal', 'Klasifikasi Gagal Diubah');

            return redirect()->route('surat.klasifikasi');
        }
    }

    private function normalizeCode($code): string
    {
        return Str::upper(trim((string) $code));
    }

    private function throwIfDuplicateCode(QueryException $exception): void
    {
        $message = $exception->getMessage();
        if (
            strpos($message, self::ACTIVE_UNIQUE_COLUMN) === false
            && strpos($message, self::ACTIVE_UNIQUE_INDEX) === false
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'kodeKlasifikasi' => 'Kode klasifikasi aktif sudah digunakan.',
        ]);
    }
}
