<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@lms-system.com',
        ]);
        $admin->assignRole('admin');

        $instructor = User::factory()->create([
            'name' => 'Instructor User',
            'email' => 'instructor@lms-system.com',
        ]);
        $instructor->assignRole('instructor');

        $student = User::factory()->create([
            'name' => 'Student User',
            'email' => 'student@lms-system.com',
        ]);
        $student->assignRole('student');
    }
}
