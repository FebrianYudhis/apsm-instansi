<?php

namespace App\Http\Controllers;

use App\Services\ActiveYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class YearController extends Controller
{
    public function __construct(private ActiveYear $activeYear) {}

    public function switch(Request $request, int $tahun): RedirectResponse
    {
        $startYear = (int) config('app.start_year', 2025);
        $currentYear = (int) now()->year;

        abort_unless($tahun >= $startYear && $tahun <= $currentYear, 404);

        $this->activeYear->select($tahun);

        Alert::success('Berhasil', 'Berhasil Pindah Ke Tahun '.$tahun);

        $redirectTo = $request->input('redirect_to');
        if ($this->isSafeInternalPath($redirectTo)) {
            return redirect()->to($redirectTo);
        }

        return redirect()->route('dashboard');
    }

    private function isSafeInternalPath(mixed $path): bool
    {
        return is_string($path)
            && str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && ! str_contains($path, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }
}
