<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Report – {{ $quiz->title }}</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Figtree','Segoe UI',sans-serif;font-size:13px;color:#1f2937;line-height:1.6;padding:30px 40px}

        /* Header */
        .report-header{border-bottom:3px solid #4f46e5;padding-bottom:20px;margin-bottom:25px}
        .report-header h1{font-size:22px;font-weight:700;color:#111827;margin-bottom:4px}
        .report-header p{font-size:12px;color:#6b7280}
        .report-badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:700;margin-top:8px}
        .badge-pass{background:#d1fae5;color:#065f46}
        .badge-fail{background:#fee2e2;color:#991b1b}

        /* Student Info */
        .info-grid{display:table;width:100%;margin-bottom:25px}
        .info-row{display:table-row}
        .info-label{display:table-cell;width:140px;padding:6px 0;font-weight:600;color:#6b7280;font-size:12px}
        .info-value{display:table-cell;padding:6px 0;color:#111827}

        /* Summary Cards */
        .summary{margin-bottom:25px}
        .summary-table{width:100%;border-collapse:collapse}
        .summary-table td{padding:12px 16px;text-align:center;border:1px solid #e5e7eb}
        .summary-table .label{font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;font-weight:600}
        .summary-table .value{font-size:20px;font-weight:800;color:#111827}
        .summary-table .value.pass{color:#059669}
        .summary-table .value.fail{color:#dc2626}

        /* Performance Bar */
        .perf-bar{margin-bottom:25px;padding:15px 20px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb}
        .perf-bar-inner{height:18px;border-radius:9px;background:#e5e7eb;overflow:hidden;margin-top:8px}
        .perf-bar-fill{height:100%;border-radius:9px;transition:width .3s}
        .perf-bar-label{font-size:11px;color:#6b7280;display:flex;justify-content:space-between}

        /* Question Review */
        .section-title{font-size:15px;font-weight:700;color:#111827;margin:25px 0 12px;padding-bottom:8px;border-bottom:2px solid #e5e7eb}
        .q-card{margin-bottom:14px;border:1px solid #e5e7eb;border-radius:6px;page-break-inside:avoid;overflow:hidden}
        .q-header{padding:10px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb}
        .q-header span{font-size:12px}
        .q-num{font-weight:700;color:#4f46e5;margin-right:10px}
        .q-result{font-weight:700;float:right}
        .q-result.correct{color:#059669}
        .q-result.incorrect{color:#dc2626}
        .q-body{padding:12px 14px}
        .q-text{margin-bottom:8px;font-size:13px;line-height:1.7}

        /* Options */
        .opt{padding:5px 10px;margin:3px 0;border-radius:4px;font-size:12px}
        .opt-correct{background:rgba(16,185,129,.1);border-left:3px solid #10b981}
        .opt-wrong{background:rgba(239,68,68,.06);border-left:3px solid #ef4444;text-decoration:line-through;opacity:.7}
        .opt-neutral{border-left:3px solid transparent;color:#6b7280}

        /* Explanation */
        .explanation{margin-top:10px;padding:10px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:4px;font-size:11px;color:#1e40af}
        .explanation strong{display:block;margin-bottom:3px;font-size:11px}

        /* Answer text */
        .answer-text{margin-top:8px;padding:8px 12px;background:#f9fafb;border-radius:4px;font-size:12px}

        /* Comparison box */
        .comparison-box{margin-bottom:25px;padding:15px 20px;border:1px solid #e5e7eb;border-radius:8px}
        .comparison-box h4{font-size:13px;font-weight:700;color:#111827;margin-bottom:10px}
        .comp-row{display:flex;justify-content:space-between;padding:4px 0;font-size:12px}
        .comp-label{color:#6b7280}
        .comp-value{font-weight:700;color:#111827}

        /* Footer */
        .report-footer{margin-top:30px;padding-top:15px;border-top:1px solid #e5e7eb;text-align:center;font-size:10px;color:#9ca3af}
    </style>
</head>
<body>
    {{-- ── Header ─────────────────────────── --}}
    <div class="report-header">
        <h1>{{ $quiz->title }}</h1>
        <p>Quiz Performance Report · Generated {{ now()->format('F d, Y \a\t h:i A') }}</p>
        <span class="report-badge {{ $attempt->passed ? 'badge-pass' : 'badge-fail' }}">
            {{ $attempt->passed ? '✓ PASSED' : '✗ FAILED' }}
        </span>
    </div>

    {{-- ── Student Info ───────────────────── --}}
    <div class="info-grid">
        <div class="info-row"><div class="info-label">Student Name</div><div class="info-value">{{ $attempt->user->name ?? 'N/A' }}</div></div>
        <div class="info-row"><div class="info-label">Email</div><div class="info-value">{{ $attempt->user->email ?? 'N/A' }}</div></div>
        <div class="info-row"><div class="info-label">Attempt ID</div><div class="info-value">#{{ $attempt->id }}</div></div>
        <div class="info-row"><div class="info-label">Date</div><div class="info-value">{{ $attempt->completed_at?->format('F d, Y h:i A') ?? 'In Progress' }}</div></div>
        @php
            $dur = $attempt->started_at && $attempt->completed_at ? $attempt->started_at->diffInSeconds($attempt->completed_at) : 0;
            $dH = intdiv($dur, 3600); $dM = intdiv($dur % 3600, 60); $dS = $dur % 60;
            $durStr = $dH > 0 ? "{$dH}h {$dM}m {$dS}s" : ($dM > 0 ? "{$dM}m {$dS}s" : "{$dS}s");
        @endphp
        <div class="info-row"><div class="info-label">Duration</div><div class="info-value">{{ $durStr }}</div></div>
    </div>

    {{-- ── Summary Scores ─────────────────── --}}
    <div class="summary">
        <table class="summary-table">
            <tr>
                <td><div class="label">Score</div><div class="value">{{ $attempt->score }} / {{ $totalPoints }}</div></td>
                <td><div class="label">Percentage</div><div class="value {{ $attempt->passed ? 'pass' : 'fail' }}">{{ round($attempt->percentage) }}%</div></td>
                <td><div class="label">Correct Answers</div><div class="value">{{ $attempt->answers->where('is_correct', true)->count() }} / {{ $quiz->questions->count() }}</div></td>
                <td><div class="label">Pass Requirement</div><div class="value">{{ $quiz->pass_percentage }}%</div></td>
            </tr>
        </table>
    </div>

    {{-- ── Visual Performance Bar ──────────── --}}
    <div class="perf-bar">
        <div class="perf-bar-label">
            <span>Score: {{ round($attempt->percentage) }}%</span>
            <span>Pass: {{ $quiz->pass_percentage }}%</span>
        </div>
        <div class="perf-bar-inner">
            <div class="perf-bar-fill" style="width:{{ min(100, round($attempt->percentage)) }}%;background:{{ $attempt->passed ? '#10b981' : '#ef4444' }}"></div>
        </div>
    </div>

    {{-- ── Class Comparison ────────────────── --}}
    <div class="comparison-box">
        <h4>Performance Comparison</h4>
        <div class="comp-row"><span class="comp-label">Your Score</span><span class="comp-value" style="color:{{ $attempt->passed ? '#059669' : '#dc2626' }}">{{ round($attempt->percentage) }}%</span></div>
        <div class="comp-row"><span class="comp-label">Class Average</span><span class="comp-value">{{ $classAvg }}%</span></div>
        <div class="comp-row"><span class="comp-label">Percentile Rank</span><span class="comp-value">{{ $percentile }}th</span></div>
        <div class="comp-row"><span class="comp-label">vs Class Average</span><span class="comp-value" style="color:{{ round($attempt->percentage - $classAvg, 1) >= 0 ? '#059669' : '#dc2626' }}">{{ round($attempt->percentage - $classAvg, 1) >= 0 ? '+' : '' }}{{ round($attempt->percentage - $classAvg, 1) }}%</span></div>
    </div>

    {{-- ── Question Review ────────────────── --}}
    <div class="section-title">Question-by-Question Review</div>

    @foreach($attempt->answers as $idx => $answer)
        @php $question = $answer->question; @endphp
        @if($question)
        <div class="q-card">
            <div class="q-header">
                <span class="q-num">Q{{ $idx + 1 }}</span>
                <span>{{ ucfirst(str_replace('_', ' ', $question->type)) }} · {{ $question->points }} pts</span>
                <span class="q-result {{ $answer->is_correct ? 'correct' : 'incorrect' }}">
                    {{ $answer->is_correct ? '✓ Correct' : '✗ Incorrect' }} ({{ $answer->points_earned ?? 0 }} pts)
                </span>
            </div>
            <div class="q-body">
                <div class="q-text">{!! $question->question_text !!}</div>

                @if($question->options->count() > 0)
                    @foreach($question->options as $opt)
                        @php
                            $wasSelected = $opt->id === $answer->question_option_id;
                            $cls = 'opt-neutral';
                            if ($opt->is_correct && $wasSelected) $cls = 'opt-correct';
                            elseif ($wasSelected && !$opt->is_correct) $cls = 'opt-wrong';
                            elseif ($opt->is_correct) $cls = 'opt-correct';
                        @endphp
                        <div class="opt {{ $cls }}">
                            @if($wasSelected) ▸ @endif
                            {!! $opt->option_text !!}
                            @if($opt->is_correct) <em>(correct)</em> @endif
                        </div>
                    @endforeach
                @endif

                @if($answer->answer_text)
                    <div class="answer-text"><strong>Student's Answer:</strong> {{ $answer->answer_text }}</div>
                @endif

                @if($question->explanation)
                    <div class="explanation"><strong>💡 Explanation</strong>{!! $question->explanation !!}</div>
                @endif
            </div>
        </div>
        @endif
    @endforeach

    {{-- ── Footer ─────────────────────────── --}}
    <div class="report-footer">
        {{ config('app.name') }} · Quiz Performance Report · Generated {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
