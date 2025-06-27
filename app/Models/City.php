<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'governorate_id',
        'city_name_ar',
        'city_name_en',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    // ✅ العلاقة مع الزونز - مهمة جداً!
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    // ✅ Scope للمدن النشطة فقط
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ✅ Scope للمدن اللي عندها zones نشطة
    public function scopeWithActiveZones($query)
    {
        return $query->whereHas('zones', function($q) {
            $q->where('is_active', true);
        });
    }

    // ✅ Helper method للتحقق من وجود zones نشطة
    public function hasActiveZones(): bool
    {
        return $this->zones()->where('is_active', true)->exists();
    }

    // ✅ Helper method للحصول على الزونز النشطة
    public function getActiveZones()
    {
        return $this->zones()->where('is_active', true)->get();
    }
}
