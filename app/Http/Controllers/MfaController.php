<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class MfaController extends Controller
{
    public function index()
    {
        $google2fa = new Google2FA;
        $secret = config('services.mfa.secret');
        $isGenerated = false;

        if (! $secret) {
            $secret = $google2fa->generateSecretKey();
            $isGenerated = true;
        }

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'APSM',
            'Sistem Persuratan',
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(250, 2),
            new SvgImageBackEnd
        );
        $qrSvg = (new Writer($renderer))->writeString($qrCodeUrl);
        $qrImage = 'data:image/svg+xml;base64,'.base64_encode($qrSvg);

        $data = [
            'judul' => 'Pengaturan Multi-Factor Authentication (MFA)',
            'secret' => $secret,
            'qrImage' => $qrImage,
            'isGenerated' => $isGenerated,
        ];

        return view('app.mfa.index', $data);
    }
}
