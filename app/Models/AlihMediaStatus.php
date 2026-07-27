<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlihMediaStatus extends Model
{
    protected $guarded = [];

    public function filelists()
    {
        return $this->hasMany(Filelist::class);
    }
}
