<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE questions MODIFY COLUMN type
             ENUM('multiple_choice','multiple_select','true_false','fill_blank','matching_pairs','ordering','essay','code_challenge','short_answer')
             NOT NULL DEFAULT 'multiple_choice'"
        );

        Schema::table('quizzes', function (Blueprint $table) {
            $table->enum('quiz_type', ['lesson_quiz', 'course_exam', 'practice_test'])
                ->default('lesson_quiz')
                ->after('description');
            $table->enum('time_limit_mode', ['per_quiz', 'per_question'])
                ->default('per_quiz')
                ->after('quiz_type');
            $table->integer('per_question_time_seconds')->nullable()->after('time_limit_minutes');
            $table->boolean('randomize_options')->default(false)->after('shuffle_questions');
            $table->enum('answer_visibility', ['immediate', 'after_attempts'])
                ->default('after_attempts')
                ->after('randomize_options');
            $table->integer('show_answers_after_attempts')->nullable()->after('answer_visibility');
            $table->enum('navigation_mode', ['free', 'sequential'])
                ->default('free')
                ->after('show_answers_after_attempts');
            $table->longText('instructions')->nullable()->after('navigation_mode');
            $table->json('settings')->nullable()->after('instructions');
            $table->timestamp('published_at')->nullable()->after('is_published');

            $table->index('quiz_type');
            $table->index('published_at');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->longText('question_content')->nullable()->after('question_text');
            $table->json('answer_payload')->nullable()->after('explanation');
            $table->json('media_embed')->nullable()->after('answer_payload');
            $table->boolean('allow_partial_credit')->default(false)->after('media_embed');
            $table->string('code_language', 50)->nullable()->after('allow_partial_credit');
            $table->longText('code_starter')->nullable()->after('code_language');
            $table->longText('code_solution')->nullable()->after('code_starter');
            $table->json('code_test_cases')->nullable()->after('code_solution');
            $table->integer('execution_timeout_seconds')->nullable()->after('code_test_cases');
            $table->json('metadata')->nullable()->after('execution_timeout_seconds');
        });

        Schema::table('question_options', function (Blueprint $table) {
            $table->longText('content')->nullable()->after('option_text');
            $table->string('match_key', 191)->nullable()->after('content');
            $table->json('option_payload')->nullable()->after('match_key');
        });
    }

    public function down(): void
    {
        Schema::table('question_options', function (Blueprint $table) {
            $table->dropColumn(['content', 'match_key', 'option_payload']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn([
                'question_content',
                'answer_payload',
                'media_embed',
                'allow_partial_credit',
                'code_language',
                'code_starter',
                'code_solution',
                'code_test_cases',
                'execution_timeout_seconds',
                'metadata',
            ]);
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropIndex(['quiz_type']);
            $table->dropIndex(['published_at']);
            $table->dropColumn([
                'quiz_type',
                'time_limit_mode',
                'per_question_time_seconds',
                'randomize_options',
                'answer_visibility',
                'show_answers_after_attempts',
                'navigation_mode',
                'instructions',
                'settings',
                'published_at',
            ]);
        });

        DB::statement(
            "ALTER TABLE questions MODIFY COLUMN type
             ENUM('multiple_choice','true_false','short_answer','essay')
             NOT NULL DEFAULT 'multiple_choice'"
        );
    }
};
