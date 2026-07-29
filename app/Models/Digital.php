<?php

namespace App\Models;

use App\Models\Concerns\AuditsDeletion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Digital extends Model
{
    use AuditsDeletion, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'perihal',
        'url',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }
}
