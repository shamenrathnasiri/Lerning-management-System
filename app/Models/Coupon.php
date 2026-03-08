<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'type', 'value', 'min_order_amount',
        'max_uses', 'used_count', 'max_uses_per_user',
        'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'max_uses_per_user' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses');
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    // Accessors
    public function getIsValidAttribute(): bool
    {
        if (!$this->is_active) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses && $this->used_count >= $this->max_uses) return false;
        return true;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getFormattedValueAttribute(): string
    {
        return $this->type === 'percentage'
            ? $this->value . '%'
            : '$' . number_format($this->value, 2);
    }

    public function getRemainingUsesAttribute(): ?int
    {
        return $this->max_uses ? max(0, $this->max_uses - $this->used_count) : null;
    }

    /**
     * Calculate the discount amount for a given order total.
     */
    public function calculateDiscount(float $orderTotal): float
    {
        if ($this->min_order_amount && $orderTotal < $this->min_order_amount) {
            return 0;
        }

        return $this->type === 'percentage'
            ? round($orderTotal * ($this->value / 100), 2)
            : min($this->value, $orderTotal);
    }

    /**
     * Check if a user can use this coupon.
     */
    public function canBeUsedBy(int $userId): bool
    {
        if (!$this->is_valid) return false;

        $userUsage = $this->payments()->where('user_id', $userId)->count();
        return $userUsage < $this->max_uses_per_user;
    }

    // Events
    protected static function booted(): void
    {
        static::creating(function (Coupon $coupon) {
            $coupon->code = strtoupper($coupon->code);
        });
    }
}
