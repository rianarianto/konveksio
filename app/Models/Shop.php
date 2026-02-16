<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\ShopScope);
    }
}
