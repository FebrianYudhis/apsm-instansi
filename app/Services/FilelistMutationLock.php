<?php

namespace App\Services;

use App\Models\Filelist;
use App\Models\Status;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FilelistMutationLock
{
    public function lock(
        ?int $currentFilelistId,
        ?int $targetFilelistId,
        string $validationKey = 'pemberkasan'
    ): Collection {
        $ids = array_values(array_unique(array_filter([
            $currentFilelistId,
            $targetFilelistId,
        ])));

        $filelists = count($ids) > 0
            ? Filelist::with('status')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
            : collect();

        if ($currentFilelistId !== null) {
            $current = $filelists->get($currentFilelistId);
            if (! $current || $current->isAlihMediaLocked()) {
                throw ValidationException::withMessages([
                    $validationKey => 'Berkas asal sudah masuk proses alih media atau tidak valid.',
                ]);
            }
        }

        if ($targetFilelistId !== null) {
            $target = $filelists->get($targetFilelistId);
            if (
                ! $target
                || optional($target->status)->nama_status !== Status::ACTIVE
                || $target->isAlihMediaLocked()
            ) {
                throw ValidationException::withMessages([
                    $validationKey => 'Berkas tujuan sudah masuk proses alih media atau tidak valid.',
                ]);
            }
        }

        return $filelists;
    }
}
