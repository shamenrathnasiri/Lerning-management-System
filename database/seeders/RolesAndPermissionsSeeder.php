<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ──────────────────────────────────────────────
        // Define all permissions grouped by domain
        // ──────────────────────────────────────────────

        $permissions = [

            // Course management
            'view courses',
            'create courses',
            'edit courses',
            'delete courses',
            'publish courses',
            'archive courses',
            'feature courses',

            // Lesson management
            'view lessons',
            'create lessons',
            'edit lessons',
            'delete lessons',

            // Section management
            'create sections',
            'edit sections',
            'delete sections',

            // Enrollment management
            'enroll students',
            'unenroll students',
            'view enrollments',
            'manage enrollments',

            // Quiz & Assignment management
            'create quizzes',
            'edit quizzes',
            'delete quizzes',
            'take quizzes',
            'view quiz results',
            'create assignments',
            'edit assignments',
            'delete assignments',
            'submit assignments',
            'grade submissions',

            // Certificate management
            'issue certificates',
            'view certificates',
            'revoke certificates',

            // Review management
            'write reviews',
            'moderate reviews',
            'delete reviews',

            // Discussion management
            'create discussions',
            'reply discussions',
            'moderate discussions',
            'pin discussions',
            'delete discussions',

            // Category & Tag management
            'manage categories',
            'manage tags',

            // User management
            'view users',
            'manage users',
            'manage roles',
            'ban users',
            'impersonate users',

            // Badge management
            'manage badges',
            'award badges',

            // Payment & Coupon management
            'view payments',
            'manage payments',
            'refund payments',
            'manage coupons',

            // Announcement management
            'create announcements',
            'manage announcements',

            // Wishlist
            'manage wishlists',

            // Analytics & Reports
            'view analytics',
            'view admin dashboard',
            'view instructor dashboard',
            'export data',

            // System management
            'manage settings',
            'view logs',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ──────────────────────────────────────────────
        // Create Roles & Assign Permissions
        // ──────────────────────────────────────────────

        // ── Super Admin ──────────────────────────────
        // Gets ALL permissions (full system access)
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // ── Admin ────────────────────────────────────
        // Full access except system-level settings
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            // Courses
            'view courses', 'create courses', 'edit courses', 'delete courses',
            'publish courses', 'archive courses', 'feature courses',
            // Lessons & Sections
            'view lessons', 'create lessons', 'edit lessons', 'delete lessons',
            'create sections', 'edit sections', 'delete sections',
            // Enrollments
            'enroll students', 'unenroll students', 'view enrollments', 'manage enrollments',
            // Quizzes & Assignments
            'create quizzes', 'edit quizzes', 'delete quizzes', 'view quiz results',
            'create assignments', 'edit assignments', 'delete assignments', 'grade submissions',
            // Certificates
            'issue certificates', 'view certificates', 'revoke certificates',
            // Reviews
            'moderate reviews', 'delete reviews',
            // Discussions
            'moderate discussions', 'pin discussions', 'delete discussions',
            // Categories & Tags
            'manage categories', 'manage tags',
            // Users
            'view users', 'manage users', 'manage roles', 'ban users',
            // Badges
            'manage badges', 'award badges',
            // Payments
            'view payments', 'manage payments', 'refund payments', 'manage coupons',
            // Announcements
            'create announcements', 'manage announcements',
            // Analytics
            'view analytics', 'view admin dashboard', 'export data',
            // System
            'view logs',
        ]);

        // ── Instructor ───────────────────────────────
        // Can manage own courses, lessons, quizzes, respond to students
        $instructor = Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        $instructor->syncPermissions([
            // Courses (own only — enforced at policy level)
            'view courses', 'create courses', 'edit courses', 'delete courses', 'publish courses',
            // Lessons & Sections (own courses)
            'view lessons', 'create lessons', 'edit lessons', 'delete lessons',
            'create sections', 'edit sections', 'delete sections',
            // Enrollments (own courses)
            'view enrollments', 'enroll students',
            // Quizzes & Assignments (own courses)
            'create quizzes', 'edit quizzes', 'delete quizzes', 'view quiz results',
            'create assignments', 'edit assignments', 'delete assignments', 'grade submissions',
            // Certificates (own courses)
            'issue certificates', 'view certificates',
            // Discussions (own courses)
            'create discussions', 'reply discussions', 'pin discussions',
            // Announcements (own courses)
            'create announcements',
            // Analytics (own courses)
            'view analytics', 'view instructor dashboard', 'export data',
            // Wishlists
            'manage wishlists',
        ]);

        // ── Student ──────────────────────────────────
        // Can browse, enroll, learn, review, discuss, earn badges
        $student = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student->syncPermissions([
            // Courses
            'view courses',
            // Lessons
            'view lessons',
            // Enrollments
            'view enrollments',
            // Quizzes & Assignments
            'take quizzes', 'view quiz results',
            'submit assignments',
            // Certificates
            'view certificates',
            // Reviews
            'write reviews',
            // Discussions
            'create discussions', 'reply discussions',
            // Wishlists
            'manage wishlists',
        ]);
    }
}
