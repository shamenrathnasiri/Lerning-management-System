<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    // ──────────────────────────────────────────────
    // Constants
    // ──────────────────────────────────────────────

    const TEMPLATE_MODERN  = 'modern';
    const TEMPLATE_CLASSIC = 'classic';
    const TEMPLATE_MINIMAL = 'minimal';

    const TEMPLATES = [
        self::TEMPLATE_MODERN,
        self::TEMPLATE_CLASSIC,
        self::TEMPLATE_MINIMAL,
    ];

    protected $fillable = [
        'user_id',
        'course_id',
        'certificate_number',
        'title',
        'template',
        'custom_background',
        'logo_path',
        'primary_color',
        'instructor_name',
        'instructor_signature',
        'organization_signature',
        'final_grade',
        'total_hours',
        'skill_level',
        'qr_code_path',
        'verification_hash',
        'verification_data',
        'share_token',
        'linkedin_badge_url',
        'share_metadata',
        'pdf_path',
        'is_revoked',
        'revoked_at',
        'revocation_reason',
        'revoked_by',
        'issued_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'final_grade'       => 'decimal:2',
            'total_hours'       => 'integer',
            'share_metadata'    => 'array',
            'is_revoked'        => 'boolean',
            'issued_at'         => 'datetime',
            'expires_at'        => 'datetime',
            'revoked_at'        => 'datetime',
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

    public function revokedByUser()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function enrollment()
    {
        return $this->hasOne(Enrollment::class, 'course_id', 'course_id')
            ->where('user_id', $this->user_id);
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

    public function scopeRevoked($query)
    {
        return $query->where('is_revoked', true);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('issued_at', '>=', now()->subDays($days));
    }

    public function scopeWithTemplate($query, string $template)
    {
        return $query->where('template', $template);
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    public function getIsValidAttribute(): bool
    {
        if ($this->is_revoked) return false;
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
        return $this->share_token
            ? url("/certificates/share/{$this->share_token}")
            : $this->verification_url;
    }

    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdf_path ? asset('storage/' . $this->pdf_path) : null;
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        return $this->qr_code_path ? asset('storage/' . $this->qr_code_path) : null;
    }

    public function getLinkedInShareUrlAttribute(): string
    {
        $params = http_build_query([
            'name'           => $this->title,
            'organizationId' => config('app.linkedin_org_id', ''),
            'issueYear'      => $this->issued_at?->year,
            'issueMonth'     => $this->issued_at?->month,
            'certUrl'        => $this->public_share_url,
            'certId'         => $this->certificate_number,
        ]);

        return "https://www.linkedin.com/profile/add?startTask=CERTIFICATION_NAME&{$params}";
    }

    public function getFormattedGradeAttribute(): ?string
    {
        if (is_null($this->final_grade)) return null;

        $grade = $this->final_grade;
        $letter = match (true) {
            $grade >= 93 => 'A',
            $grade >= 90 => 'A-',
            $grade >= 87 => 'B+',
            $grade >= 83 => 'B',
            $grade >= 80 => 'B-',
            $grade >= 77 => 'C+',
            $grade >= 73 => 'C',
            $grade >= 70 => 'C-',
            $grade >= 67 => 'D+',
            $grade >= 60 => 'D',
            default      => 'F',
        };

        return "{$letter} ({$grade}%)";
    }

    public function getSkillLevelLabelAttribute(): string
    {
        return match ($this->skill_level) {
            'beginner'     => 'Beginner',
            'intermediate' => 'Intermediate',
            'advanced'     => 'Advanced',
            'expert'       => 'Expert',
            default        => ucfirst($this->skill_level ?? 'N/A'),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->is_revoked) return 'Revoked';
        if ($this->is_expired) return 'Expired';
        return 'Valid';
    }

    public function getStatusColorAttribute(): string
    {
        if ($this->is_revoked) return '#ef4444';  // red
        if ($this->is_expired) return '#f59e0b';  // amber
        return '#10b981';                           // green
    }

    // ──────────────────────────────────────────────
    // Methods
    // ──────────────────────────────────────────────

    /**
     * Revoke this certificate.
     */
    public function revoke(string $reason, ?int $revokedBy = null): self
    {
        $this->update([
            'is_revoked'        => true,
            'revoked_at'        => now(),
            'revocation_reason' => $reason,
            'revoked_by'        => $revokedBy,
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
            'revoked_by'        => null,
        ]);

        return $this;
    }

    /**
     * Generate verification hash.
     */
    public function generateVerificationHash(): string
    {
        $data = json_encode([
            'certificate_number' => $this->certificate_number,
            'user_id'            => $this->user_id,
            'course_id'          => $this->course_id,
            'issued_at'          => $this->issued_at?->toISOString(),
        ]);

        return hash('sha256', $data . config('app.key'));
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
                $certificate->share_token = Str::random(48);
            }

            if (empty($certificate->template)) {
                $certificate->template = self::TEMPLATE_MODERN;
            }
        });

        static::created(function (Certificate $certificate) {
            // Generate verification hash after creation (needs ID)
            $certificate->update([
                'verification_hash' => $certificate->generateVerificationHash(),
                'verification_data' => json_encode([
                    'id'                 => $certificate->id,
                    'certificate_number' => $certificate->certificate_number,
                    'student'            => $certificate->user?->name,
                    'course'             => $certificate->course?->title,
                    'issued_at'          => $certificate->issued_at?->toISOString(),
                ]),
            ]);
        });
    }
}
