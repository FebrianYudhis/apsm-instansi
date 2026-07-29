<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Contracts\Activity;

trait AuditsDeletion
{
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function deleteWithAudit(User $deletedBy, string $deletionReason): bool
    {
        $this->forceFill([
            'deleted_by_user_id' => $deletedBy->getKey(),
            'deletion_reason' => $deletionReason,
        ]);

        static::withoutEvents(function (): void {
            $this->saveOrFail();
        });

        return (bool) $this->delete();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($eventName !== 'deleted') {
            return;
        }

        $deletedBy = $this->deletedBy;

        if ($deletedBy) {
            $activity->causer()->associate($deletedBy);
        }

        $activity->properties = $activity->properties->put(
            'attributes',
            array_merge($activity->properties->get('attributes', []), [
                'deleted_by_user_id' => $this->deleted_by_user_id,
                'deleted_by_name' => $deletedBy?->name,
                'deletion_reason' => $this->deletion_reason,
            ])
        );
    }
}
