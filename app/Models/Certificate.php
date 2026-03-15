<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'course_id',
        'certificate_number',
        'title',
        'template',
        'instructor_name',
        'instructor_signature_path',
        'course_title',
        'course_level',
        'course_duration_hours',
        'student_name',
        'final_score',
        'completion_days',
        'total_lessons_completed',
        'verification_hash',
        'qr_code_path',
        'share_token',
        'linkedin_share_url',
        'public_image_path',
        'pdf_path',
        'is_revoked',
        'revoked_at',
        'revocation_reason',
        'issued_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'final_score'       => 'decimal:2',
            'completion_days'   => 'integer',
            'total_lessons_completed' => 'integer',
            'course_duration_hours'   => 'integer',
            'is_revoked'        => 'boolean',
            'issued_at'         => 'datetime',
            'expires_at'        => 'datetime',
            'revoked_at'        => 'datetime',
            'metadata'          => 'array',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeValid($query)
    {
        return $query->where('is_revoked', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeRevoked($query)
    {
        return $query->where('is_revoked', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('issued_at', '>=', now()->subDays($days));
    }

    public function scopeForTemplate($query, string $template)
    {
        return $query->where('template', $template);
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    public function getIsValidAttribute(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        return is_null($this->expires_at) || $this->expires_at->isFuture();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getFormattedIssueDateAttribute(): string
    {
        return $this->issued_at?->format('F d, Y') ?? 'N/A';
    }

    public function getVerificationUrlAttribute(): string
    {
        return url("/api/certificates/verify/{$this->certificate_number}");
    }

    public function getPublicShareUrlAttribute(): string
    {
        return url("/certificates/share/{$this->share_token}");
    }

    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdf_path ? asset('storage/' . $this->pdf_path) : null;
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        return $this->qr_code_path ? asset('storage/' . $this->qr_code_path) : null;
    }

    public function getLinkedinAddUrlAttribute(): string
    {
        $params = http_build_query([
            'name'             => $this->title,
            'organizationName' => config('app.name', 'LMS'),
            'issueYear'        => $this->issued_at?->year,
            'issueMonth'       => $this->issued_at?->month,
            'certUrl'          => $this->verification_url,
            'certId'           => $this->certificate_number,
        ]);

        return "https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME&{$params}";
    }

    public function getStatusAttribute(): string
    {
        if ($this->is_revoked) return 'revoked';
        if ($this->is_expired) return 'expired';
        return 'valid';
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'valid'   => ['label' => 'Valid', 'color' => '#10b981', 'icon' => '✅'],
            'expired' => ['label' => 'Expired', 'color' => '#f59e0b', 'icon' => '⏰'],
            'revoked' => ['label' => 'Revoked', 'color' => '#ef4444', 'icon' => '🚫'],
        };
    }

    // ──────────────────────────────────────────────
    // Methods
    // ──────────────────────────────────────────────

    /**
     * Revoke this certificate.
     */
    public function revoke(string $reason = null): self
    {
        $this->update([
            'is_revoked'        => true,
            'revoked_at'        => now(),
            'revocation_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Reinstate a revoked certificate.
     */
    public function reinstate(): self
    {
        $this->update([
            'is_revoked'        => false,
            'revoked_at'        => null,
            'revocation_reason' => null,
        ]);

        return $this;
    }

    // ──────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate) {
            if (empty($certificate->certificate_number)) {
                $certificate->certificate_number = 'CERT-' . strtoupper(Str::random(8)) . '-' . now()->format('Y');
            }

            if (is_null($certificate->issued_at)) {
                $certificate->issued_at = now();
            }

            if (empty($certificate->share_token)) {
                $certificate->share_token = Str::random(32);
            }

            if (empty($certificate->verification_hash)) {
                $certificate->verification_hash = hash('sha256',
                    $certificate->certificate_number . $certificate->user_id . $certificate->course_id . now()->timestamp
                );
            }
        });
    }
}
