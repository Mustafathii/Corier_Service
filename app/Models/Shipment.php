<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_number',
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

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
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

    // Boot method for auto-generating tracking number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($shipment) {
            if (empty($shipment->tracking_number)) {
                $shipment->tracking_number = 'TRK' . strtoupper(\Illuminate\Support\Str::random(10));
            }

            // If using existing seller, populate sender fields from seller data
            if ($shipment->is_existing_seller && $shipment->seller_id) {
                $seller = User::find($shipment->seller_id);
                if ($seller) {
                    $shipment->sender_name = $seller->name;
                    $shipment->sender_phone = $seller->phone ?? 'N/A';
                    $shipment->sender_address = $seller->address ?? 'N/A';
                    $shipment->sender_city = 'N/A'; // You might want to add city to users table
                }
            }

            if (auth()->check()) {
                $shipment->created_by = auth()->id();
            }
        });
    }
}
