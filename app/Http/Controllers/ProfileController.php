<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

class ProfileController extends Controller
{
    public function edit()
    {
        $data = [
            'judul' => 'Profil Saya',
        ];

        return view('app.profile.edit', $data);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'current_password' => 'required_with:password|current_password',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user->name = $request->name;
        $user->username = $request->username;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($user->save()) {
            Alert::success('Berhasil', 'Profil berhasil diperbarui');

            return redirect()->route('profil.edit');
        } else {
            Alert::error('Gagal', 'Profil gagal diperbarui');

            return redirect()->route('profil.edit');
        }
    }
}
