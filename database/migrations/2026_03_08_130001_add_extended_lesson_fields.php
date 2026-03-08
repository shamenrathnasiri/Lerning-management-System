<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the type enum — MySQL requires a full redeclaration
        DB::statement(
            "ALTER TABLE lessons MODIFY COLUMN type
             ENUM('video','text','pdf','quiz','assignment','presentation','audio','external')
             NOT NULL DEFAULT 'video'"
        );

        Schema::table('lessons', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('video_provider');
            $table->string('thumbnail_path')->nullable()->after('file_path');
            $table->json('video_quality_urls')->nullable()->after('thumbnail_path');
            $table->boolean('video_watermark')->default(false)->after('video_quality_urls');
            $table->string('s3_key')->nullable()->after('video_watermark');
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])
                  ->nullable()->after('s3_key');
            $table->string('external_url', 1000)->nullable()->after('processing_status');
            $table->json('slides_data')->nullable()->after('external_url');
            $table->boolean('is_downloadable')->default(false)->after('slides_data');

            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['processing_status']);
            $table->dropColumn([
                'file_path',
                'thumbnail_path',
                'video_quality_urls',
                'video_watermark',
                's3_key',
                'processing_status',
                'external_url',
                'slides_data',
                'is_downloadable',
            ]);
        });

        DB::statement(
            "ALTER TABLE lessons MODIFY COLUMN type
             ENUM('video','text','pdf','quiz','assignment')
             NOT NULL DEFAULT 'video'"
        );
    }
};
