<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Governorate extends Model
{
    use HasFactory;

    protected $fillable = [
        'governorate_name_ar',
        'governorate_name_en',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // العلاقات
    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    // Accessors
    public function getDisplayNameAttribute()
    {
        return app()->getLocale() === 'ar'
            ? $this->governorate_name_ar
            : $this->governorate_name_en;
    }
}
