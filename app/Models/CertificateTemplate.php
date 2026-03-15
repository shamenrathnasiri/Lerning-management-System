<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class CertificateTemplate extends Model
{
    use HasSlug;

    // Design presets
    const DESIGN_MODERN  = 'modern';
    const DESIGN_CLASSIC = 'classic';
    const DESIGN_MINIMAL = 'minimal';

    const DESIGNS = [
        self::DESIGN_MODERN,
        self::DESIGN_CLASSIC,
        self::DESIGN_MINIMAL,
    ];

    const BORDER_ELEGANT = 'elegant';
    const BORDER_GOLD    = 'gold';
    const BORDER_SIMPLE  = 'simple';
    const BORDER_NONE    = 'none';

    protected $fillable = [
        'name',
        'slug',
        'design',
        'description',
        'background_image_path',
        'logo_path',
        'badge_image_path',
        'border_style',
        'primary_color',
        'accent_color',
        'text_color',
        'font_family',
        'heading_text',
        'body_text',
        'footer_text',
        'signature_fields',
        'orientation',
        'paper_size',
        'show_qr_code',
        'show_logo',
        'show_badge',
        'show_date',
        'show_score',
        'show_duration',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'signature_fields' => 'array',
            'show_qr_code'     => 'boolean',
            'show_logo'        => 'boolean',
            'show_badge'       => 'boolean',
            'show_date'        => 'boolean',
            'show_score'       => 'boolean',
            'show_duration'    => 'boolean',
            'is_default'       => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    // ──── Scopes ────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfDesign($query, string $design)
    {
        return $query->where('design', $design);
    }

    // ──── Helpers ───────────────────────────────────────────────

    /**
     * Parse body text with dynamic placeholders.
     */
    public function renderBodyText(array $data): string
    {
        $text = $this->body_text ?? 'This is to certify that {{student_name}} has successfully completed the course "{{course_title}}".';

        $replacements = [
            '{{student_name}}'  => $data['student_name'] ?? '',
            '{{course_title}}'  => $data['course_title'] ?? '',
            '{{course_level}}'  => $data['course_level'] ?? '',
            '{{instructor}}'    => $data['instructor'] ?? '',
            '{{issued_date}}'   => $data['issued_date'] ?? '',
            '{{duration}}'      => $data['duration'] ?? '',
            '{{score}}'         => $data['score'] ?? '',
            '{{cert_number}}'   => $data['cert_number'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Get the default template.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first()
            ?? static::where('is_active', true)->first();
    }
}
