<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Quiz Results
        </h2>
    </x-slot>

    @push('styles')
    <style>
        .qr-ring-progress { transition: stroke-dashoffset 1.5s cubic-bezier(.4,0,.2,1); }
        .qr-bounce { animation: qrBounce .6s ease; }
        @keyframes qrBounce { 0%{transform:scale(0)} 60%{transform:scale(1.15)} 100%{transform:scale(1)} }
        .qr-review-correct { border-left: 4px solid #10b981; }
        .qr-review-incorrect { border-left: 4px solid #ef4444; }
        .qr-opt-correct { background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.3); }
        .qr-opt-wrong   { background: rgba(239,68,68,.06);  border: 1px solid rgba(239,68,68,.3); text-decoration: line-through; opacity: .8; }
        .qr-opt-selected { background: rgba(79,70,229,.06); border: 1px solid rgba(79,70,229,.3); }
    </style>
    @endpush

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8" x-data="{ showDetails: {} }">

            {{-- ═══ HERO SECTION ═══ --}}
            <div class="relative mb-8 overflow-hidden rounded-2xl px-6 py-10 text-center text-white shadow-lg
                {{ $attempt->passed ? 'bg-gradient-to-r from-emerald-600 via-emerald-500 to-teal-400' : 'bg-gradient-to-r from-red-600 via-red-500 to-rose-400' }}">
                {{-- Pattern overlay --}}
                <div class="absolute inset-0 opacity-10" style="background-image: url(&quot;data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E&quot;)"></div>
                <div class="relative z-10">
                    {{-- Icon --}}
                    @if($attempt->passed)
                        <svg class="qr-bounce mx-auto mb-4 h-16 w-16 drop-shadow-lg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25z" clip-rule="evenodd"/>
                        </svg>
                    @else
                        <svg class="qr-bounce mx-auto mb-4 h-16 w-16 drop-shadow-lg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72z" clip-rule="evenodd"/>
                        </svg>
                    @endif
                    <h1 class="text-2xl font-extrabold sm:text-3xl">
                        {{ $attempt->passed ? 'Congratulations! You Passed!' : 'Keep Trying!' }}
                    </h1>
                    <p class="mt-2 text-sm font-medium text-white/80">{{ $quiz->title }}</p>
                </div>
            </div>

            {{-- ═══ SUMMARY CARDS ═══ --}}
            @php
                $totalPoints = $quiz->questions()->sum('points');
                $totalQuestions = $quiz->questions()->count();
                $correctCount = $attempt->answers()->where('is_correct', true)->count();
                $pct = round($attempt->percentage);
                $durationTotal = $attempt->started_at && $attempt->completed_at
                    ? $attempt->started_at->diffInSeconds($attempt->completed_at) : 0;
                $dH = intdiv($durationTotal, 3600);
                $dM = intdiv($durationTotal % 3600, 60);
                $dS = $durationTotal % 60;
                $durationStr = $dH > 0 ? "{$dH}h {$dM}m {$dS}s" : ($dM > 0 ? "{$dM}m {$dS}s" : "{$dS}s");
                $attemptNumber = $quiz->attempts()->where('user_id', $attempt->user_id)->where('id', '<=', $attempt->id)->count();
                $canRetake = $quiz->hasRemainingAttempts(auth()->id());
                $circumference = 2 * 3.14159265 * 52;
                $offset = $circumference - ($pct / 100) * $circumference;
            @endphp

            <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                {{-- Score Circle --}}
                <div class="col-span-2 flex flex-col items-center justify-center rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:col-span-1 sm:row-span-2">
                    <div class="relative h-28 w-28">
                        <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="8"
                                    class="{{ $pct >= 80 ? 'text-emerald-100' : ($attempt->passed ? 'text-indigo-100' : 'text-red-100') }}"/>
                            <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="8"
                                    stroke-linecap="round"
                                    class="qr-ring-progress {{ $pct >= 80 ? 'text-emerald-500' : ($attempt->passed ? 'text-indigo-500' : 'text-red-500') }}"
                                    stroke-dasharray="{{ $circumference }}"
                                    stroke-dashoffset="{{ $offset }}"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-3xl font-extrabold text-gray-900">{{ $pct }}</span>
                            <span class="mt-1 text-sm font-semibold text-gray-400">%</span>
                        </div>
                    </div>
                    <p class="mt-2 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Your Score</p>
                    <p class="text-sm font-semibold text-gray-600">{{ $attempt->score }} / {{ $totalPoints }} pts</p>
                </div>

                {{-- Time Taken --}}
                <div class="flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Time Taken</p>
                        <p class="text-lg font-bold text-gray-900">{{ $durationStr }}</p>
                    </div>
                </div>

                {{-- Correct Count --}}
                <div class="flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143z" clip-rule="evenodd"/></svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Correct</p>
                        <p class="text-lg font-bold text-gray-900">{{ $correctCount }} / {{ $totalQuestions }}</p>
                    </div>
                </div>

                {{-- Pass/Fail Status --}}
                <div class="flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $attempt->passed ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600' }}">
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Status</p>
                        <p class="text-lg font-bold {{ $attempt->passed ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $attempt->passed ? 'PASSED' : 'FAILED' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Pass Requirement --}}
            <div class="mb-8 flex flex-wrap gap-6 rounded-xl border border-gray-200 bg-gray-50 px-5 py-3 text-sm text-gray-500">
                <span>Pass requirement: <strong class="text-gray-700">{{ $quiz->pass_percentage }}%</strong></span>
                @if($quiz->max_attempts)
                    <span>Attempts: <strong class="text-gray-700">{{ $attemptNumber }}</strong> of <strong class="text-gray-700">{{ $quiz->max_attempts }}</strong></span>
                @else
                    <span>Attempts: <strong class="text-gray-700">{{ $attemptNumber }}</strong> (unlimited)</span>
                @endif
            </div>

            {{-- ═══ QUESTION REVIEW ═══ --}}
            @if($quiz->show_correct_answers)
            <div class="mb-8">
                <h2 class="mb-5 flex items-center gap-2 text-xl font-bold text-gray-900">
                    <svg class="h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41z" clip-rule="evenodd"/></svg>
                    Question Review
                </h2>

                @foreach($attempt->answers as $idx => $answer)
                    @php $question = $answer->question; @endphp
                    <div class="mb-4 overflow-hidden rounded-xl border border-gray-200 shadow-sm transition hover:shadow {{ $answer->is_correct ? 'qr-review-correct' : 'qr-review-incorrect' }}">
                        {{-- Header --}}
                        <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-200 text-xs font-bold text-gray-600">
                                Q{{ $idx + 1 }}
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $answer->is_correct ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                {{ $answer->is_correct ? '✓ Correct' : '✗ Incorrect' }}
                            </span>
                            <span class="ml-auto text-xs font-semibold text-gray-400">
                                {{ $answer->points_earned ?? 0 }} / {{ $question->points }} pts
                            </span>
                        </div>

                        {{-- Body --}}
                        <div class="bg-white p-5">
                            <p class="mb-4 text-sm leading-7 text-gray-800">{!! $question->question_text !!}</p>

                            {{-- Options with correct/incorrect markers --}}
                            @if($question->options->count() > 0)
                                <div class="space-y-2">
                                    @foreach($question->options as $opt)
                                        @php
                                            $wasSelected = $opt->id === $answer->question_option_id;
                                            $classes = '';
                                            if ($opt->is_correct && $wasSelected) $classes = 'qr-opt-correct';
                                            elseif ($wasSelected && !$opt->is_correct) $classes = 'qr-opt-wrong';
                                            elseif ($opt->is_correct) $classes = 'qr-opt-correct';
                                            elseif ($wasSelected) $classes = 'qr-opt-selected';
                                        @endphp
                                        <div class="flex items-start gap-2 rounded-lg px-3 py-2 text-sm {{ $classes }}">
                                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center text-xs font-bold
                                                {{ $opt->is_correct && $wasSelected ? 'text-emerald-600' : '' }}
                                                {{ $wasSelected && !$opt->is_correct ? 'text-red-600' : '' }}
                                                {{ $opt->is_correct && !$wasSelected ? 'text-emerald-600' : '' }}
                                            ">
                                                @if($opt->is_correct && $wasSelected) ✓
                                                @elseif($wasSelected && !$opt->is_correct) ✗
                                                @elseif($opt->is_correct) ✓
                                                @else ○
                                                @endif
                                            </span>
                                            <span>{!! $opt->option_text !!}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Text answer --}}
                            @if($answer->answer_text)
                                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                                    <strong>Your Answer:</strong> {{ $answer->answer_text }}
                                </div>
                            @endif

                            {{-- Explanation --}}
                            @if($question->explanation)
                                <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                                    <div class="mb-1 flex items-center gap-1.5 text-xs font-bold text-blue-800">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9z" clip-rule="evenodd"/></svg>
                                        Explanation
                                    </div>
                                    <p class="text-xs leading-6 text-blue-700">{!! $question->explanation !!}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

            {{-- ═══ ACTIONS ═══ --}}
            <div class="flex flex-wrap items-center justify-center gap-4">
                @if($canRetake)
                    <a href="{{ route('student.quiz.start', $quiz) }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H4.397a.75.75 0 0 0-.75.75v3.834a.75.75 0 0 0 1.5 0v-2.09l.311.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39zm-11.23-3.21a.75.75 0 0 0 1.449.39 5.5 5.5 0 0 1 9.201-2.466l.312.311H12.61a.75.75 0 0 0 0 1.5h3.834a.75.75 0 0 0 .75-.75V3.365a.75.75 0 0 0-1.5 0v2.09l-.311-.31A7 7 0 0 0 3.673 8.283.75.75 0 0 0 4.082 8.214z" clip-rule="evenodd"/></svg>
                        Retake Quiz
                    </a>
                @endif
                <a href="{{ $quiz->course ? route('student.course.show', ['course' => $quiz->course->slug ?? $quiz->course->id]) : '/' }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-300 px-6 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0z" clip-rule="evenodd"/></svg>
                    Back to Course
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
