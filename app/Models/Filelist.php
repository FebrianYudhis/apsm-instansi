<?php

namespace App\Models;

use App\Models\Concerns\AuditsDeletion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Filelist extends Model
{
    use AuditsDeletion, HasFactory, LogsActivity, SoftDeletes;

    public const ALIH_MEDIA_PROCESSING = 1;

    public const ALIH_MEDIA_DONE = 2;

    public const ALIH_MEDIA_FAILED = 3;

    public const ALIH_MEDIA_CLOSED = 4;

    protected $fillable = [
        'classification_id',
        'nama_berkas',
        'status_id',
        'retensi_aktif',
        'retensi_inaktif',
        'keterangan_akhir',
        'alih_media_status_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

    public function classification()
    {
        return $this->belongsTo(Classification::class);
    }

    public function outcomings()
    {
        return $this->hasMany(Outcoming::class);
    }

    public function incomings()
    {
        return $this->hasMany(Incoming::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function alihMediaStatus()
    {
        return $this->belongsTo(AlihMediaStatus::class);
    }

    public function isAlihMediaLocked(): bool
    {
        return $this->alih_media_status_id !== null;
    }
}
