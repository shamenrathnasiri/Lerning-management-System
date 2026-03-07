<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Course permissions
        Permission::create(['name' => 'view courses']);
        Permission::create(['name' => 'create courses']);
        Permission::create(['name' => 'edit courses']);
        Permission::create(['name' => 'delete courses']);
        Permission::create(['name' => 'publish courses']);

        // Lesson permissions
        Permission::create(['name' => 'view lessons']);
        Permission::create(['name' => 'create lessons']);
        Permission::create(['name' => 'edit lessons']);
        Permission::create(['name' => 'delete lessons']);

        // Enrollment permissions
        Permission::create(['name' => 'enroll students']);
        Permission::create(['name' => 'unenroll students']);
        Permission::create(['name' => 'view enrollments']);

        // Quiz / Assignment permissions
        Permission::create(['name' => 'create quizzes']);
        Permission::create(['name' => 'take quizzes']);
        Permission::create(['name' => 'grade submissions']);

        // Certificate permissions
        Permission::create(['name' => 'generate certificates']);
        Permission::create(['name' => 'view certificates']);

        // User management permissions
        Permission::create(['name' => 'manage users']);
        Permission::create(['name' => 'manage roles']);

        // Export permissions
        Permission::create(['name' => 'export data']);

        // ── Roles ────────────────────────────────────────────────

        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        $instructor = Role::create(['name' => 'instructor']);
        $instructor->givePermissionTo([
            'view courses', 'create courses', 'edit courses', 'publish courses',
            'view lessons', 'create lessons', 'edit lessons', 'delete lessons',
            'view enrollments', 'enroll students',
            'create quizzes', 'grade submissions',
            'generate certificates',
            'export data',
        ]);

        $student = Role::create(['name' => 'student']);
        $student->givePermissionTo([
            'view courses',
            'view lessons',
            'take quizzes',
            'view certificates',
        ]);
    }
}
