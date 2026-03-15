<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class CertificateTemplate extends Model
{
    use HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'design',
        'background_image',
        'logo_image',
        'primary_color',
        'secondary_color',
        'text_color',
        'accent_color',
        'font_family',
        'instructor_signature_image',
        'organization_signature_image',
        'organization_name',
        'custom_message',
        'show_grade',
        'show_hours',
        'show_qr',
        'is_default',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'show_grade' => 'boolean',
            'show_hours' => 'boolean',
            'show_qr'    => 'boolean',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
            'metadata'   => 'array',
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

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
