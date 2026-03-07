<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('type', ['video', 'text', 'pdf', 'quiz', 'assignment'])->default('video');
            $table->longText('content')->nullable();
            $table->string('video_url')->nullable();
            $table->string('video_provider')->nullable(); // youtube, vimeo, upload
            $table->integer('duration_minutes')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_free_preview')->default(false);
            $table->boolean('is_published')->default(false);
            $table->json('resources')->nullable(); // downloadable attachments
            $table->timestamps();
            $table->softDeletes();

            $table->index('section_id');
            $table->index('course_id');
            $table->index('slug');
            $table->index('sort_order');
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
