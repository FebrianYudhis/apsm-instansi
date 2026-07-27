<?php

namespace App\Models;

use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Outcoming extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_digital' => 'boolean',
        'is_srikandi' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logOnlyDirty();
    }

    public function filelist()
    {
        return $this->belongsTo(Filelist::class);
    }

    public function access()
    {
        return $this->belongsTo(Access::class);
    }

    public function isPubliclyAccessible(): bool
    {
        return optional($this->access)->sifat_akses === 'Biasa';
    }

    public function isAlihMediaLocked(): bool
    {
        if (! empty($this->url_watermarked)) {
            return true;
        }

        return optional($this->filelist)->alih_media_status_id !== null;
    }

    public function hasExistingWatermarkedFile(): bool
    {
        if (empty($this->url_watermarked)) {
            return false;
        }

        return app(DocumentService::class)->exists(
            DocumentService::TYPE_OUTGOING,
            $this,
            DocumentService::VARIANT_WATERMARK
        );
    }
}
