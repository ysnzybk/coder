<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $guarded = ['id'];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class)->orderBy('name');
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }
}
