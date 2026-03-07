<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function index(Quiz $quiz)
    {
        return response()->json(
            $quiz->questions()->with('options')->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:multiple_choice,true_false,short_answer,essay'],
            'question_text' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer'],
            'options' => ['nullable', 'array'],
            'options.*.option_text' => ['required_with:options', 'string'],
            'options.*.is_correct' => ['required_with:options', 'boolean'],
            'options.*.sort_order' => ['nullable', 'integer'],
        ]);

        $options = $validated['options'] ?? [];
        unset($validated['options']);

        $validated['sort_order'] = $validated['sort_order']
            ?? ($quiz->questions()->max('sort_order') + 1);

        $question = $quiz->questions()->create($validated);

        foreach ($options as $option) {
            $question->options()->create($option);
        }

        return response()->json($question->load('options'), 201);
    }

    public function show(Quiz $quiz, Question $question)
    {
        return response()->json($question->load('options'));
    }

    public function update(Request $request, Quiz $quiz, Question $question)
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'in:multiple_choice,true_false,short_answer,essay'],
            'question_text' => ['sometimes', 'string'],
            'explanation' => ['nullable', 'string'],
            'points' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer'],
            'options' => ['nullable', 'array'],
            'options.*.id' => ['nullable', 'exists:question_options,id'],
            'options.*.option_text' => ['required_with:options', 'string'],
            'options.*.is_correct' => ['required_with:options', 'boolean'],
            'options.*.sort_order' => ['nullable', 'integer'],
        ]);

        if (isset($validated['options'])) {
            $question->options()->delete();
            foreach ($validated['options'] as $option) {
                unset($option['id']);
                $question->options()->create($option);
            }
            unset($validated['options']);
        }

        $question->update($validated);

        return response()->json($question->load('options'));
    }

    public function destroy(Quiz $quiz, Question $question)
    {
        $question->delete();

        return response()->json(['message' => 'Question deleted.']);
    }
}
