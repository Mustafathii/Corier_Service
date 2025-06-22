<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
        'metadata',
        'description',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a history entry
     */
    public static function log(
        int $shipmentId,
        string $action,
        string $description,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'shipment_id' => $shipmentId,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'metadata' => array_merge([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ], $metadata ?? []),
            'description' => $description,
        ]);
    }
}
