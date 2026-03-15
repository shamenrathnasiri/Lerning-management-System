<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use Illuminate\Database\Seeder;

class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name'                         => 'Modern Gradient',
                'design'                       => 'modern',
                'primary_color'                => '#6366f1',
                'secondary_color'              => '#818cf8',
                'text_color'                   => '#1e293b',
                'accent_color'                 => '#f59e0b',
                'font_family'                  => 'Helvetica Neue',
                'organization_name'            => config('app.name', 'LMS Platform'),
                'custom_message'               => null,
                'show_grade'                   => true,
                'show_hours'                   => true,
                'show_qr'                      => true,
                'is_default'                   => true,
                'is_active'                    => true,
            ],
            [
                'name'                         => 'Classic Formal',
                'design'                       => 'classic',
                'primary_color'                => '#92400e',
                'secondary_color'              => '#b45309',
                'text_color'                   => '#1a1a2e',
                'accent_color'                 => '#d4a574',
                'font_family'                  => 'Georgia',
                'organization_name'            => config('app.name', 'LMS Platform'),
                'custom_message'               => null,
                'show_grade'                   => true,
                'show_hours'                   => true,
                'show_qr'                      => true,
                'is_default'                   => false,
                'is_active'                    => true,
            ],
            [
                'name'                         => 'Minimal Clean',
                'design'                       => 'minimal',
                'primary_color'                => '#0f172a',
                'secondary_color'              => '#334155',
                'text_color'                   => '#0f172a',
                'accent_color'                 => '#6366f1',
                'font_family'                  => 'Helvetica Neue',
                'organization_name'            => config('app.name', 'LMS Platform'),
                'custom_message'               => null,
                'show_grade'                   => true,
                'show_hours'                   => true,
                'show_qr'                      => true,
                'is_default'                   => false,
                'is_active'                    => true,
            ],
        ];

        foreach ($templates as $template) {
            CertificateTemplate::firstOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }
}
