<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles & permissions first
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        // ── Super Admin ──────────────────────────────
        $superAdmin = User::factory()->create([
            'name'     => 'Super Admin',
            'username' => 'superadmin',
            'email'    => 'superadmin@lms-system.com',
        ]);
        $superAdmin->assignRole('super-admin');

        // ── Admin ────────────────────────────────────
        $admin = User::factory()->create([
            'name'     => 'Admin User',
            'username' => 'admin',
            'email'    => 'admin@lms-system.com',
        ]);
        $admin->assignRole('admin');

        // ── Instructor ───────────────────────────────
        $instructor = User::factory()->create([
            'name'     => 'Instructor User',
            'username' => 'instructor',
            'email'    => 'instructor@lms-system.com',
        ]);
        $instructor->assignRole('instructor');

        // ── Student ──────────────────────────────────
        $student = User::factory()->create([
            'name'     => 'Student User',
            'username' => 'student',
            'email'    => 'student@lms-system.com',
        ]);
        $student->assignRole('student');
    }
}
