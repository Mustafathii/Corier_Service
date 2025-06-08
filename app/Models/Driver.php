<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'license_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    // Relationship to shipments
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    // Active shipments (not delivered, canceled, or returned)
    public function activeShipments(): HasMany
    {
        return $this->hasMany(Shipment::class)
            ->whereNotIn('status', ['delivered', 'canceled', 'returned']);
    }

    // Relationship to user account
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'driver_id');
    }
}
