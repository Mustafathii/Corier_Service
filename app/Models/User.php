<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'company_name',
        'is_active',
        'driver_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    /**
     * ✅ Override method to check if user is active during authentication
     */
    public function validateForPassportPasswordGrant($password)
    {
        // Check if user is active before validating password
        if (!$this->is_active) {
            return false;
        }

        return $this->validatePassword($password);
    }

    /**
     * ✅ Override method for standard authentication
     */
    public function getAuthPassword()
    {
        // Return password only if user is active
        return $this->is_active ? $this->password : null;
    }

    // Relationships

    /**
     * Get shipments where this user is the seller
     */
    public function sellerShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'seller_id');
    }

    /**
     * Get shipments assigned to this user as driver
     */
    public function driverShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'driver_id');
    }

    /**
     * Get shipments created by this user
     */
    public function createdShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'created_by');
    }

    /**
     * If this user has a driver_id, get the driver user
     * This is for cases where a user might be linked to a driver
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get users that have this user as their driver
     */
    public function assignedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'driver_id');
    }

    // Role helper methods
    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }

    public function isDriver(): bool
    {
        return $this->hasRole('Driver');
    }

    public function isOperationsManager(): bool
    {
        return $this->hasRole('Operations Manager');
    }

    public function isSeller(): bool
    {
        return $this->hasRole('Seller');
    }

    // Scope methods
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDrivers($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('name', 'Driver');
        });
    }

    public function scopeSellers($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('name', 'Seller');
        });
    }

    public function scopeAdmins($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('name', 'Admin');
        });
    }

    public function scopeOperationsManagers($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('name', 'Operations Manager');
        });
    }

    // Accessor methods
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->isSeller() && $this->company_name) {
            return $this->company_name;
        }

        return $this->name;
    }

    public function getRoleNamesAttribute(): string
    {
        return $this->roles->pluck('name')->implode(', ');
    }

    // Business logic methods

    /**
     * Check if user can manage shipments
     */
    public function canManageShipments(): bool
    {
        return $this->hasAnyRole(['Admin', 'Operations Manager']);
    }

    /**
     * Check if user can view all shipments
     */
    public function canViewAllShipments(): bool
    {
        return $this->hasAnyRole(['Admin', 'Operations Manager']);
    }

    /**
     * Check if user can assign drivers
     */
    public function canAssignDrivers(): bool
    {
        return $this->hasAnyRole(['Admin', 'Operations Manager']);
    }

    /**
     * Check if user can update shipment status
     */
    public function canUpdateShipmentStatus(): bool
    {
        return $this->hasAnyRole(['Admin', 'Operations Manager', 'Driver']);
    }

    /**
     * Get pending shipments count for this user
     */
    public function getPendingShipmentsCount(): int
    {
        if ($this->isDriver()) {
            return $this->driverShipments()->where('status', 'pending')->count();
        }

        if ($this->isSeller()) {
            return $this->sellerShipments()->where('status', 'pending')->count();
        }

        if ($this->canViewAllShipments()) {
            return Shipment::where('status', 'pending')->count();
        }

        return 0;
    }

    /**
     * Get today's deliveries for driver
     */
    public function getTodayDeliveries()
    {
        if (!$this->isDriver()) {
            return collect();
        }

        return $this->driverShipments()
            ->whereDate('expected_delivery_date', today())
            ->whereIn('status', ['in_transit', 'out_for_delivery'])
            ->get();
    }

    public function customerInvoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function driverCommissions()
    {
        return $this->hasMany(Invoice::class, 'driver_id');
    }

    /**
     * Get COD amount to collect for driver
     */
    public function getCodAmountToCollect(): float
    {
        if (!$this->isDriver()) {
            return 0.0;
        }

        return $this->driverShipments()
            ->where('payment_method', 'cod')
            ->whereIn('status', ['in_transit', 'out_for_delivery'])
            ->sum('cod_amount');
    }

    /**
     * Get monthly earnings for driver
     */
    public function getMonthlyEarnings(): float
    {
        if (!$this->isDriver()) {
            return 0.0;
        }

        return $this->driverShipments()
            ->where('status', 'delivered')
            ->whereMonth('actual_delivery_date', now()->month)
            ->whereYear('actual_delivery_date', now()->year)
            ->sum('shipping_cost') * 0.1; // Assuming 10% commission
    }

    /**
     * Get seller's total shipments value
     */
    public function getTotalShipmentsValue(): float
    {
        if (!$this->isSeller()) {
            return 0.0;
        }

        return $this->sellerShipments()->sum('shipping_cost');
    }

    /**
     * Check if user can edit specific shipment
     */
    public function canEditShipment(Shipment $shipment): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isOperationsManager()) {
            return true;
        }

        if ($this->isSeller() && $shipment->seller_id === $this->id) {
            return $shipment->status === 'pending';
        }

        if ($this->isDriver() && $shipment->driver_id === $this->id) {
            return in_array($shipment->status, ['in_transit', 'out_for_delivery']);
        }

        return false;
    }

    /**
     * Check if user can view specific shipment
     */
    public function canViewShipment(Shipment $shipment): bool
    {
        if ($this->canViewAllShipments()) {
            return true;
        }

        if ($this->isSeller() && $shipment->seller_id === $this->id) {
            return true;
        }

        if ($this->isDriver() && $shipment->driver_id === $this->id) {
            return true;
        }

        return false;
    }

    /**
     * Get user's dashboard statistics
     */
    public function getDashboardStats(): array
    {
        $stats = [
            'total_shipments' => 0,
            'pending_shipments' => 0,
            'delivered_shipments' => 0,
            'in_transit_shipments' => 0,
        ];

        if ($this->isDriver()) {
            $shipments = $this->driverShipments();
            $stats['cod_to_collect'] = $this->getCodAmountToCollect();
            $stats['today_deliveries'] = $this->getTodayDeliveries()->count();
            $stats['monthly_earnings'] = $this->getMonthlyEarnings();
        } elseif ($this->isSeller()) {
            $shipments = $this->sellerShipments();
            $stats['total_value'] = $this->getTotalShipmentsValue();
        } else {
            $shipments = Shipment::query();
        }

        $stats['total_shipments'] = $shipments->count();
        $stats['pending_shipments'] = $shipments->where('status', 'pending')->count();
        $stats['delivered_shipments'] = $shipments->where('status', 'delivered')->count();
        $stats['in_transit_shipments'] = $shipments->whereIn('status', ['in_transit', 'out_for_delivery'])->count();

        return $stats;
    }

    // Boot method for setting default values
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if ($user->is_active === null) {
                $user->is_active = true;
            }
        });
    }
}
