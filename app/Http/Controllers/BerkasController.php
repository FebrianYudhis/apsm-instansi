<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteDataRequest;
use App\Models\Classification;
use App\Models\Filelist;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class BerkasController extends Controller
{
    public function tambah()
    {
        return view('app.surat.berkas.tambah', [
            'judul' => 'Tambah Berkas',
            'classification' => Classification::orderBy('kode_klasifikasi', 'asc')->get(),
        ]);
    }

    public function store()
    {
        $validated = request()->validate($this->rules());

        $filelist = DB::transaction(function () use ($validated) {
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

        if ($filelist) {
            Alert::success('Berhasil', 'Berkas Berhasil Ditambahkan');
        } else {
            Alert::error('Gagal', 'Berkas Gagal Ditambahkan');
        }

        return redirect()->route('surat.berkas');
    }

    public function hapus(DeleteDataRequest $request, $id)
    {
        $result = DB::transaction(function () use ($id, $request) {
            $filelist = Filelist::lockForUpdate()->find($id);
            if (! $filelist) {
                return 'not_found';
            }

            if ($filelist->isAlihMediaLocked()) {
                return 'locked';
            }

            if ($filelist->incomings()->exists() || $filelist->outcomings()->exists()) {
                return 'used';
            }

            return $filelist->deleteWithAudit(
                $request->user(),
                $request->deletionReason()
            ) ? 'deleted' : 'failed';
        });

        if ($result === 'not_found') {
            Alert::error('Gagal', 'Berkas Tidak Ditemukan');
        } elseif ($result === 'locked') {
            Alert::error('Gagal', 'Berkas yang sudah masuk proses alih media tidak dapat dihapus');
        } elseif ($result === 'used') {
            Alert::error('Gagal', 'Berkas Tidak Dapat Dihapus, Karena Masih Digunakan');
        } elseif ($result === 'deleted') {
            Alert::success('Berhasil', 'Berkas Berhasil Dihapus');
        } else {
            Alert::error('Gagal', 'Berkas Gagal Dihapus');
        }

        return redirect()->route('surat.berkas');
    }

    public function edit($id)
    {
        $filelist = Filelist::find($id);
        if (! $filelist) {
            Alert::error('Gagal', 'Berkas Tidak Ditemukan');

            return redirect()->route('surat.berkas');
        }

        if ($filelist->isAlihMediaLocked()) {
            Alert::error('Gagal', 'Berkas yang sudah masuk proses alih media tidak dapat diedit');

            return redirect()->route('surat.berkas');
        }

        return view('app.surat.berkas.edit', [
            'judul' => 'Edit Berkas',
            'data' => $filelist,
            'classification' => Classification::orderBy('kode_klasifikasi', 'asc')->get(),
        ]);
    }

    public function update($id)
    {
        $validated = request()->validate($this->rules());

        $result = DB::transaction(function () use ($id, $validated) {
            $classification = Classification::lockForUpdate()
                ->find($validated['kodeKlasifikasi']);

            if (! $classification) {
                throw ValidationException::withMessages([
                    'kodeKlasifikasi' => 'Klasifikasi sudah dihapus atau tidak valid.',
                ]);
            }

            $filelist = Filelist::lockForUpdate()->find($id);
            if (! $filelist) {
                return 'not_found';
            }

            if ($filelist->isAlihMediaLocked()) {
                return 'locked';
            }

            $filelist->fill([
                'classification_id' => $classification->id,
                'nama_berkas' => $validated['namaBerkas'],
                'retensi_aktif' => $validated['retensiAktif'],
                'retensi_inaktif' => $validated['retensiInaktif'],
                'keterangan_akhir' => $validated['keteranganAkhir'],
            ])->saveOrFail();

            return 'updated';
        }, 3);

        if ($result === 'not_found') {
            Alert::error('Gagal', 'Berkas Tidak Ditemukan');

            return redirect()->route('surat.berkas');
        }

        $redirectResponse = request()->boolean('redirect_back')
            ? back()
            : redirect()->route('surat.berkas');

        if ($result === 'locked') {
            Alert::error('Gagal', 'Berkas yang sudah masuk proses alih media tidak dapat diedit');

            return $redirectResponse;
        }

        Alert::success('Berhasil', 'Berkas Berhasil Diubah');

        return $redirectResponse;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
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
        ];
    }
}
