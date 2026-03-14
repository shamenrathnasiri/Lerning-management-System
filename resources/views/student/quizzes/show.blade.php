<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ $quiz->title }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            {{-- Quiz Info Card --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                {{-- Header --}}
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-8 text-center text-white">
                    <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-sm font-medium backdrop-blur-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M5.127 3.502 5.25 3.5h9.5c.041 0 .082 0 .123.002A2.251 2.251 0 0 0 12.75 2h-5.5a2.25 2.25 0 0 0-2.123 1.502zM1 10.25A2.25 2.25 0 0 1 3.25 8h13.5A2.25 2.25 0 0 1 19 10.25v5.5A2.25 2.25 0 0 1 16.75 18H3.25A2.25 2.25 0 0 1 1 15.75v-5.5z"/>
                        </svg>
                        Quiz
                    </div>
                    <h1 class="text-2xl font-bold">{{ $quiz->title }}</h1>
                    @if($quiz->description)
                        <p class="mt-2 text-sm text-white/80">{{ $quiz->description }}</p>
                    @endif
                </div>

                {{-- Quiz Details --}}
                <div class="grid grid-cols-2 gap-4 border-b border-gray-200 p-6 sm:grid-cols-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900">{{ $quiz->questions_count }}</p>
                        <p class="text-xs text-gray-500">Questions</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900">{{ $quiz->total_points }}</p>
                        <p class="text-xs text-gray-500">Total Points</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900">{{ $quiz->formatted_time_limit }}</p>
                        <p class="text-xs text-gray-500">Time Limit</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900">{{ $quiz->pass_percentage }}%</p>
                        <p class="text-xs text-gray-500">Pass Score</p>
                    </div>
                </div>

                {{-- Instructions --}}
                @if($quiz->instructions)
                    <div class="border-b border-gray-200 p-6">
                        <h3 class="mb-2 text-sm font-semibold text-gray-900">Instructions</h3>
                        <div class="prose prose-sm text-gray-600">{!! nl2br(e($quiz->instructions)) !!}</div>
                    </div>
                @endif

                {{-- Rules --}}
                <div class="border-b border-gray-200 p-6">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">Quiz Rules</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        @if($quiz->time_limit_minutes)
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-indigo-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/>
                                </svg>
                                You have <strong>{{ $quiz->formatted_time_limit }}</strong> to complete this quiz. It will auto-submit when time expires.
                            </li>
                        @endif
                        @if($quiz->max_attempts)
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H4.397a.75.75 0 00-.75.75v3.834a.75.75 0 001.5 0v-2.09l.311.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39z" clip-rule="evenodd"/>
                                </svg>
                                Maximum <strong>{{ $quiz->max_attempts }}</strong> attempt(s) allowed.
                            </li>
                        @else
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                                </svg>
                                Unlimited attempts allowed.
                            </li>
                        @endif
                        @if($quiz->shuffle_questions)
                            <li class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-purple-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H4.397a.75.75 0 00-.75.75v3.834a.75.75 0 001.5 0v-2.09l.311.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39z" clip-rule="evenodd"/>
                                </svg>
                                Questions will be presented in random order.
                            </li>
                        @endif
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M13.024 9.25c.47 0 .827-.433.637-.863a4 4 0 00-7.322 0c-.19.43.168.863.637.863h6.048zM12 11.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM13.25 12a.75.75 0 01.75-.75h2.25a.75.75 0 010 1.5H14a.75.75 0 01-.75-.75z"/>
                            </svg>
                            Your answers are auto-saved every 30 seconds.
                        </li>
                    </ul>
                </div>

                {{-- Action --}}
                <div class="p-6 text-center">
                    @if($inProgressAttempt)
                        <a href="{{ route('student.quiz.start', $quiz) }}" class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600 hover:shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M2 10a8 8 0 1116 0 8 8 0 01-16 0zm6.39-2.908a.75.75 0 01.766.027l3.5 2.25a.75.75 0 010 1.262l-3.5 2.25A.75.75 0 018 12.25v-4.5a.75.75 0 01.39-.658z" clip-rule="evenodd"/>
                            </svg>
                            Resume Quiz
                        </a>
                        <p class="mt-2 text-xs text-gray-500">You have an in-progress attempt.</p>
                    @elseif($canAttempt)
                        <a href="{{ route('student.quiz.start', $quiz) }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 hover:shadow-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M2 10a8 8 0 1116 0 8 8 0 01-16 0zm6.39-2.908a.75.75 0 01.766.027l3.5 2.25a.75.75 0 010 1.262l-3.5 2.25A.75.75 0 018 12.25v-4.5a.75.75 0 01.39-.658z" clip-rule="evenodd"/>
                            </svg>
                            Start Quiz
                        </a>
                    @else
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                            You have reached the maximum number of attempts for this quiz.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Previous Attempts --}}
            @if($userAttempts->where('completed_at', '!=', null)->count() > 0)
                <div class="mt-8">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900">Previous Attempts</h3>
                    <div class="space-y-3">
                        @foreach($userAttempts->whereNotNull('completed_at') as $pastAttempt)
                            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                                <div class="flex items-center gap-4">
                                    <div class="flex h-10 w-10 items-center justify-content-center rounded-full {{ $pastAttempt->passed ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600' }}">
                                        @if($pastAttempt->passed)
                                            <svg class="mx-auto h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                                            </svg>
                                        @else
                                            <svg class="mx-auto h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $pastAttempt->formatted_percentage }}
                                            <span class="ml-1 text-xs font-normal {{ $pastAttempt->passed ? 'text-emerald-600' : 'text-red-600' }}">
                                                ({{ $pastAttempt->passed ? 'Passed' : 'Failed' }})
                                            </span>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $pastAttempt->started_at->format('M d, Y h:i A') }} · {{ $pastAttempt->formatted_duration }}
                                        </p>
                                    </div>
                                </div>
                                <a href="{{ route('student.quiz.results', ['quiz' => $quiz->slug, 'attempt' => $pastAttempt->id]) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                    View Details →
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
