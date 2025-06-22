<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'payment_number',
        'amount',
        'payment_method',
        'status',
        'payment_date',
        'reference_number',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = $payment->generatePaymentNumber();
            }

            $payment->received_by = auth()->id();
        });

        static::created(function ($payment) {
            if ($payment->status === 'completed') {
                $payment->updateInvoiceBalance();
            }
        });
    }

    private function generatePaymentNumber(): string
    {
        $year = now()->year;
        $lastPayment = static::whereYear('created_at', $year)
                            ->latest('id')
                            ->first();

        $nextNumber = $lastPayment ?
            intval(substr($lastPayment->payment_number, -4)) + 1 : 1;

        return "PAY-{$year}-" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private function updateInvoiceBalance(): void
    {
        $invoice = $this->invoice;
        if ($invoice) {
            $invoice->paid_amount += $this->amount;
            $invoice->remaining_amount = $invoice->total_amount - $invoice->paid_amount;

            if ($invoice->remaining_amount <= 0) {
                $invoice->status = 'paid';
                $invoice->paid_at = now();
            }

            $invoice->save();
        }
    }
}
