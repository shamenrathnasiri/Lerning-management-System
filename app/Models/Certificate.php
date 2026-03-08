<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'course_id',
        'certificate_number',
        'title',
        'pdf_path',
        'issued_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    /**
     * User who earned this certificate.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Course this certificate is for.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    /**
     * Scope to valid (non-expired) certificates.
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to expired certificates.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to recent certificates.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('issued_at', '>=', now()->subDays($days));
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Check if the certificate is still valid.
     */
    public function getIsValidAttribute(): bool
    {
        return is_null($this->expires_at) || $this->expires_at->isFuture();
    }

    /**
     * Check if the certificate has expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the formatted issue date.
     */
    public function getFormattedIssueDateAttribute(): string
    {
        return $this->issued_at?->format('F d, Y') ?? 'N/A';
    }

    /**
     * Get the verification URL for this certificate.
     */
    public function getVerificationUrlAttribute(): string
    {
        return url("/certificates/verify/{$this->certificate_number}");
    }

    /**
     * Get the PDF download URL.
     */
    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdf_path ? asset('storage/' . $this->pdf_path) : null;
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate) {
            if (empty($certificate->certificate_number)) {
                $certificate->certificate_number = 'CERT-' . strtoupper(\Str::random(8)) . '-' . now()->format('Y');
            }

            if (is_null($certificate->issued_at)) {
                $certificate->issued_at = now();
            }
        });
    }
}
