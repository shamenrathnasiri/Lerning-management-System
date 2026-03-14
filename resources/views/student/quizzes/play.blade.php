<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ $quiz->title }}
            </h2>
            <span class="text-xs text-gray-400">Attempt #{{ $attempt->id }}</span>
        </div>
    </x-slot>

    {{-- ── Inline Styles ─────────────────────────────────────────── --}}
    @push('styles')
    <style>
        /* ── Quiz Player Variables ──────────────────────── */
        :root {
            --qp-primary: #4f46e5;
            --qp-primary-light: #818cf8;
            --qp-primary-dark: #3730a3;
            --qp-secondary: #10b981;
            --qp-danger: #ef4444;
            --qp-warning: #f59e0b;
            --qp-transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ── Connection Indicator ──────────────────────── */
        .qp-connection {
            position: fixed; top: 1rem; right: 1rem; z-index: 50;
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 999px;
            font-size: 0.7rem; font-weight: 600;
            backdrop-filter: blur(8px);
            transition: all var(--qp-transition);
        }
        .qp-connection.online  { background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.3); color: #059669; }
        .qp-connection.offline { background: rgba(239,68,68,.12);  border: 1px solid rgba(239,68,68,.3);  color: #dc2626; }
        .qp-connection-dot {
            width: 7px; height: 7px; border-radius: 50%; display: inline-block;
        }
        .qp-connection.online .qp-connection-dot  { background: #10b981; box-shadow: 0 0 6px rgba(16,185,129,.6); animation: qpPulse 2s ease-in-out infinite; }
        .qp-connection.offline .qp-connection-dot  { background: #ef4444; }

        @keyframes qpPulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.4);opacity:.6} }

        /* ── Timer ─────────────────────────────────────── */
        .qp-timer {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 16px; border-radius: 10px;
            font-weight: 700; font-size: 1.1rem;
            font-variant-numeric: tabular-nums;
            transition: all var(--qp-transition);
        }
        .qp-timer.normal  { background: #f3f4f6; color: #111827; }
        .qp-timer.warning { background: rgba(245,158,11,.15); color: #b45309; border: 1px solid rgba(245,158,11,.3); animation: qpTimerPulse 1s ease-in-out infinite; }
        .qp-timer.critical { background: rgba(239,68,68,.15); color: #dc2626; border: 1px solid rgba(239,68,68,.3); animation: qpTimerPulse .5s ease-in-out infinite; }
        @keyframes qpTimerPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.03)} }

        /* ── Progress Bar ──────────────────────────────── */
        .qp-progress-track { height: 8px; background: #f3f4f6; border-radius: 999px; overflow: hidden; flex: 1; }
        .qp-progress-fill  { height: 100%; background: linear-gradient(90deg, var(--qp-primary), var(--qp-primary-light)); border-radius: 999px; transition: width .5s cubic-bezier(.4,0,.2,1); }

        /* ── Palette Buttons ───────────────────────────── */
        .qp-palette-btn {
            width: 100%; aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
            border: 2px solid #e5e7eb; border-radius: 8px; background: #fff;
            font-size: .75rem; font-weight: 600; color: #6b7280;
            cursor: pointer; transition: all var(--qp-transition); position: relative;
        }
        .qp-palette-btn:hover { border-color: var(--qp-primary-light); color: var(--qp-primary); transform: scale(1.08); }
        .qp-palette-btn.current { background: var(--qp-primary); border-color: var(--qp-primary); color: #fff; box-shadow: 0 0 0 3px rgba(79,70,229,.2); }
        .qp-palette-btn.answered { background: rgba(16,185,129,.1); border-color: var(--qp-secondary); color: #065f46; }
        .qp-palette-btn.flagged  { border-color: #f59e0b; box-shadow: inset 0 0 0 2px rgba(245,158,11,.15); }
        .qp-palette-btn.current.answered { background: var(--qp-primary); color: #fff; }

        /* ── Question Card Animation ───────────────────── */
        .qp-question-card { animation: qpFadeUp .3s ease; }
        @keyframes qpFadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

        /* ── Option Cards ──────────────────────────────── */
        .qp-option {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 14px 18px; border: 2px solid #e5e7eb; border-radius: 12px;
            cursor: pointer; transition: all var(--qp-transition); background: #fff;
        }
        .qp-option:hover { border-color: var(--qp-primary-light); background: #eef2ff; transform: translateX(4px); }
        .qp-option.selected { border-color: var(--qp-primary); background: #eef2ff; box-shadow: 0 0 0 3px rgba(79,70,229,.1); }

        /* Radio */
        .qp-radio { width: 22px; height: 22px; border: 2px solid #d1d5db; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; transition: all var(--qp-transition); }
        .qp-option.selected .qp-radio { border-color: var(--qp-primary); }
        .qp-radio-inner { width: 10px; height: 10px; background: var(--qp-primary); border-radius: 50%; animation: qpScaleIn .2s ease; }
        @keyframes qpScaleIn { from{transform:scale(0)} to{transform:scale(1)} }

        /* Checkbox */
        .qp-checkbox { width: 22px; height: 22px; border: 2px solid #d1d5db; border-radius: 5px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; transition: all var(--qp-transition); }
        .qp-checkbox.checked { background: var(--qp-primary); border-color: var(--qp-primary); }

        /* Option letter badge */
        .qp-letter {
            width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center;
            background: #f3f4f6; border-radius: 6px; font-size: .75rem; font-weight: 700; color: #6b7280;
            flex-shrink: 0; transition: all var(--qp-transition);
        }
        .qp-option.selected .qp-letter { background: var(--qp-primary); color: #fff; }

        /* ── Drag Items ────────────────────────────────── */
        .qp-drag-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; background: #fff; border: 2px solid #e5e7eb; border-radius: 8px;
            cursor: grab; transition: all var(--qp-transition);
        }
        .qp-drag-item:hover { border-color: var(--qp-primary-light); background: #eef2ff; }
        .qp-drag-item:active { cursor: grabbing; }
        .qp-drag-item.dragging { opacity: .5; border-style: dashed; }

        /* ── Code Editor ───────────────────────────────── */
        .qp-code-editor {
            width: 100%; min-height: 250px; padding: 16px; background: #0f172a; color: #e2e8f0;
            border: none; font-family: 'Fira Code','Cascadia Code','JetBrains Mono',Consolas,monospace;
            font-size: .875rem; line-height: 1.8; resize: vertical; tab-size: 4; border-radius: 0 0 12px 12px;
        }
        .qp-code-editor:focus { outline: none; box-shadow: inset 0 0 0 2px rgba(56,189,248,.3); }

        /* ── Question type badges ──────────────────────── */
        .qp-type-badge { padding: 3px 10px; border-radius: 999px; font-size: .6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .qp-type-mc    { background: #dbeafe; color: #1e40af; }
        .qp-type-tf    { background: #dbeafe; color: #1e40af; }
        .qp-type-ms    { background: #e0e7ff; color: #3730a3; }
        .qp-type-sa    { background: #fef3c7; color: #92400e; }
        .qp-type-fb    { background: #fef3c7; color: #92400e; }
        .qp-type-essay { background: #fce7f3; color: #9d174d; }
        .qp-type-ord   { background: #d1fae5; color: #065f46; }
        .qp-type-match { background: #d1fae5; color: #065f46; }
        .qp-type-code  { background: #1e293b; color: #38bdf8; }

        /* ── Modal ─────────────────────────────────────── */
        .qp-modal-overlay {
            position: fixed; inset: 0; z-index: 60;
            display: flex; align-items: center; justify-content: center;
            background: rgba(0,0,0,.5); backdrop-filter: blur(4px); padding: 1rem;
        }
        .qp-modal {
            background: #fff; border-radius: 16px; width: 100%; max-width: 460px;
            padding: 2rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,.1);
            animation: qpSlideUp .3s ease;
        }
        @keyframes qpSlideUp { from{transform:translateY(20px);opacity:.5} to{transform:translateY(0);opacity:1} }

        /* ── Autosave dot ──────────────────────────────── */
        .qp-autosave-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .qp-autosave-dot.saving { background: #f59e0b; animation: qpPulse .8s ease-in-out infinite; }
        .qp-autosave-dot.saved  { background: #10b981; }
        .qp-autosave-dot.error  { background: #ef4444; }
        .qp-autosave-dot.idle   { background: #9ca3af; }

        /* ── Flag button ───────────────────────────────── */
        .qp-flag { transition: all var(--qp-transition); }
        .qp-flag:hover { color: #b45309; background: rgba(245,158,11,.05); }
        .qp-flag.active { background: rgba(245,158,11,.1); border-color: #f59e0b; color: #b45309; }
    </style>
    @endpush

    {{-- ── Quiz Player Alpine Component ──────────────────────── --}}
    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
             x-data="quizPlayer()"
             x-init="init()"
             x-on:beforeunload.window.prevent="!submitted"
        >
            {{-- Connection Status --}}
            <div class="qp-connection"
                 :class="online ? 'online' : 'offline'"
                 x-on:online.window="online = true"
                 x-on:offline.window="online = false"
            >
                <span class="qp-connection-dot"></span>
                <span x-text="online ? 'Connected' : 'Offline'"></span>
            </div>

            {{-- ── Top Header Bar ────────────────────────────── --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">{{ $quiz->title }}</h3>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">
                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M5.127 3.502 5.25 3.5h9.5c.041 0 .082 0 .123.002A2.251 2.251 0 0 0 12.75 2h-5.5a2.25 2.25 0 0 0-2.123 1.502zM1 10.25A2.25 2.25 0 0 1 3.25 8h13.5A2.25 2.25 0 0 1 19 10.25v5.5A2.25 2.25 0 0 1 16.75 18H3.25A2.25 2.25 0 0 1 1 15.75v-5.5z"/></svg>
                            Question <span x-text="currentIndex + 1"></span> of <span x-text="questions.length"></span>
                        </span>
                        @if($quiz->total_points > 0)
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            {{ $quiz->total_points }} Points
                        </span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    {{-- Timer --}}
                    @if($quiz->time_limit_minutes)
                    <div class="qp-timer"
                         :class="timerClass"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                        <span x-text="formattedTime"></span>
                    </div>
                    @endif
                    {{-- Autosave --}}
                    <div class="flex items-center gap-1.5 text-xs font-medium" :class="{'text-gray-400': autosaveStatus==='idle', 'text-amber-600': autosaveStatus==='saving', 'text-emerald-600': autosaveStatus==='saved', 'text-red-600': autosaveStatus==='error'}">
                        <span class="qp-autosave-dot" :class="autosaveStatus"></span>
                        <span x-text="autosaveLabel"></span>
                    </div>
                </div>
            </div>

            {{-- ── Progress Bar ──────────────────────────────── --}}
            <div class="mb-6 flex items-center gap-3">
                <div class="qp-progress-track">
                    <div class="qp-progress-fill" :style="'width:' + progressPct + '%'"></div>
                </div>
                <span class="whitespace-nowrap text-xs font-semibold text-gray-500" x-text="progressPct + '% Complete'"></span>
            </div>

            {{-- ── Body: Sidebar + Main ──────────────────────── --}}
            <div class="grid items-start gap-6 lg:grid-cols-[280px_1fr]">
                {{-- ── Sidebar: Question Palette ─────────────── --}}
                <aside class="sticky top-6 space-y-4 lg:order-1 order-2">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h4 class="mb-3 text-sm font-bold text-gray-900">Question Palette</h4>
                        <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                            <span class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>Answered</span>
                            <span class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-amber-400"></span>Flagged</span>
                            <span class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-indigo-600"></span>Current</span>
                            <span class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400"><span class="inline-block h-2.5 w-2.5 rounded-sm border border-gray-300 bg-white"></span>Not answered</span>
                        </div>
                        <div class="grid grid-cols-5 gap-1.5">
                            <template x-for="(q, idx) in questions" :key="q.id">
                                <button
                                    class="qp-palette-btn"
                                    :class="{
                                        current: idx === currentIndex,
                                        answered: isAnswered(q.id),
                                        flagged: flagged.includes(q.id)
                                    }"
                                    @click="goTo(idx)"
                                    x-text="idx + 1"
                                ></button>
                            </template>
                        </div>
                    </div>
                    {{-- Submit button --}}
                    <button @click="openSubmitModal()"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 px-4 py-3 text-sm font-semibold text-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg"
                            :disabled="submitting"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        Submit Quiz
                    </button>
                </aside>

                {{-- ── Main Question Area ────────────────────── --}}
                <main class="lg:order-2 order-1">
                    <template x-if="currentQuestion">
                        <div class="qp-question-card rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8" :key="currentIndex">
                            {{-- Question Header --}}
                            <div class="mb-5 flex flex-wrap items-center gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-indigo-800 text-sm font-extrabold text-white">
                                    Q<span x-text="currentIndex + 1"></span>
                                </div>
                                <div class="flex flex-1 flex-wrap items-center gap-2">
                                    <span class="qp-type-badge"
                                          :class="{
                                              'qp-type-mc': currentQuestion.type==='multiple_choice',
                                              'qp-type-tf': currentQuestion.type==='true_false',
                                              'qp-type-ms': currentQuestion.type==='multi_select',
                                              'qp-type-sa': currentQuestion.type==='short_answer',
                                              'qp-type-fb': currentQuestion.type==='fill_blank',
                                              'qp-type-essay': currentQuestion.type==='essay',
                                              'qp-type-ord': currentQuestion.type==='ordering',
                                              'qp-type-match': currentQuestion.type==='matching',
                                              'qp-type-code': currentQuestion.type==='code_challenge',
                                          }"
                                          x-text="typeLabel(currentQuestion.type)"
                                    ></span>
                                    <span class="text-xs text-gray-400" x-text="currentQuestion.points + (currentQuestion.points === 1 ? ' point' : ' points')"></span>
                                </div>
                                {{-- Flag button --}}
                                <button
                                    class="qp-flag inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-500"
                                    :class="{ active: flagged.includes(currentQuestion.id) }"
                                    @click="toggleFlag(currentQuestion.id)"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.5 2.75a.75.75 0 0 0-1.5 0v14.5a.75.75 0 0 0 1.5 0v-4.392l1.657-.348a6.449 6.449 0 0 1 4.271.572 7.948 7.948 0 0 0 5.965.524l.078-.028a.75.75 0 0 0 .468-.7V3.625a.75.75 0 0 0-1.02-.702 6.45 6.45 0 0 1-5.04-.362 7.948 7.948 0 0 0-5.272-.704l-.347.073V2.75z"/></svg>
                                    <span x-text="flagged.includes(currentQuestion.id) ? 'Flagged' : 'Flag'"></span>
                                </button>
                            </div>

                            {{-- Question Text --}}
                            <div class="mb-6 border-b border-gray-100 pb-5 text-base leading-7 text-gray-800" x-html="currentQuestion.question_text"></div>

                            {{-- ════ ANSWER AREA ════ --}}

                            {{-- ── Multiple Choice / True-False (Radio) ── --}}
                            <template x-if="currentQuestion.type === 'multiple_choice' || currentQuestion.type === 'true_false'">
                                <div class="space-y-3">
                                    <template x-for="(opt, oi) in currentQuestion.options" :key="opt.id">
                                        <div class="qp-option"
                                             :class="{ selected: answers[currentQuestion.id]?.option_id === opt.id }"
                                             @click="answers[currentQuestion.id] = { ...answers[currentQuestion.id], option_id: opt.id }"
                                        >
                                            <div class="qp-radio">
                                                <div class="qp-radio-inner" x-show="answers[currentQuestion.id]?.option_id === opt.id" x-transition.scale></div>
                                            </div>
                                            <span class="qp-letter" x-text="String.fromCharCode(65 + oi)"></span>
                                            <span class="text-sm leading-relaxed text-gray-800" x-html="opt.option_text"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- ── Multi-Select (Checkboxes) ────────── --}}
                            <template x-if="currentQuestion.type === 'multi_select'">
                                <div class="space-y-3">
                                    <template x-for="(opt, oi) in currentQuestion.options" :key="opt.id">
                                        <div class="qp-option"
                                             :class="{ selected: (answers[currentQuestion.id]?.option_ids || []).includes(opt.id) }"
                                             @click="toggleMulti(currentQuestion.id, opt.id)"
                                        >
                                            <div class="qp-checkbox" :class="{ checked: (answers[currentQuestion.id]?.option_ids || []).includes(opt.id) }">
                                                <svg x-show="(answers[currentQuestion.id]?.option_ids || []).includes(opt.id)" class="h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143z" clip-rule="evenodd"/></svg>
                                            </div>
                                            <span class="qp-letter" x-text="String.fromCharCode(65 + oi)"></span>
                                            <span class="text-sm leading-relaxed text-gray-800" x-html="opt.option_text"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            {{-- ── Short Answer / Fill-in-Blank ─────── --}}
                            <template x-if="currentQuestion.type === 'short_answer' || currentQuestion.type === 'fill_blank'">
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-400">Your Answer</label>
                                    <input type="text"
                                           class="w-full rounded-lg border-2 border-gray-200 px-4 py-3 text-sm text-gray-800 transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                           placeholder="Type your answer here..."
                                           :value="answers[currentQuestion.id]?.text || ''"
                                           @input="answers[currentQuestion.id] = { ...answers[currentQuestion.id], text: $event.target.value }"
                                    />
                                </div>
                            </template>

                            {{-- ── Essay ────────────────────────────── --}}
                            <template x-if="currentQuestion.type === 'essay'">
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-400">Your Essay Response</label>
                                    <textarea
                                        class="w-full rounded-lg border-2 border-gray-200 px-4 py-3 text-sm leading-7 text-gray-800 transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                        rows="8"
                                        placeholder="Write your essay response here..."
                                        :value="answers[currentQuestion.id]?.text || ''"
                                        @input="answers[currentQuestion.id] = { ...answers[currentQuestion.id], text: $event.target.value }"
                                    ></textarea>
                                    <p class="mt-1 text-right text-xs text-gray-400" x-text="(answers[currentQuestion.id]?.text || '').length + ' characters'"></p>
                                </div>
                            </template>

                            {{-- ── Ordering / Matching (Drag & Drop) ── --}}
                            <template x-if="currentQuestion.type === 'ordering' || currentQuestion.type === 'matching'">
                                <div>
                                    <p class="mb-3 text-xs font-medium text-gray-500">Drag items to reorder them in the correct sequence:</p>
                                    <div class="space-y-2"
                                         x-data="{ dragIdx: null }"
                                    >
                                        <template x-for="(item, idx) in getDragItems(currentQuestion)" :key="item.id">
                                            <div class="qp-drag-item"
                                                 :class="{ dragging: dragIdx === idx }"
                                                 draggable="true"
                                                 @dragstart="dragIdx = idx; $event.dataTransfer.effectAllowed = 'move'"
                                                 @dragover.prevent
                                                 @drop.prevent="dropItem(currentQuestion, dragIdx, idx); dragIdx = null"
                                                 @dragend="dragIdx = null"
                                            >
                                                <span class="text-gray-300">
                                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75zm0 10.5a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75zM2 10a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10z" clip-rule="evenodd"/></svg>
                                                </span>
                                                <span class="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-50 text-xs font-bold text-indigo-600" x-text="idx + 1"></span>
                                                <span class="text-sm text-gray-800" x-html="item.option_text"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- ── Code Challenge ──────────────────── --}}
                            <template x-if="currentQuestion.type === 'code_challenge'">
                                <div>
                                    <div class="overflow-hidden rounded-xl border-2 border-slate-700">
                                        <div class="flex items-center justify-between bg-slate-800 px-4 py-2">
                                            <span class="rounded bg-slate-900 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-sky-400" x-text="currentQuestion.code_language || 'plain'"></span>
                                            <button class="rounded px-2 py-1 text-xs font-medium text-slate-400 transition hover:bg-slate-700 hover:text-white" @click="answers[currentQuestion.id] = { ...answers[currentQuestion.id], text: currentQuestion.code_starter || '' }">Reset</button>
                                        </div>
                                        <textarea
                                            class="qp-code-editor"
                                            spellcheck="false"
                                            :placeholder="currentQuestion.code_starter || '// Write your code here...'"
                                            :value="answers[currentQuestion.id]?.text ?? (currentQuestion.code_starter || '')"
                                            @input="answers[currentQuestion.id] = { ...answers[currentQuestion.id], text: $event.target.value }"
                                            @keydown.tab.prevent="insertTab($event)"
                                        ></textarea>
                                    </div>
                                </div>
                            </template>

                            {{-- ── Fallback ────────────────────────── --}}
                            <template x-if="!['multiple_choice','true_false','multi_select','short_answer','fill_blank','essay','ordering','matching','code_challenge'].includes(currentQuestion.type)">
                                <div>
                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-400">Your Answer</label>
                                    <textarea
                                        class="w-full rounded-lg border-2 border-gray-200 px-4 py-3 text-sm text-gray-800 transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                                        rows="4"
                                        placeholder="Enter your answer..."
                                        :value="answers[currentQuestion.id]?.text || ''"
                                        @input="answers[currentQuestion.id] = { ...answers[currentQuestion.id], text: $event.target.value }"
                                    ></textarea>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- ── Navigation ─────────────────────────── --}}
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                        <button @click="prev()"
                                :disabled="currentIndex === 0"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:border-indigo-300 hover:text-indigo-600 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0z" clip-rule="evenodd"/></svg>
                            Previous
                        </button>
                        <button @click="saveAndContinue()"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-emerald-500 to-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143z" clip-rule="evenodd"/></svg>
                            Save &amp; Continue
                        </button>
                        <button @click="next()"
                                :disabled="currentIndex >= questions.length - 1"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:border-indigo-300 hover:text-indigo-600 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            Next
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                </main>
            </div>

            {{-- ═══ SUBMIT CONFIRMATION MODAL ═══ --}}
            <template x-if="showSubmitModal">
                <div class="qp-modal-overlay" @click.self="showSubmitModal = false">
                    <div class="qp-modal">
                        <div class="mb-4 text-center">
                            <svg class="mx-auto h-10 w-10 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5z" clip-rule="evenodd"/></svg>
                        </div>
                        <h3 class="mb-2 text-center text-xl font-bold text-gray-900">Submit Quiz?</h3>
                        <p class="mb-4 text-center text-sm text-gray-500">This action cannot be undone.</p>
                        <div class="mb-4 flex justify-center gap-6 rounded-xl bg-gray-50 p-4">
                            <div class="text-center">
                                <p class="text-2xl font-extrabold text-emerald-600" x-text="answeredCount"></p>
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Answered</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-extrabold text-amber-600" x-text="questions.length - answeredCount"></p>
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Unanswered</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-extrabold text-rose-500" x-text="flagged.length"></p>
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Flagged</p>
                            </div>
                        </div>
                        <template x-if="flagged.length > 0">
                            <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs font-medium text-amber-800">
                                ⚠ You have <span x-text="flagged.length"></span> flagged question(s) for review.
                            </p>
                        </template>
                        <div class="flex items-center justify-center gap-3">
                            <button @click="showSubmitModal = false" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Go Back</button>
                            <button @click="submitQuiz()" :disabled="submitting"
                                    class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:shadow-md disabled:opacity-60"
                            >
                                <span x-show="submitting" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                                <span x-text="submitting ? 'Submitting...' : 'Confirm Submit'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ═══ TIMEOUT WARNING MODAL ═══ --}}
            <template x-if="showTimeoutWarning">
                <div class="qp-modal-overlay">
                    <div class="qp-modal border-t-4 border-red-500">
                        <div class="mb-4 text-center">
                            <svg class="mx-auto h-10 w-10 animate-pulse text-rose-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6z" clip-rule="evenodd"/></svg>
                        </div>
                        <h3 class="mb-2 text-center text-xl font-bold text-rose-600">Time Running Out!</h3>
                        <p class="mb-5 text-center text-sm text-gray-500">
                            You have less than <strong x-text="Math.ceil(remainingSeconds / 60)"></strong> minute(s) remaining. Your quiz will be auto-submitted when time expires.
                        </p>
                        <div class="flex items-center justify-center gap-3">
                            <button @click="showTimeoutWarning = false" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Continue Working</button>
                            <button @click="showTimeoutWarning = false; openSubmitModal()" class="rounded-lg bg-gradient-to-r from-red-500 to-red-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:shadow-md">Submit Now</button>
                        </div>
                    </div>
                </div>
            </template>

        </div>
    </div>

    {{-- ── Alpine.js Component ───────────────────────────────── --}}
    @push('scripts')
    <script>
        function quizPlayer() {
            return {
                // ── Data ────────────────────────────────────
                questions: @json($questions->map(fn($q) => [
                    'id' => $q->id,
                    'type' => $q->type,
                    'question_text' => $q->question_text,
                    'points' => $q->points,
                    'code_language' => $q->code_language,
                    'code_starter' => $q->code_starter,
                    'options' => ($quiz->randomize_options
                        ? $q->options->shuffle()->values()
                        : $q->options
                    )->map(fn($o) => [
                        'id' => $o->id,
                        'option_text' => $o->option_text,
                        'sort_order' => $o->sort_order,
                        'match_key' => $o->match_key,
                    ]),
                ])),
                currentIndex: 0,
                answers: {},
                flagged: [],
                dragOrders: {},
                online: navigator.onLine,
                submitted: false,
                submitting: false,
                showSubmitModal: false,
                showTimeoutWarning: false,
                timeoutWarningShown: false,
                autosaveStatus: 'idle',
                autosaveLabel: 'Auto-save active',
                remainingSeconds: {{ ($quiz->time_limit_minutes ?? 0) * 60 }},
                hasTimeLimit: {{ $quiz->time_limit_minutes ? 'true' : 'false' }},
                timerInterval: null,
                autosaveInterval: null,
                csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
                attemptId: {{ $attempt->id }},

                // ── Computed ────────────────────────────────
                get currentQuestion() { return this.questions[this.currentIndex] || null; },
                get formattedTime() {
                    const h = Math.floor(this.remainingSeconds / 3600);
                    const m = Math.floor((this.remainingSeconds % 3600) / 60);
                    const s = this.remainingSeconds % 60;
                    if (h > 0) return h + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
                    return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
                },
                get timerClass() {
                    if (this.remainingSeconds <= 60) return 'critical';
                    if (this.remainingSeconds <= 300) return 'warning';
                    return 'normal';
                },
                get answeredCount() {
                    return Object.keys(this.answers).filter(qId => {
                        const a = this.answers[qId];
                        if (!a) return false;
                        if (a.option_id) return true;
                        if (a.option_ids?.length > 0) return true;
                        if (a.text?.trim()) return true;
                        return false;
                    }).length;
                },
                get progressPct() {
                    if (!this.questions.length) return 0;
                    return Math.round((this.answeredCount / this.questions.length) * 100);
                },

                // ── Init ───────────────────────────────────
                init() {
                    // Init answers
                    this.questions.forEach(q => {
                        if (!this.answers[q.id]) {
                            this.answers[q.id] = { option_id: null, option_ids: [], text: '' };
                        }
                    });
                    // Init drag orders
                    this.questions.filter(q => q.type === 'ordering' || q.type === 'matching').forEach(q => {
                        this.dragOrders[q.id] = [...q.options];
                    });

                    // Timer
                    if (this.hasTimeLimit) {
                        this.timerInterval = setInterval(() => {
                            if (this.remainingSeconds > 0) {
                                this.remainingSeconds--;
                                if (this.remainingSeconds === 300 && !this.timeoutWarningShown) {
                                    this.showTimeoutWarning = true;
                                    this.timeoutWarningShown = true;
                                }
                                if (this.remainingSeconds <= 0) {
                                    this.autoSubmit();
                                }
                            }
                        }, 1000);
                    }

                    // Auto-save every 30s
                    this.autosaveInterval = setInterval(() => this.autoSave(), 30000);

                    // Prevent accidental close
                    window.addEventListener('beforeunload', (e) => {
                        if (!this.submitted) { e.preventDefault(); e.returnValue = ''; }
                    });
                },

                // ── Navigation ──────────────────────────────
                goTo(idx) { this.currentIndex = idx; },
                prev()    { if (this.currentIndex > 0) this.currentIndex--; },
                next()    { if (this.currentIndex < this.questions.length - 1) this.currentIndex++; },
                saveAndContinue() {
                    if (this.currentIndex < this.questions.length - 1) this.currentIndex++;
                },

                // ── Answers ─────────────────────────────────
                isAnswered(qId) {
                    const a = this.answers[qId];
                    if (!a) return false;
                    return !!(a.option_id || a.option_ids?.length > 0 || a.text?.trim());
                },
                toggleMulti(qId, optId) {
                    const current = this.answers[qId]?.option_ids || [];
                    const idx = current.indexOf(optId);
                    const updated = [...current];
                    idx >= 0 ? updated.splice(idx, 1) : updated.push(optId);
                    this.answers[qId] = { ...this.answers[qId], option_ids: updated };
                },
                toggleFlag(qId) {
                    const idx = this.flagged.indexOf(qId);
                    idx >= 0 ? this.flagged.splice(idx, 1) : this.flagged.push(qId);
                },

                // ── Drag & Drop ─────────────────────────────
                getDragItems(q) { return this.dragOrders[q.id] || q.options; },
                dropItem(q, fromIdx, toIdx) {
                    if (fromIdx === null || fromIdx === toIdx) return;
                    const items = [...(this.dragOrders[q.id] || q.options)];
                    const [moved] = items.splice(fromIdx, 1);
                    items.splice(toIdx, 0, moved);
                    this.dragOrders[q.id] = items;
                    this.answers[q.id] = { ...this.answers[q.id], text: JSON.stringify(items.map(i => i.id)) };
                },

                // ── Code Editor Tab ─────────────────────────
                insertTab(e) {
                    const t = e.target;
                    const s = t.selectionStart;
                    const end = t.selectionEnd;
                    const v = t.value;
                    t.value = v.substring(0, s) + '    ' + v.substring(end);
                    t.selectionStart = t.selectionEnd = s + 4;
                    this.answers[this.currentQuestion.id] = { ...this.answers[this.currentQuestion.id], text: t.value };
                },

                // ── Helpers ─────────────────────────────────
                typeLabel(type) {
                    const map = {
                        multiple_choice: 'Multiple Choice', true_false: 'True / False',
                        multi_select: 'Multi-Select', short_answer: 'Short Answer',
                        fill_blank: 'Fill in the Blank', essay: 'Essay',
                        ordering: 'Ordering', matching: 'Matching', code_challenge: 'Code Challenge',
                    };
                    return map[type] || type;
                },

                // ── Auto-Save ───────────────────────────────
                async autoSave() {
                    if (!this.online) {
                        this.autosaveStatus = 'error';
                        this.autosaveLabel = 'Offline – waiting';
                        return;
                    }
                    this.autosaveStatus = 'saving';
                    this.autosaveLabel = 'Saving...';
                    try {
                        const resp = await fetch('{{ route("student.api.quiz.auto-save", ["quiz" => $quiz->slug, "attempt" => $attempt->id]) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ answers: this.buildPayload() }),
                        });
                        if (resp.ok) {
                            this.autosaveStatus = 'saved';
                            this.autosaveLabel = 'Saved just now';
                        } else { throw new Error(); }
                    } catch {
                        this.autosaveStatus = 'error';
                        this.autosaveLabel = 'Save failed';
                    }
                },

                buildPayload() {
                    const payload = [];
                    for (const [qId, a] of Object.entries(this.answers)) {
                        const q = this.questions.find(q => String(q.id) === String(qId));
                        if (!q) continue;
                        const entry = { question_id: Number(qId) };
                        if (['multiple_choice', 'true_false'].includes(q.type)) {
                            if (a.option_id) entry.question_option_id = a.option_id;
                        } else if (q.type === 'multi_select') {
                            entry.question_option_ids = a.option_ids || [];
                        } else {
                            entry.answer_text = a.text || '';
                        }
                        payload.push(entry);
                    }
                    return payload;
                },

                // ── Submit ──────────────────────────────────
                openSubmitModal() { this.showSubmitModal = true; },
                async autoSubmit() {
                    clearInterval(this.timerInterval);
                    this.submitting = true;
                    await this._doSubmit();
                },
                async submitQuiz() {
                    this.submitting = true;
                    this.showSubmitModal = false;
                    await this._doSubmit();
                },
                async _doSubmit() {
                    try {
                        const resp = await fetch('{{ route("student.api.quiz.submit", ["quiz" => $quiz->slug]) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                attempt_id: this.attemptId,
                                answers: this.buildPayload(),
                            }),
                        });
                        if (resp.ok) {
                            const data = await resp.json();
                            this.submitted = true;
                            clearInterval(this.timerInterval);
                            clearInterval(this.autosaveInterval);
                            window.location.href = '{{ route("student.quiz.results", ["quiz" => $quiz->slug, "attempt" => "__ATTEMPT__"]) }}'.replace('__ATTEMPT__', data.attempt_id || this.attemptId);
                        } else {
                            const err = await resp.json().catch(() => ({}));
                            alert(err.message || 'Failed to submit quiz.');
                            this.submitting = false;
                        }
                    } catch {
                        alert('Network error. Please try again.');
                        this.submitting = false;
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
