<?php

namespace App\Models;

use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Incoming extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'nomor_agenda',
        'tanggal_diterima',
        'nomor_surat',
        'pengirim',
        'tanggal_surat',
        'perihal',
        'url',
        'tahun',
        'is_srikandi',
        'url_watermarked',
        'access_id',
        'filelist_id',
    ];

    protected $casts = [
        'is_srikandi' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function (Incoming $incoming) {
            if ($incoming->is_srikandi) {
                $incoming->nomor_agenda = null;
                $incoming->filelist_id = null;
            }
        });
    }

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
            DocumentService::TYPE_INCOMING,
            $this,
            DocumentService::VARIANT_WATERMARK
        );
    }
}
