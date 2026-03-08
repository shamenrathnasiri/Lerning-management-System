<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->unsignedBigInteger('original_size')->nullable();
            $table->string('s3_key')->nullable();
            $table->enum('status', ['pending', 'queued', 'processing', 'completed', 'failed'])
                  ->default('pending');
            $table->json('thumbnails')->nullable();
            $table->json('qualities')->nullable(); // {'360p': 's3-key', '720p': 's3-key'}
            $table->boolean('watermark_applied')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('lesson_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_processing_jobs');
    }
};
