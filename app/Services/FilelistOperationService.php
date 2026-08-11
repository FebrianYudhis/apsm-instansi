<?php

namespace App\Services;

use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\Status;
use Illuminate\Support\Facades\DB;

class FilelistOperationService
{
    /**
     * @param  array<int, string>  $items
     * @return array{status: string, count?: int}
     */
    public function attachLetters(
        int $targetFilelistId,
        array $items
    ): array {
        [$incomingIds, $outcomingIds] = $this->separateLetterIds($items);

        if (count($incomingIds) + count($outcomingIds) !== count($items)) {
            return ['status' => 'letter_invalid'];
        }

        return DB::transaction(function () use (
            $targetFilelistId,
            $incomingIds,
            $outcomingIds
        ): array {
            $targetFilelist = Filelist::lockForUpdate()->find($targetFilelistId);

            if (
                ! $targetFilelist
                || (int) $targetFilelist->status_id !== 1
                || $targetFilelist->isAlihMediaLocked()
            ) {
                return ['status' => 'target_invalid'];
            }

            $incomingLetters = count($incomingIds) > 0
                ? Incoming::withTrashed()
                    ->whereIn('id', $incomingIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                : collect();
            $outgoingLetters = count($outcomingIds) > 0
                ? Outcoming::withTrashed()
                    ->whereIn('id', $outcomingIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                : collect();

            if (
                $incomingLetters->count() !== count($incomingIds)
                || $outgoingLetters->count() !== count($outcomingIds)
            ) {
                return ['status' => 'letter_invalid'];
            }

            $startYear = (int) config('app.start_year');
            $currentYear = now()->year;
            $hasInvalidLetter = $incomingLetters
                ->concat($outgoingLetters)
                ->contains(function ($letter) use ($startYear, $currentYear): bool {
                    return $letter->trashed()
                        || (int) $letter->tahun < $startYear
                        || (int) $letter->tahun > $currentYear
                        || $letter->filelist_id !== null
                        || (bool) $letter->is_srikandi
                        || $letter->url_watermarked !== null;
                });

            if ($hasInvalidLetter) {
                return ['status' => 'letter_invalid'];
            }

            foreach ($incomingLetters as $letter) {
                $letter->filelist_id = $targetFilelistId;
                $letter->saveOrFail();
            }

            foreach ($outgoingLetters as $letter) {
                $letter->filelist_id = $targetFilelistId;
                $letter->saveOrFail();
            }

            return [
                'status' => 'updated',
                'count' => $incomingLetters->count() + $outgoingLetters->count(),
            ];
        });
    }

    /**
     * @param  array<int, string>  $items
     * @return array{status: string, count?: int}
     */
    public function moveLetters(int $sourceFilelistId, int $targetFilelistId, array $items): array
    {
        [$incomingIds, $outcomingIds] = $this->separateLetterIds($items);

        return DB::transaction(function () use (
            $sourceFilelistId,
            $targetFilelistId,
            $incomingIds,
            $outcomingIds
        ): array {
            $filelists = Filelist::whereIn('id', [
                $sourceFilelistId,
                $targetFilelistId,
            ])->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $sourceFilelist = $filelists->get($sourceFilelistId);
            $targetFilelist = $filelists->get($targetFilelistId);

            if (
                ! $sourceFilelist
                || (int) $sourceFilelist->status_id !== 1
                || $sourceFilelist->isAlihMediaLocked()
            ) {
                return ['status' => 'source_invalid'];
            }

            if (
                ! $targetFilelist
                || (int) $targetFilelist->status_id !== 1
                || $targetFilelist->isAlihMediaLocked()
            ) {
                return ['status' => 'target_invalid'];
            }

            if ($sourceFilelistId === $targetFilelistId) {
                return ['status' => 'same_filelist'];
            }

            $incomingLetters = count($incomingIds) > 0
                ? Incoming::whereIn('id', $incomingIds)
                    ->where('filelist_id', $sourceFilelistId)
                    ->lockForUpdate()
                    ->get()
                : collect();

            if ($incomingLetters->count() !== count($incomingIds)) {
                return ['status' => 'incoming_invalid'];
            }

            $outgoingLetters = count($outcomingIds) > 0
                ? Outcoming::whereIn('id', $outcomingIds)
                    ->where('filelist_id', $sourceFilelistId)
                    ->lockForUpdate()
                    ->get()
                : collect();

            if ($outgoingLetters->count() !== count($outcomingIds)) {
                return ['status' => 'outcoming_invalid'];
            }

            $hasLockedLetter = $incomingLetters
                ->concat($outgoingLetters)
                ->contains(function ($letter) use ($sourceFilelist): bool {
                    $letter->setRelation('filelist', $sourceFilelist);

                    return $letter->isAlihMediaLocked();
                });

            if ($hasLockedLetter) {
                return ['status' => 'item_locked'];
            }

            foreach ($incomingLetters as $incomingLetter) {
                $incomingLetter->filelist_id = $targetFilelistId;
                $incomingLetter->saveOrFail();
            }

            foreach ($outgoingLetters as $outgoingLetter) {
                $outgoingLetter->filelist_id = $targetFilelistId;
                $outgoingLetter->saveOrFail();
            }

            return [
                'status' => 'updated',
                'count' => $incomingLetters->count() + $outgoingLetters->count(),
            ];
        });
    }

    public function releaseLetter(int $filelistId, string $type, int $letterId): string
    {
        return DB::transaction(function () use ($filelistId, $type, $letterId): string {
            $filelist = Filelist::lockForUpdate()->find($filelistId);
            if (! $filelist) {
                return 'filelist_not_found';
            }

            if ((int) $filelist->status_id !== 1 || $filelist->isAlihMediaLocked()) {
                return 'filelist_locked';
            }

            $letter = $type === 'masuk'
                ? Incoming::lockForUpdate()->find($letterId)
                : Outcoming::lockForUpdate()->find($letterId);

            if (! $letter || (int) $letter->filelist_id !== $filelistId) {
                return 'letter_not_found';
            }

            $letter->setRelation('filelist', $filelist);
            if ($letter->isAlihMediaLocked()) {
                return 'letter_locked';
            }

            $letter->filelist_id = null;
            $letter->saveOrFail();

            return 'updated';
        });
    }

    public function transitionStatus(int $filelistId, int $targetStatusId): string
    {
        return DB::transaction(function () use ($filelistId, $targetStatusId): string {
            $filelist = Filelist::with('status')->lockForUpdate()->find($filelistId);
            if (! $filelist) {
                return 'not_found';
            }

            if ($filelist->isAlihMediaLocked()) {
                return 'locked';
            }

            $targetStatus = Status::find($targetStatusId);
            if (
                ! $filelist->status
                || ! $targetStatus
                || ! $filelist->status->canTransitionTo($targetStatus)
            ) {
                return 'invalid_transition';
            }

            $filelist->status_id = $targetStatus->id;
            $filelist->saveOrFail();

            return 'updated';
        });
    }

    /**
     * @param  array<int, string>  $items
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function separateLetterIds(array $items): array
    {
        $incomingIds = [];
        $outcomingIds = [];

        foreach ($items as $item) {
            [$type, $id] = explode(':', $item);

            if ($type === 'masuk') {
                $incomingIds[] = (int) $id;
            } elseif ($type === 'keluar') {
                $outcomingIds[] = (int) $id;
            }
        }

        return [
            array_values(array_unique($incomingIds)),
            array_values(array_unique($outcomingIds)),
        ];
    }
}
