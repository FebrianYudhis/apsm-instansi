<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonalAccessTokenRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PersonalAccessTokenController extends Controller
{
    public function index(Request $request): View
    {
        return view('app.api-tokens.index', [
            'judul' => 'Token API',
            'tokens' => $request->user()->tokens()->latest()->get(),
        ]);
    }

    public function store(StorePersonalAccessTokenRequest $request): RedirectResponse
    {
        $user = $request->user();
        $expiresAt = now()->addDays($request->integer('expires_in_days'));
        $newToken = $user->createToken(
            $request->string('name')->trim()->toString(),
            ['surat:create'],
            $expiresAt
        );

        activity('api-token')
            ->causedBy($user)
            ->event('created')
            ->withProperties([
                'token_id' => $newToken->accessToken->getKey(),
                'token_name' => $newToken->accessToken->name,
                'abilities' => $newToken->accessToken->abilities,
                'expires_at' => $expiresAt->toISOString(),
            ])
            ->log('Token API dibuat');

        Alert::success('Berhasil', 'Token API berhasil dibuat.');

        return redirect()
            ->route('api-tokens.index')
            ->with('plain_text_token', $newToken->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $personalAccessToken = $request->user()
            ->tokens()
            ->whereKey($token)
            ->firstOrFail();
        $tokenProperties = [
            'token_id' => $personalAccessToken->getKey(),
            'token_name' => $personalAccessToken->name,
        ];

        $personalAccessToken->delete();

        activity('api-token')
            ->causedBy($request->user())
            ->event('deleted')
            ->withProperties($tokenProperties)
            ->log('Token API dicabut');

        Alert::success('Berhasil', 'Token API berhasil dicabut.');

        return redirect()->route('api-tokens.index');
    }
}
