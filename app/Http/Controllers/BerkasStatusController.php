<?php

namespace App\Http\Controllers;

use App\Services\FilelistOperationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PragmaRX\Google2FA\Google2FA;
use RealRashid\SweetAlert\Facades\Alert;
use Throwable;

class BerkasStatusController extends Controller
{
    public function pindah(
        Request $request,
        int $id,
        int $status,
        FilelistOperationService $operations
    ): RedirectResponse {
        $request->merge(['status_target' => $status]);
        $validated = $request->validate([
            'password_status_berkas' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'status_target' => ['required', 'integer', Rule::exists('statuses', 'id')],
        ]);

        $mfaSecret = config('services.mfa.secret');
        if (empty($mfaSecret)) {
            Alert::error('Gagal', 'MFA_SECRET belum dikonfigurasi');

            return redirect()->route('surat.berkas');
        }

        try {
            $isValidToken = (new Google2FA)->verifyKey(
                $mfaSecret,
                $validated['password_status_berkas']
            );
        } catch (Throwable) {
            $isValidToken = false;
        }

        if (! $isValidToken) {
            Alert::error('Gagal', 'Kode Token MFA salah');

            return redirect()->route('surat.berkas');
        }

        $result = $operations->transitionStatus($id, $status);

        if ($result === 'not_found') {
            Alert::error('Gagal', 'Berkas Tidak Ditemukan');
        } elseif ($result === 'locked') {
            Alert::error('Gagal', 'Status berkas yang sudah masuk proses alih media tidak dapat diubah');
        } elseif ($result === 'invalid_transition') {
            Alert::error('Gagal', 'Perubahan status berkas tidak mengikuti alur yang diizinkan');
        } else {
            Alert::success('Berhasil', 'Status Berkas Berhasil Diubah');
        }

        return redirect()->route('surat.berkas');
    }
}
