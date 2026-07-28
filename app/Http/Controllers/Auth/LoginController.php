<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActiveYear;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/app';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(private ActiveYear $activeYear)
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        $startYear = (int) config('app.start_year', 2025);
        $currentYear = Carbon::now()->year;
        $years = range($currentYear, $startYear);

        $data = [
            'judul' => 'Masuk',
            'years' => $years,
            'tahun' => $currentYear,
        ];

        return view('auth.login', $data);
    }

    public function username()
    {
        return 'username';
    }

    protected function validateLogin(Request $request)
    {
        $startYear = (int) config('app.start_year', 2025);
        $currentYear = Carbon::now()->year;

        $request->validate([
            $this->username() => 'required|string',
            'password' => 'required|string',
            'tahun' => 'required|integer|between:'.$startYear.','.$currentYear,
        ]);
    }

    protected function authenticated(Request $request, $user)
    {
        $this->activeYear->select((int) $request->tahun);
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        Alert::error('Gagal', 'Username atau Password Salah !');

        return redirect()->route('login');
    }

    protected function loggedOut(Request $request)
    {
        Alert::success('Berhasil', 'Anda Berhasil Keluar');

        return redirect()->route('login');
    }
}
