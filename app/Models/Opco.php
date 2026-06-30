<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opco extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function opcoFiles(): HasMany
    {
        return $this->hasMany(OpcoFile::class);
    }
}
