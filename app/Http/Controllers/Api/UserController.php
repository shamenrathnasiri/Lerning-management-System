<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->latest()->paginate($request->integer('per_page', 15))
        );
    }

    public function show(User $user)
    {
        return response()->json(
            $user->load('roles', 'permissions')
        );
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'bio' => ['nullable', 'string', 'max:5000'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'expertise' => ['nullable', 'string', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json($user->load('roles'));
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    public function assignRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user->syncRoles([$validated['role']]);

        return response()->json($user->load('roles'));
    }

    public function instructors(Request $request)
    {
        return response()->json(
            User::role('instructor')
                ->select('id', 'name', 'username', 'avatar', 'bio', 'expertise')
                ->withCount('courses')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function courses(User $user)
    {
        return $user->load('roles')->hasRole('instructor')
            ? response()->json($user->courses()->with('category:id,name')->withCount('enrollments')->latest()->get())
            : response()->json([]);
    }
}
