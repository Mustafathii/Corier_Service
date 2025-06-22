<?php

// app/Models/Invoice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'type',
        'status',
        'customer_id',
        'driver_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'issue_date',
        'due_date',
        'paid_at',
        'notes',
        'payment_methods',
        'period_from',
        'period_to',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'payment_methods' => 'array',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Scopes
    public function scopeCustomerInvoices($query)
    {
        return $query->where('type', 'customer');
    }

    public function scopeDriverCommissions($query)
    {
        return $query->where('type', 'driver_commission');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
                    ->orWhere(function($q) {
                        $q->where('status', 'sent')
                          ->where('due_date', '<', now());
                    });
    }

    // Helper Methods
    public function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $lastInvoice = static::whereYear('created_at', $year)
                           ->where('type', $this->type)
                           ->latest('id')
                           ->first();

        $nextNumber = $lastInvoice ?
            intval(substr($lastInvoice->invoice_number, -4)) + 1 : 1;

        $prefix = $this->type === 'customer' ? 'INV' : 'COM';

        return "{$prefix}-{$year}-" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotals(): void
{
    $this->subtotal = $this->items()->sum('total_price');
    $this->tax_amount = ($this->subtotal * $this->tax_rate) / 100;
    $this->total_amount = $this->subtotal + $this->tax_amount - $this->discount_amount;

    if ($this->remaining_amount === null || $this->remaining_amount === 0) {
        $this->remaining_amount = $this->total_amount - ($this->paid_amount ?? 0);
    }
}

    public function addPayment(float $amount, string $method, array $data = []): Payment
    {
        $payment = $this->payments()->create([
            'payment_number' => $this->generatePaymentNumber(),
            'amount' => $amount,
            'payment_method' => $method,
            'payment_date' => now(),
            'status' => 'completed',
            'notes' => $data['notes'] ?? null,
            'reference_number' => $data['reference'] ?? null,
            'received_by' => auth()->id(),
        ]);

        // Update invoice paid amount
        $this->paid_amount += $amount;
        $this->remaining_amount = $this->total_amount - $this->paid_amount;

        // Update status
        if ($this->remaining_amount <= 0) {
            $this->status = 'paid';
            $this->paid_at = now();
        }

        $this->save();

        return $payment;
    }

    public function isOverdue(): bool
    {
        return $this->due_date < now() && $this->status !== 'paid';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->remaining_amount <= 0;
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'sent' => 'info',
            'paid' => 'success',
            'overdue' => 'danger',
            'cancelled' => 'warning',
            default => 'gray'
        };
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = $invoice->generateInvoiceNumber();
            }
        });
    }

    private function generatePaymentNumber(): string
    {
        $year = now()->year;
        $lastPayment = Payment::whereYear('created_at', $year)
                            ->latest('id')
                            ->first();

        $nextNumber = $lastPayment ?
            intval(substr($lastPayment->payment_number, -4)) + 1 : 1;

        return "PAY-{$year}-" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
