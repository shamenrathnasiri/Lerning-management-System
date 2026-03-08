<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerBladeDirectives();
        $this->registerSuperAdminGate();
    }

    /**
     * Register custom Blade directives for role-based access control.
     *
     * Usage in Blade templates:
     *
     *   @admin       ... @endadmin
     *   @instructor  ... @endinstructor
     *   @student     ... @endstudent
     *   @superadmin  ... @endsuperadmin
     */
    private function registerBladeDirectives(): void
    {
        // ── @superadmin / @endsuperadmin ──────────────
        Blade::if('superadmin', function () {
            return auth()->check() && auth()->user()->isSuperAdmin();
        });

        // ── @admin / @endadmin ────────────────────────
        // Includes both admin and super-admin
        Blade::if('admin', function () {
            return auth()->check() && auth()->user()->isAdmin();
        });

        // ── @instructor / @endinstructor ──────────────
        // Matches instructor, admin, and super-admin
        Blade::if('instructor', function () {
            return auth()->check() && auth()->user()->isAtLeastInstructor();
        });

        // ── @student / @endstudent ────────────────────
        // Matches any authenticated user with the student role
        Blade::if('student', function () {
            return auth()->check() && auth()->user()->isStudent();
        });

        // ── @enrolled($course) / @endrolled ───────────
        // Check if the user is enrolled in a specific course
        Blade::if('enrolled', function ($course) {
            return auth()->check() && auth()->user()->isEnrolledIn($course);
        });

        // ── @ownscourse($course) / @endownscourse ────
        // Check if the user is the instructor (owner) of a course
        Blade::if('ownscourse', function ($course) {
            return auth()->check() && auth()->user()->ownsCourse($course);
        });

        // ── @canmanage($course) / @endcanmanage ──────
        // Check if the user can manage a course (owner or admin)
        Blade::if('canmanage', function ($course) {
            return auth()->check() && auth()->user()->canManageCourse($course);
        });
    }

    /**
     * Grant super-admin all permissions via Gate::before.
     *
     * This ensures super-admin bypasses all Gate/Policy checks application-wide.
     */
    private function registerSuperAdminGate(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
