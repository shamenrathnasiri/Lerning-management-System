<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\UserBadge;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function index()
    {
        return response()->json(
            Badge::withCount('users')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'criteria_type' => ['required', 'string', 'max:255'],
            'criteria_value' => ['required', 'integer', 'min:1'],
        ]);

        $badge = Badge::create($validated);

        return response()->json($badge, 201);
    }

    public function show(Badge $badge)
    {
        return response()->json($badge->loadCount('users'));
    }

    public function update(Request $request, Badge $badge)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'criteria_type' => ['sometimes', 'string', 'max:255'],
            'criteria_value' => ['sometimes', 'integer', 'min:1'],
        ]);

        $badge->update($validated);

        return response()->json($badge);
    }

    public function destroy(Badge $badge)
    {
        $badge->delete();

        return response()->json(['message' => 'Badge deleted.']);
    }

    public function award(Request $request, Badge $badge)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $exists = UserBadge::where('user_id', $validated['user_id'])
            ->where('badge_id', $badge->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'User already has this badge.'], 409);
        }

        $userBadge = UserBadge::create([
            'user_id' => $validated['user_id'],
            'badge_id' => $badge->id,
            'earned_at' => now(),
        ]);

        return response()->json($userBadge->load('badge', 'user:id,name'), 201);
    }

    public function myBadges(Request $request)
    {
        return response()->json(
            UserBadge::with('badge')
                ->where('user_id', $request->user()->id)
                ->latest('earned_at')
                ->get()
        );
    }
}
