<?php

namespace App\Actions;

use App\Models\Incoming;
use App\Services\DocumentService;
use App\Services\FilelistMutationLock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateIncomingAction
{
    public function __construct(
        private DocumentService $documentService,
        private FilelistMutationLock $filelistMutationLock
    ) {}

    /**
     * @param  array{
     *     nomor_agenda?: int|null,
     *     tanggal_diterima: string,
     *     nomor_surat: string,
     *     pengirim: string,
     *     tanggal_surat: string|null,
     *     perihal: string,
     *     tahun: int,
     *     is_srikandi: bool,
     *     access_id: int,
     *     filelist_id?: int|null
     * }  $data
     */
    public function handle(
        array $data,
        UploadedFile $document,
        string $filelistValidationKey = 'filelist_id',
        string $agendaValidationKey = 'nomor_agenda'
    ): Incoming {
        $isSrikandi = (bool) $data['is_srikandi'];
        $agendaNumber = $isSrikandi ? null : $data['nomor_agenda'];
        $filelistId = $isSrikandi ? null : ($data['filelist_id'] ?? null);
        $documentPath = $this->documentService->storeOriginal(
            DocumentService::TYPE_INCOMING,
            $document
        );

        $incoming = new Incoming([
            'nomor_agenda' => $agendaNumber,
            'tanggal_diterima' => $data['tanggal_diterima'],
            'nomor_surat' => $data['nomor_surat'],
            'pengirim' => $data['pengirim'],
            'tanggal_surat' => $data['tanggal_surat'] ?? null,
            'perihal' => $data['perihal'],
            'url' => $documentPath,
            'tahun' => $data['tahun'],
            'is_srikandi' => $isSrikandi,
            'filelist_id' => $filelistId,
            'access_id' => $data['access_id'],
        ]);

        try {
            DB::transaction(function () use (
                $incoming,
                $agendaNumber,
                $filelistId,
                $filelistValidationKey,
                $agendaValidationKey
            ): void {
                $this->filelistMutationLock->lock(
                    null,
                    $filelistId,
                    $filelistValidationKey
                );
                $this->ensureAgendaAvailable(
                    $agendaNumber,
                    (int) $incoming->tahun,
                    $agendaValidationKey
                );
                $incoming->saveOrFail();
            });
        } catch (Throwable $exception) {
            $this->documentService->delete(
                DocumentService::TYPE_INCOMING,
                $documentPath
            );

            throw $exception;
        }

        return $incoming;
    }

    private function ensureAgendaAvailable(
        ?int $agendaNumber,
        int $year,
        string $validationKey
    ): void {
        if ($agendaNumber === null) {
            return;
        }

        if (
            Incoming::withTrashed()
                ->where('nomor_agenda', $agendaNumber)
                ->where('tahun', $year)
                ->lockForUpdate()
                ->exists()
        ) {
            throw ValidationException::withMessages([
                $validationKey => 'Nomor agenda sudah digunakan pada tahun tersebut.',
            ]);
        }
    }
}
