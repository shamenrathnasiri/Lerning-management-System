<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->unsignedInteger('timestamp_seconds')->nullable(); // sync to video position
            $table->string('color', 20)->default('yellow');
            $table->boolean('is_private')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'lesson_id']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_notes');
    }
};
