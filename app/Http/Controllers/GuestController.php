<?php

namespace App\Http\Controllers;

use App\Models\Digital;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use PragmaRX\Google2FA\Google2FA;

class GuestController extends Controller
{
    private const SEARCH_PAGE_SIZE = 50;

    private $documents;

    public function __construct(DocumentService $documents)
    {
        $this->documents = $documents;
    }

    public function index()
    {
        $data = [
            'judul' => 'Beranda',
            'suratMasuk' => Incoming::count(),
            'suratKeluar' => Outcoming::count(),
            'suratDigital' => Digital::count(),
        ];

        return view('guest.index', $data);
    }

    public function masuk(Request $request)
    {
        if ($request->ajax()) {
            $data = $request->get('pencarian');
            if ($data == null || $data == '') {
                return null;
            } else {
                $suratMasuk = Incoming::where(function ($q) use ($data) {
                    $q->where('nomor_surat', 'LIKE', '%'.$data.'%')
                        ->orWhere('pengirim', 'LIKE', '%'.$data.'%')
                        ->orWhere('perihal', 'LIKE', '%'.$data.'%');
                })->select([
                    'id',
                    'nomor_agenda',
                    'tanggal_diterima',
                    'nomor_surat',
                    'pengirim',
                    'tanggal_surat',
                    'perihal',
                    'url',
                    'url_watermarked',
                    'tahun',
                    'is_srikandi',
                    'access_id',
                ])->with('access:id,sifat_akses')
                    ->orderBy('tahun', 'desc')
                    ->orderBy('nomor_agenda', 'desc')
                    ->paginate(self::SEARCH_PAGE_SIZE);

                $suratMasuk->setCollection(
                    $suratMasuk->getCollection()->transform(
                        fn ($item) => $this->prepareGuestLetter(
                            $item,
                            DocumentService::TYPE_INCOMING,
                            true
                        )
                    )
                );

                return $suratMasuk;
            }
        }

        $suratMasuk = Incoming::with('access')
            ->orderBy('tahun', 'desc')
            ->orderBy('nomor_agenda', 'desc')
            ->paginate(10);
        $suratMasuk->getCollection()->transform(fn ($item) => $this->prepareGuestLetter(
            $item,
            DocumentService::TYPE_INCOMING
        ));

        $data = [
            'judul' => 'List Surat Masuk',
            'suratMasuk' => $suratMasuk,
        ];

        return view('guest.masuk', $data);
    }

    public function keluar(Request $request)
    {
        if ($request->ajax()) {
            $data = $request->get('pencarian');
            if ($data == null || $data == '') {
                return null;
            } else {
                $suratKeluar = Outcoming::where(function ($q) use ($data) {
                    $q->where('nomor_surat', 'LIKE', '%'.$data.'%')
                        ->orWhere('tujuan', 'LIKE', '%'.$data.'%')
                        ->orWhere('perihal', 'LIKE', '%'.$data.'%');
                })->select([
                    'id',
                    'tanggal_surat',
                    'nomor_surat',
                    'tujuan',
                    'perihal',
                    'url',
                    'url_watermarked',
                    'tahun',
                    'is_digital',
                    'is_srikandi',
                    'access_id',
                ])->with('access:id,sifat_akses')
                    ->orderBy('tahun', 'desc')
                    ->orderBy('tanggal_surat', 'desc')
                    ->paginate(self::SEARCH_PAGE_SIZE);

                $suratKeluar->setCollection(
                    $suratKeluar->getCollection()->transform(
                        fn ($item) => $this->prepareGuestLetter(
                            $item,
                            DocumentService::TYPE_OUTGOING,
                            true
                        )
                    )
                );

                return $suratKeluar;
            }
        }

        $suratKeluar = Outcoming::with('access')
            ->orderBy('tahun', 'desc')
            ->orderBy('tanggal_surat', 'desc')
            ->paginate(10);
        $suratKeluar->getCollection()->transform(fn ($item) => $this->prepareGuestLetter(
            $item,
            DocumentService::TYPE_OUTGOING
        ));

        $data = [
            'judul' => 'List Surat Keluar',
            'suratKeluar' => $suratKeluar,
        ];

        return view('guest.keluar', $data);
    }

    public function digital(Request $request)
    {
        if ($request->ajax()) {
            $data = $request->get('pencarian');
            if ($data == null || $data == '') {
                return null;
            } else {
                $suratDigital = Digital::where('perihal', 'LIKE', '%'.$data.'%')
                    ->select(['id', 'perihal', 'url'])
                    ->orderBy('perihal')
                    ->orderBy('id')
                    ->paginate(self::SEARCH_PAGE_SIZE);
                $suratDigital->setCollection(
                    $suratDigital->getCollection()->transform(
                        fn ($item) => $this->prepareGuestDigital($item, true)
                    )
                );

                return $suratDigital;
            }
        }

        $suratDigital = Digital::orderBy('perihal')
            ->orderBy('id')
            ->paginate(10);
        $suratDigital->getCollection()->transform(
            fn ($item) => $this->prepareGuestDigital($item)
        );

        $data = [
            'judul' => 'List Surat Digital',
            'suratDigital' => $suratDigital,
        ];

        return view('guest.digital', $data);
    }

    public function bukaSurat(Request $request)
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in([
                DocumentService::TYPE_INCOMING,
                DocumentService::TYPE_OUTGOING,
            ])],
            'id' => ['required', 'integer', 'min:1'],
            'password' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $google2fa = new Google2FA;
        $valid = false;
        $mfaSecret = config('services.mfa.secret');

        try {
            if (! empty($mfaSecret)) {
                $valid = $google2fa->verifyKey($mfaSecret, $data['password']);
            }
        } catch (\Exception $e) {
            $valid = false;
        }

        if (! $valid) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Kode MFA salah atau konfigurasi MFA belum tersedia.',
            ]);
        }

        $surat = $this->documents->find($data['jenis'], (int) $data['id']);
        if (! $surat) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Surat tidak ditemukan.',
            ], 404);
        }

        if (! $this->documents->exists($data['jenis'], $surat)) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'File dokumen tidak ditemukan.',
            ], 404);
        }

        $expiresAt = now()->addMinutes(max(1, (int) config('documents.guest_link_minutes')));
        $request->session()->put(
            $this->documents->grantSessionKey($data['jenis'], (int) $data['id']),
            $expiresAt->timestamp
        );

        return response()->json([
            'isSuccess' => true,
            'url' => URL::temporarySignedRoute(
                'document.temporary',
                $expiresAt,
                [
                    'jenis' => $data['jenis'],
                    'id' => (int) $data['id'],
                    'nama' => $this->documents->descriptiveFileName(
                        $surat,
                        DocumentService::VARIANT_DISPLAY
                    ),
                ]
            ),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    private function prepareGuestLetter($item, string $type, bool $forSearchResponse = false)
    {
        $item->requires_mfa = ! $item->isPubliclyAccessible();
        $item->access_state = $item->access_id === null
            ? 'undefined'
            : ($item->requires_mfa ? 'restricted' : 'public');
        $item->document_url = ! $item->requires_mfa
            && $this->documents->exists($type, $item)
                ? $this->documents->publicUrl($type, $item)
                : null;

        if ($forSearchResponse) {
            $common = [
                'id' => (int) $item->id,
                'perihal' => $item->perihal,
                'nomor_surat' => $item->nomor_surat,
                'tahun' => $item->tahun,
                'tanggal_surat' => $item->tanggal_surat,
                'requires_mfa' => (bool) $item->requires_mfa,
                'access_state' => $item->access_state,
                'document_url' => $item->document_url,
            ];

            if ($type === DocumentService::TYPE_INCOMING) {
                return array_merge($common, [
                    'nomor_agenda' => $item->nomor_agenda,
                    'tanggal_diterima' => $item->tanggal_diterima,
                    'pengirim' => $item->pengirim,
                    'is_srikandi' => (bool) $item->is_srikandi,
                ]);
            }

            return array_merge($common, [
                'tujuan' => $item->tujuan,
                'is_digital' => (bool) $item->is_digital,
                'is_srikandi' => (bool) $item->is_srikandi,
            ]);
        }

        return $item;
    }

    private function prepareGuestDigital($item, bool $forSearchResponse = false)
    {
        $item->document_url = $this->documents->exists(
            DocumentService::TYPE_DIGITAL,
            $item
        ) ? $this->documents->publicUrl(DocumentService::TYPE_DIGITAL, $item) : null;

        if ($forSearchResponse) {
            return [
                'perihal' => $item->perihal,
                'document_url' => $item->document_url,
            ];
        }

        return $item;
    }
}
