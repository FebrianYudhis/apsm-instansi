<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlihMediaStatus extends Model
{
    protected $fillable = [
        'nama_status',
    ];

    public function filelists()
    {
        return $this->hasMany(Filelist::class);
    }
}
