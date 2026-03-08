<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'course_id', 'coupon_id', 'transaction_id',
        'amount', 'discount_amount', 'currency', 'payment_method',
        'payment_gateway', 'status', 'gateway_response',
        'paid_at', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('payment_gateway', $gateway);
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    public function getFormattedDiscountAttribute(): string
    {
        return '$' . number_format($this->discount_amount, 2);
    }

    public function getNetAmountAttribute(): float
    {
        return $this->amount - $this->discount_amount;
    }

    public function getFormattedNetAmountAttribute(): string
    {
        return '$' . number_format($this->net_amount, 2);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsRefundedAttribute(): bool
    {
        return $this->status === 'refunded';
    }

    public function getFormattedPaidDateAttribute(): ?string
    {
        return $this->paid_at?->format('M d, Y h:i A');
    }

    // Events
    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->transaction_id)) {
                $payment->transaction_id = 'TXN-' . strtoupper(\Str::random(12));
            }
            if (empty($payment->currency)) {
                $payment->currency = 'USD';
            }
        });

        static::updating(function (Payment $payment) {
            if ($payment->isDirty('status') && $payment->status === 'completed' && is_null($payment->paid_at)) {
                $payment->paid_at = now();
            }
            if ($payment->isDirty('status') && $payment->status === 'refunded' && is_null($payment->refunded_at)) {
                $payment->refunded_at = now();
            }
        });
    }
}
