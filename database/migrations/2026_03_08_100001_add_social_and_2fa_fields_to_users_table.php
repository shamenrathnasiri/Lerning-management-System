<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Social login fields
            $table->string('google_id')->nullable()->unique()->after('social_links');
            $table->string('facebook_id')->nullable()->unique()->after('google_id');
            $table->string('social_avatar')->nullable()->after('facebook_id');

            // Two-factor authentication fields
            $table->boolean('two_factor_enabled')->default(false)->after('social_avatar');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            // Terms acceptance
            $table->timestamp('terms_accepted_at')->nullable()->after('two_factor_confirmed_at');

            // Indexes
            $table->index('google_id');
            $table->index('facebook_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['google_id']);
            $table->dropIndex(['facebook_id']);
            $table->dropColumn([
                'google_id',
                'facebook_id',
                'social_avatar',
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'terms_accepted_at',
            ]);
        });
    }
};
