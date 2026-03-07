<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use Illuminate\Http\Request;

class DiscussionController extends Controller
{
    public function index(Request $request)
    {
        $query = Discussion::with('user:id,name,username,avatar')
            ->withCount('replies');

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        return response()->json(
            $query->orderByDesc('is_pinned')
                ->latest()
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $validated['user_id'] = $request->user()->id;

        $discussion = Discussion::create($validated);

        return response()->json($discussion->load('user:id,name,username'), 201);
    }

    public function show(Discussion $discussion)
    {
        return response()->json(
            $discussion->load('user:id,name,username,avatar', 'replies.user:id,name,username,avatar', 'replies.children')
                ->loadCount('replies')
        );
    }

    public function update(Request $request, Discussion $discussion)
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_resolved' => ['nullable', 'boolean'],
        ]);

        $discussion->update($validated);

        return response()->json($discussion);
    }

    public function destroy(Discussion $discussion)
    {
        $discussion->delete();

        return response()->json(['message' => 'Discussion deleted.']);
    }

    // ─── Replies ─────────────────────────────────────────────────

    public function replies(Discussion $discussion)
    {
        return response()->json(
            $discussion->replies()
                ->with('user:id,name,username,avatar', 'children.user:id,name,username')
                ->whereNull('parent_id')
                ->latest()
                ->paginate(20)
        );
    }

    public function storeReply(Request $request, Discussion $discussion)
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
            'parent_id' => ['nullable', 'exists:discussion_replies,id'],
        ]);

        $validated['user_id'] = $request->user()->id;

        $reply = $discussion->replies()->create($validated);

        return response()->json($reply->load('user:id,name,username'), 201);
    }

    public function updateReply(Request $request, Discussion $discussion, DiscussionReply $reply)
    {
        $validated = $request->validate([
            'body' => ['sometimes', 'string'],
            'is_best_answer' => ['nullable', 'boolean'],
        ]);

        $reply->update($validated);

        return response()->json($reply);
    }

    public function destroyReply(Discussion $discussion, DiscussionReply $reply)
    {
        $reply->delete();

        return response()->json(['message' => 'Reply deleted.']);
    }
}
