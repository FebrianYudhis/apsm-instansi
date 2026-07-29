<?php

namespace App\Models;

use App\Models\Concerns\AuditsDeletion;
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Outcoming extends Model
{
    use AuditsDeletion, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tanggal_surat',
        'nomor_surat',
        'tujuan',
        'perihal',
        'url',
        'tahun',
        'is_digital',
        'is_srikandi',
        'url_watermarked',
        'access_id',
        'filelist_id',
    ];

    protected $casts = [
        'is_digital' => 'boolean',
        'is_srikandi' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
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

    public function scopePendingFiling(Builder $query): Builder
    {
        return $query
            ->whereNull('filelist_id')
            ->where('is_srikandi', false);
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
