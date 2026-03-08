<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->timestamp('scheduled_publish_at')->nullable()->after('published_at');
            $table->string('certificate_template')->default('classic')->after('meta_keywords');
        });

        Schema::create('course_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['course_id', 'created_at']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_versions');

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['scheduled_publish_at', 'certificate_template']);
        });
    }
};
