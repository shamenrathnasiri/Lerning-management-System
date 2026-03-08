<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            'password' => Hash::make('password'),
        ]);
        $superAdmin->assignRole('super-admin');

        // ── Admin 1 ─────────────────────────────────
        $admin1 = User::factory()->create([
            'name'     => 'Kamal Perera',
            'username' => 'admin1',
            'email'    => 'admin1@lms-system.com',
            'password' => Hash::make('admin123'),
        ]);
        $admin1->assignRole('admin');

        // ── Admin 2 ─────────────────────────────────
        $admin2 = User::factory()->create([
            'name'     => 'Nimal Silva',
            'username' => 'admin2',
            'email'    => 'admin2@lms-system.com',
            'password' => Hash::make('admin123'),
        ]);
        $admin2->assignRole('admin');

        // ── Instructor 1 ────────────────────────────
        $instructor1 = User::factory()->create([
            'name'     => 'Saman Fernando',
            'username' => 'instructor1',
            'email'    => 'instructor1@lms-system.com',
            'password' => Hash::make('instructor123'),
        ]);
        $instructor1->assignRole('instructor');

        // ── Instructor 2 ────────────────────────────
        $instructor2 = User::factory()->create([
            'name'     => 'Dilani Jayawardena',
            'username' => 'instructor2',
            'email'    => 'instructor2@lms-system.com',
            'password' => Hash::make('instructor123'),
        ]);
        $instructor2->assignRole('instructor');

        // ── Student 1 ───────────────────────────────
        $student1 = User::factory()->create([
            'name'     => 'Kasun Rajapaksha',
            'username' => 'student1',
            'email'    => 'student1@lms-system.com',
            'password' => Hash::make('student123'),
        ]);
        $student1->assignRole('student');

        // ── Student 2 ───────────────────────────────
        $student2 = User::factory()->create([
            'name'     => 'Amaya Dissanayake',
            'username' => 'student2',
            'email'    => 'student2@lms-system.com',
            'password' => Hash::make('student123'),
        ]);
        $student2->assignRole('student');
    }
}
