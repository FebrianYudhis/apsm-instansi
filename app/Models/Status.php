<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    public const ACTIVE = 'Aktif';

    public const PROPOSE_TRANSFER = 'Usul Pindah UP ke UK';

    public const INACTIVE = 'Inaktif';

    public const PROPOSE_DESTROY = 'Usul Musnah';

    public const DESTROYED = 'Musnah';

    public const PROPOSE_PERMANENT = 'Usul Permanen';

    public const PERMANENT = 'Permanen';

    protected $guarded = [];

    public function filelists()
    {
        return $this->hasMany(Filelist::class);
    }

    public function canTransitionTo(Status $target): bool
    {
        $allowedTransitions = [
            self::ACTIVE => [self::PROPOSE_TRANSFER],
            self::PROPOSE_TRANSFER => [self::ACTIVE, self::INACTIVE],
            self::INACTIVE => [self::PROPOSE_TRANSFER, self::PROPOSE_DESTROY, self::PROPOSE_PERMANENT],
            self::PROPOSE_DESTROY => [self::INACTIVE, self::DESTROYED],
            self::PROPOSE_PERMANENT => [self::INACTIVE, self::PERMANENT],
            self::DESTROYED => [],
            self::PERMANENT => [],
        ];

        return in_array(
            $target->nama_status,
            $allowedTransitions[$this->nama_status] ?? [],
            true
        );
    }
}
