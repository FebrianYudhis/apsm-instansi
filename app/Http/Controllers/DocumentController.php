<?php

namespace App\Http\Controllers;

use App\Services\DocumentService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    private $documents;

    public function __construct(DocumentService $documents)
    {
        $this->documents = $documents;
    }

    public function admin(
        string $jenis,
        int $id,
        string $versi = DocumentService::VARIANT_DISPLAY,
        ?string $nama = null
    ) {
        $document = $this->documents->find($jenis, $id);
        abort_unless($document, 404);
        abort_if($this->documents->path($jenis, $document, $versi) === null, 404);

        $expectedName = $this->documents->descriptiveFileName($document, $versi);
        if ($nama !== $expectedName) {
            return redirect()->to(
                $this->documents->adminUrl($jenis, $document, $versi)
            );
        }

        return $this->documents->response($jenis, $document, $versi);
    }

    public function public(string $jenis, int $id, ?string $nama = null)
    {
        $document = $this->documents->find($jenis, $id);
        abort_unless($document, 404);
        abort_unless($this->documents->isGuestPublic($jenis, $document), 403);

        return $this->documents->response($jenis, $document);
    }

    public function temporary(Request $request, string $jenis, int $id, ?string $nama = null)
    {
        $document = $this->documents->find($jenis, $id);
        abort_unless($document, 404);

        $expiresAt = (int) $request->session()->get(
            $this->documents->grantSessionKey($jenis, $id),
            0
        );
        abort_if($expiresAt < now()->timestamp, 403);

        return $this->documents->response($jenis, $document);
    }
}
