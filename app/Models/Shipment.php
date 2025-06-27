<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\BarcodeService;
use Illuminate\Support\Facades\DB;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_number',
        'barcode_svg',
        'status',
        'is_existing_seller',
        'seller_id',
        'sender_name',
        'sender_phone',
        'sender_address',
        'sender_city',
        'receiver_name',
        'receiver_phone',
        'receiver_address',
        'receiver_city',
        'package_type',
        'weight',
        'description',
        'declared_value',
        'pickup_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'shipment_type',
        'governorate_id',
        'city_id',
        'zone_id',
        'quantity',
        'payment_method',
        'shipping_cost',
        'cod_amount',
        'driver_id',
        'notes',
        'internal_notes',
        'created_by',
    ];

    protected $casts = [
        'is_existing_seller' => 'boolean',
        'pickup_date' => 'datetime',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'datetime',
        'weight' => 'decimal:2',
        'declared_value' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'cod_amount' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // Relationships
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ShipmentHistory::class)->orderByDesc('created_at');
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'picked_up' => 'Picked Up',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'canceled' => 'Canceled',
            'failed_delivery' => 'Failed Delivery',
            'returned' => 'Returned',
            default => $this->status,
        };
    }

    public function getShipmentTypeLabelAttribute(): string
    {
        return match($this->shipment_type) {
            'express' => 'Express',
            'standard' => 'Standard',
            'same_day' => 'Same Day',
            default => $this->shipment_type,
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'cod' => 'Cash on Delivery',
            'prepaid' => 'Prepaid',
            'electronic_wallet' => 'Electronic Wallet',
            default => $this->payment_method,
        };
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeForSeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeCod($query)
    {
        return $query->where('payment_method', 'cod');
    }

    // Methods
    public function canBeEditedBy(User $user): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->hasRole('Operations Manager')) {
            return true;
        }

        if ($user->hasRole('Seller') && $this->seller_id === $user->id) {
            return $this->status === 'pending';
        }

        if ($user->hasRole('Driver') && $this->driver_id === $user->id) {
            return in_array($this->status, ['in_transit', 'out_for_delivery']);
        }

        return false;
    }

    public function canUpdateStatus(User $user): bool
    {
        if ($user->hasRole(['Admin', 'Operations Manager'])) {
            return true;
        }

        if ($user->hasRole('Driver') && $this->driver_id === $user->id) {
            return true;
        }

        return false;
    }

    public function getTotalAmount(): float
    {
        $total = $this->shipping_cost;

        if ($this->payment_method === 'cod' && $this->cod_amount) {
            $total += $this->cod_amount;
        }

        return $total;
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInTransit(): bool
    {
        return in_array($this->status, ['in_transit', 'out_for_delivery']);
    }

    public function governorate()
{
    return $this->belongsTo(\App\Models\Governorate::class);
}

public function city()
{
    return $this->belongsTo(\App\Models\City::class);
}

public function zone()
{
    return $this->belongsTo(\App\Models\Zone::class);
}

// إضافة Accessors:
public function getFullLocationAttribute()
{
    $parts = [];

    if ($this->city) {
        $parts[] = $this->city->city_name_en;
    } elseif ($this->receiver_city) {
        $parts[] = $this->receiver_city;
    }

    if ($this->governorate) {
        $parts[] = $this->governorate->governorate_name_en;
    }

    return implode(', ', $parts) ?: 'Not specified';
}

public function getLocationDisplayAttribute()
{
    if ($this->zone) {
        return $this->zone->zone_name . ' (' . $this->governorate->governorate_name_en . ')';
    }

    return $this->full_location;
}


    // Boot method with Barcode generation
   protected static function boot()
{
    parent::boot();

    static::saving(function ($shipment) {
        // Auto-update receiver_city when city is selected
        if ($shipment->isDirty('city_id') && $shipment->city_id) {
            $city = \App\Models\City::find($shipment->city_id);
            if ($city && empty($shipment->receiver_city)) {
                $shipment->receiver_city = $city->city_name_en;
            }
        }

        // Auto-update shipping cost when zone changes
        if ($shipment->isDirty('zone_id') && $shipment->zone_id && !$shipment->isDirty('shipping_cost')) {
            $zone = \App\Models\Zone::find($shipment->zone_id);
            if ($zone) {
                $shipment->shipping_cost = $zone->getCostByType($shipment->shipment_type ?? 'standard');
            }
        }

        // Auto-update shipping cost when shipment type changes
        if ($shipment->isDirty('shipment_type') && $shipment->zone_id && !$shipment->isDirty('shipping_cost')) {
            $zone = \App\Models\Zone::find($shipment->zone_id);
            if ($zone) {
                $shipment->shipping_cost = $zone->getCostByType($shipment->shipment_type);
            }
        }

        // Auto-update expected delivery date
        if (($shipment->isDirty('zone_id') || $shipment->isDirty('shipment_type'))
            && $shipment->zone_id
            && !$shipment->isDirty('expected_delivery_date')) {
            $zone = \App\Models\Zone::find($shipment->zone_id);
            if ($zone) {
                $shipment->expected_delivery_date = now()->addDays($zone->estimated_delivery_days)->format('Y-m-d');
            }
        }
    });
}

// إضافة Scopes مفيدة:
public function scopeByGovernorate($query, $governorateId)
{
    return $query->where('governorate_id', $governorateId);
}

public function scopeByCity($query, $cityId)
{
    return $query->where('city_id', $cityId);
}

public function scopeByZone($query, $zoneId)
{
    return $query->where('zone_id', $zoneId);
}

public function scopeWithLocation($query)
{
    return $query->with(['governorate', 'city', 'zone']);
}
}
