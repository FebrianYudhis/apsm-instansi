<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class YearController extends Controller
{
    public function switch(Request $request, int $tahun)
    {
        $startYear = (int) config('app.start_year', 2025);
        $currentYear = (int) now()->year;

        abort_unless($tahun >= $startYear && $tahun <= $currentYear, 404);

        $request->user()->forceFill(['tahun' => $tahun])->save();

        Alert::success('Berhasil', 'Berhasil Pindah Ke Tahun '.$tahun);

        $redirectTo = $request->input('redirect_to');
        if (is_string($redirectTo) && preg_match('#^/surat/(masuk|keluar)/edit/[0-9]+$#D', $redirectTo) === 1) {
            return redirect()->to($redirectTo);
        }

        return redirect()->route('surat.masuk');
    }
}
