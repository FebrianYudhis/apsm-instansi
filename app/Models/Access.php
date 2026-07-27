<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Access extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function incomings()
    {
        return $this->hasMany(Incoming::class);
    }

    public function outcomings()
    {
        return $this->hasMany(Outcoming::class);
    }
}
