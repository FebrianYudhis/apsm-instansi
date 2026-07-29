<?php

namespace App\Actions;

use App\Models\Outcoming;
use App\Services\DocumentService;
use App\Services\FilelistMutationLock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateOutgoingAction
{
    public function __construct(
        private DocumentService $documentService,
        private FilelistMutationLock $filelistMutationLock
    ) {}

    /**
     * @param  array{
     *     tanggal_surat: string,
     *     nomor_surat: string,
     *     tujuan: string,
     *     perihal: string,
     *     tahun: int,
     *     is_digital?: bool,
     *     is_srikandi: bool,
     *     access_id: int,
     *     filelist_id?: int|null
     * }  $data
     */
    public function handle(
        array $data,
        UploadedFile $document,
        string $filelistValidationKey = 'filelist_id'
    ): Outcoming {
        $isSrikandi = (bool) $data['is_srikandi'];
        $filelistId = $isSrikandi ? null : ($data['filelist_id'] ?? null);
        $documentPath = $this->documentService->storeOriginal(
            DocumentService::TYPE_OUTGOING,
            $document
        );

        $outgoing = new Outcoming([
            'tanggal_surat' => $data['tanggal_surat'],
            'nomor_surat' => $data['nomor_surat'],
            'tujuan' => $data['tujuan'],
            'perihal' => $data['perihal'],
            'url' => $documentPath,
            'tahun' => $data['tahun'],
            'is_digital' => $isSrikandi || (bool) ($data['is_digital'] ?? false),
            'is_srikandi' => $isSrikandi,
            'filelist_id' => $filelistId,
            'access_id' => $data['access_id'],
        ]);

        try {
            DB::transaction(function () use (
                $outgoing,
                $filelistId,
                $filelistValidationKey
            ): void {
                $this->filelistMutationLock->lock(
                    null,
                    $filelistId,
                    $filelistValidationKey
                );
                $outgoing->saveOrFail();
            });
        } catch (Throwable $exception) {
            $this->documentService->delete(
                DocumentService::TYPE_OUTGOING,
                $documentPath
            );

            throw $exception;
        }

        return $outgoing;
    }
}
