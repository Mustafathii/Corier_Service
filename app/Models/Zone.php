<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'governorate_id',
        'city_id',
        'zone_name',
        'shipping_cost',
        'express_cost',
        'same_day_cost',
        'cod_fee',
        'estimated_delivery_days',
        'is_active',
        'notes'
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'express_cost' => 'decimal:2',
        'same_day_cost' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'is_active' => 'boolean',
        'estimated_delivery_days' => 'integer'
    ];

    // ✅ إضافة الـ method المفقود
    public function getCostByType(string $type): float
    {
        return match($type) {
            'express' => $this->express_cost ?? ($this->shipping_cost * 1.5),
            'same_day' => $this->same_day_cost ?? ($this->shipping_cost * 2),
            'standard' => $this->shipping_cost,
            default => $this->shipping_cost,
        };
    }

    // Scope للمناطق النشطة فقط
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope للمناطق غير النشطة فقط
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    // العلاقات
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    // Accessors لحساب الأسعار التلقائية (backup)
    public function getExpressCostAttribute($value)
    {
        return $value ?? ($this->shipping_cost * 1.5);
    }

    public function getSameDayCostAttribute($value)
    {
        return $value ?? ($this->shipping_cost * 2);
    }

    // Method للتحقق من كون المنطقة نشطة
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    // Method للحصول على معلومات الشحن الكاملة
    public function getShippingInfo(): array
    {
        return [
            'zone_id' => $this->id,
            'zone_name' => $this->zone_name,
            'governorate' => $this->governorate->governorate_name_en,
            'city' => $this->city->city_name_en,
            'standard_cost' => $this->shipping_cost,
            'express_cost' => $this->getCostByType('express'),
            'same_day_cost' => $this->getCostByType('same_day'),
            'cod_fee' => $this->cod_fee,
            'estimated_delivery_days' => $this->estimated_delivery_days,
            'is_active' => $this->is_active
        ];
    }
}
