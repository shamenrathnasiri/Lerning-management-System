<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Quiz Analytics</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $quiz->title }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('instructor.quiz-analytics.export-excel', $quiz) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    <svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Excel
                </a>
                <a href="{{ route('instructor.quiz-analytics.export-csv', $quiz) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    CSV
                </a>
            </div>
        </div>
    </x-slot>

    @push('styles')
    <style>
        .stat-card{transition:all .25s ease;background:linear-gradient(135deg,#fff 0%,#f8fafc 100%)}
        .stat-card:hover{transform:translateY(-3px);box-shadow:0 12px 24px -8px rgba(0,0,0,.12)}
        .chart-box{border-radius:1rem;border:1px solid #e5e7eb;background:#fff;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.04)}
        .chart-box canvas{max-height:320px}
        .glass-header{background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#a855f7 100%);border-radius:1rem;padding:1.5rem 2rem;color:#fff;margin-bottom:2rem;position:relative;overflow:hidden}
        .glass-header::before{content:'';position:absolute;top:-50%;right:-20%;width:300px;height:300px;background:rgba(255,255,255,.08);border-radius:50%}
        .glass-header::after{content:'';position:absolute;bottom:-30%;left:-10%;width:200px;height:200px;background:rgba(255,255,255,.05);border-radius:50%}
        .badge-easy{background:#d1fae5;color:#065f46}.badge-medium{background:#fef3c7;color:#92400e}.badge-hard{background:#fee2e2;color:#991b1b}
        @keyframes countUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
        .stat-value{animation:countUp .5s ease forwards}
    </style>
    @endpush

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            {{-- ═══ HERO HEADER ═══ --}}
            <div class="glass-header">
                <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-2xl font-extrabold">{{ $quiz->title }}</h3>
                        <p class="mt-1 text-sm text-indigo-200">{{ $quiz->questions->count() }} Questions · Pass: {{ $quiz->pass_percentage }}% · {{ $quiz->formatted_time_limit }}</p>
                    </div>
                    <div class="flex gap-6 text-center">
                        <div><p class="text-3xl font-extrabold">{{ $totalAttempts }}</p><p class="text-xs text-indigo-200">Attempts</p></div>
                        <div><p class="text-3xl font-extrabold">{{ $averageScore }}%</p><p class="text-xs text-indigo-200">Avg Score</p></div>
                        <div><p class="text-3xl font-extrabold">{{ $passRate }}%</p><p class="text-xs text-indigo-200">Pass Rate</p></div>
                    </div>
                </div>
            </div>

            {{-- ═══ STAT CARDS ═══ --}}
            <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                @php $cards = [
                    ['label'=>'Total Attempts','value'=>$totalAttempts,'color'=>'indigo','icon'=>'M5.127 3.502L5.25 3.5h9.5c.041 0 .082 0 .123.002A2.251 2.251 0 0012.75 2h-5.5a2.25 2.25 0 00-2.123 1.502zM1 10.25A2.25 2.25 0 013.25 8h13.5A2.25 2.25 0 0119 10.25v5.5A2.25 2.25 0 0116.75 18H3.25A2.25 2.25 0 011 15.75v-5.5z'],
                    ['label'=>'Students','value'=>$uniqueStudents,'color'=>'blue','icon'=>'M7 8a3 3 0 100-6 3 3 0 000 6zm7.5 1a2.5 2.5 0 100-5 2.5 2.5 0 000 5z'],
                    ['label'=>'Avg Score','value'=>$averageScore.'%','color'=>'amber','icon'=>'M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z'],
                    ['label'=>'Pass Rate','value'=>$passRate.'%','color'=>'emerald','icon'=>'M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z'],
                    ['label'=>'Completion','value'=>$completionRate.'%','color'=>'purple','icon'=>'M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z'],
                    ['label'=>'Avg Time','value'=>intdiv($avgDuration,60).'m '.($avgDuration%60).'s','color'=>'rose','icon'=>'M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z'],
                ]; @endphp
                @foreach($cards as $card)
                <div class="stat-card rounded-2xl border border-gray-200 p-5 shadow-sm">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-{{ $card['color'] }}-100 text-{{ $card['color'] }}-600">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="{{ $card['icon'] }}"/></svg>
                    </div>
                    <p class="stat-value text-2xl font-extrabold text-gray-900">{{ $card['value'] }}</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $card['label'] }}</p>
                </div>
                @endforeach
            </div>

            {{-- ═══ SECONDARY STATS ═══ --}}
            <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-5">
                @php $mini = [
                    ['l'=>'Highest','v'=>$highestScore.'%','c'=>'text-emerald-600'],
                    ['l'=>'Lowest','v'=>$lowestScore.'%','c'=>'text-red-600'],
                    ['l'=>'Median','v'=>$medianScore.'%','c'=>'text-gray-900'],
                    ['l'=>'Std Dev','v'=>$stdDev,'c'=>'text-gray-900'],
                    ['l'=>'Passed / Failed','v'=>$passCount.' / '.$failCount,'c'=>'text-gray-900'],
                ]; @endphp
                @foreach($mini as $m)
                <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm text-center">
                    <p class="text-lg font-extrabold {{ $m['c'] }}">{{ $m['v'] }}</p>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">{{ $m['l'] }}</p>
                </div>
                @endforeach
            </div>

            {{-- ═══ CHARTS ROW 1 ═══ --}}
            <div class="mb-8 grid gap-6 lg:grid-cols-2">
                <div class="chart-box">
                    <h3 class="mb-4 text-sm font-bold text-gray-900">Score Distribution</h3>
                    <canvas id="scoreDistChart"></canvas>
                </div>
                <div class="chart-box">
                    <h3 class="mb-4 text-sm font-bold text-gray-900">Pass vs Fail</h3>
                    <div class="flex items-center justify-center"><canvas id="passFailChart" style="max-width:280px;max-height:280px"></canvas></div>
                </div>
            </div>

            {{-- ═══ CHARTS ROW 2 ═══ --}}
            <div class="mb-8 grid gap-6 lg:grid-cols-2">
                <div class="chart-box">
                    <h3 class="mb-4 text-sm font-bold text-gray-900">Question Difficulty (Correct Rate %)</h3>
                    <canvas id="questionDiffChart"></canvas>
                </div>
                <div class="chart-box">
                    <h3 class="mb-4 text-sm font-bold text-gray-900">Performance Trend</h3>
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            {{-- ═══ CHARTS ROW 3 ═══ --}}
            <div class="mb-8 grid gap-6 lg:grid-cols-2">
                <div class="chart-box">
                    <h3 class="mb-4 text-sm font-bold text-gray-900">Student Performance Bands</h3>
                    <canvas id="perfBandsChart"></canvas>
                </div>
                <div class="chart-box">
                    <h3 class="mb-4 text-sm font-bold text-gray-900">Time Per Question (avg seconds)</h3>
                    <canvas id="timePerQChart"></canvas>
                </div>
            </div>

            {{-- ═══ GRADE DISTRIBUTION ═══ --}}
            <div class="mb-8 chart-box">
                <h3 class="mb-4 text-sm font-bold text-gray-900">Grade Distribution</h3>
                <canvas id="gradeDistChart" height="120"></canvas>
            </div>

            {{-- ═══ QUESTION ANALYSIS TABLE ═══ --}}
            <div class="mb-8 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-sm font-bold text-gray-900">Question Analysis</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-6 py-3">#</th>
                                <th class="px-6 py-3">Question</th>
                                <th class="px-6 py-3">Type</th>
                                <th class="px-6 py-3">Points</th>
                                <th class="px-6 py-3">Attempts</th>
                                <th class="px-6 py-3">Correct %</th>
                                <th class="px-6 py-3">Difficulty</th>
                                <th class="px-6 py-3">Discrim.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($questionStats as $qs)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-3 font-semibold text-indigo-600">Q{{ $qs['index'] }}</td>
                                <td class="max-w-xs truncate px-6 py-3 text-gray-700">{{ $qs['text'] }}</td>
                                <td class="px-6 py-3"><span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600">{{ ucfirst(str_replace('_',' ',$qs['type'])) }}</span></td>
                                <td class="px-6 py-3 font-semibold">{{ $qs['points'] }}</td>
                                <td class="px-6 py-3">{{ $qs['total_answers'] }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-16 overflow-hidden rounded-full bg-gray-200">
                                            <div class="h-full rounded-full {{ $qs['correct_rate'] >= 70 ? 'bg-emerald-500' : ($qs['correct_rate'] >= 40 ? 'bg-amber-500' : 'bg-red-500') }}" style="width:{{ $qs['correct_rate'] }}%"></div>
                                        </div>
                                        <span class="text-xs font-semibold">{{ $qs['correct_rate'] }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold badge-{{ strtolower($qs['difficulty']) }}">{{ $qs['difficulty'] }}</span>
                                </td>
                                <td class="px-6 py-3">
                                    <span class="text-xs font-semibold {{ $qs['discrimination'] >= 0.3 ? 'text-emerald-600' : ($qs['discrimination'] >= 0.1 ? 'text-amber-600' : 'text-red-600') }}">
                                        {{ $qs['discrimination'] }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ═══ TOP PERFORMERS ═══ --}}
            <div class="mb-8 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4"><h3 class="text-sm font-bold text-gray-900">🏆 Top Performers</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr><th class="px-6 py-3">Rank</th><th class="px-6 py-3">Student</th><th class="px-6 py-3">Best Score</th><th class="px-6 py-3">Avg Score</th><th class="px-6 py-3">Attempts</th><th class="px-6 py-3">Actions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($topPerformers as $idx => $tp)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-3">
                                    @if($idx < 3)
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full {{ $idx === 0 ? 'bg-yellow-100 text-yellow-700' : ($idx === 1 ? 'bg-gray-100 text-gray-600' : 'bg-orange-100 text-orange-700') }} text-xs font-bold">{{ $idx+1 }}</span>
                                    @else
                                        <span class="ml-2 text-sm text-gray-500">{{ $idx+1 }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3"><p class="font-semibold text-gray-900">{{ $tp->user->name ?? 'N/A' }}</p><p class="text-xs text-gray-400">{{ $tp->user->email ?? '' }}</p></td>
                                <td class="px-6 py-3 font-bold text-emerald-600">{{ round($tp->best_score,1) }}%</td>
                                <td class="px-6 py-3 text-gray-700">{{ round($tp->avg_score,1) }}%</td>
                                <td class="px-6 py-3">{{ $tp->attempts_count }}</td>
                                <td class="px-6 py-3"><a href="{{ route('instructor.quiz-analytics.student-history', ['quiz'=>$quiz->slug,'user'=>$tp->user_id]) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">View History</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ═══ RECENT ATTEMPTS TABLE ═══ --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4"><h3 class="text-sm font-bold text-gray-900">Recent Attempts</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr><th class="px-6 py-3">Student</th><th class="px-6 py-3">Score</th><th class="px-6 py-3">Percentage</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Date</th><th class="px-6 py-3">Actions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($recentAttempts as $ra)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-3"><p class="font-semibold text-gray-900">{{ $ra->user->name ?? 'N/A' }}</p><p class="text-xs text-gray-400">{{ $ra->user->email ?? '' }}</p></td>
                                <td class="px-6 py-3 font-semibold">{{ $ra->score }} pts</td>
                                <td class="px-6 py-3"><span class="text-sm font-bold {{ $ra->passed ? 'text-emerald-600' : 'text-red-600' }}">{{ round($ra->percentage) }}%</span></td>
                                <td class="px-6 py-3"><span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $ra->passed ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">{{ $ra->passed ? 'Passed' : 'Failed' }}</span></td>
                                <td class="px-6 py-3 text-xs text-gray-500">{{ $ra->completed_at->format('M d, Y h:i A') }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('instructor.quiz-analytics.student-history', ['quiz'=>$quiz->slug,'user'=>$ra->user_id]) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">History</a>
                                        <a href="{{ route('instructor.quiz-analytics.export-pdf', ['quiz'=>$quiz->slug,'attempt'=>$ra->id]) }}" class="text-xs font-semibold text-gray-500 hover:text-gray-700">PDF</a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-400">No attempts recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const F="'Figtree','Inter',system-ui,sans-serif";
        Chart.defaults.font.family=F;Chart.defaults.font.size=12;Chart.defaults.color='#6b7280';Chart.defaults.plugins.legend.labels.usePointStyle=true;

        // 1. Score Distribution
        const sL=@json(array_keys($scoreRanges)),sV=@json(array_values($scoreRanges));
        const gradColors=['#fca5a5','#fdba74','#fde68a','#fef08a','#d9f99d','#bbf7d0','#a7f3d0','#6ee7b7','#34d399','#10b981'];
        new Chart(document.getElementById('scoreDistChart'),{type:'bar',data:{labels:sL.map(l=>l+'%'),datasets:[{label:'Students',data:sV,backgroundColor:gradColors,borderRadius:6,borderSkipped:false}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1,precision:0},grid:{color:'rgba(0,0,0,.04)'}},x:{grid:{display:false}}}}});

        // 2. Pass/Fail Doughnut
        new Chart(document.getElementById('passFailChart'),{type:'doughnut',data:{labels:['Passed','Failed'],datasets:[{data:[{{$passCount}},{{$failCount}}],backgroundColor:['#10b981','#ef4444'],borderWidth:0,hoverOffset:8}]},options:{responsive:true,cutout:'65%',plugins:{legend:{position:'bottom'}}}});

        // 3. Question Difficulty
        const qS=@json($questionStats);
        new Chart(document.getElementById('questionDiffChart'),{type:'bar',data:{labels:qS.map(q=>'Q'+q.index),datasets:[{label:'Correct %',data:qS.map(q=>q.correct_rate),backgroundColor:qS.map(q=>q.correct_rate>=70?'rgba(16,185,129,.7)':q.correct_rate>=40?'rgba(245,158,11,.7)':'rgba(239,68,68,.7)'),borderRadius:6,borderSkipped:false}]},options:{responsive:true,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,max:100,grid:{color:'rgba(0,0,0,.04)'}},y:{grid:{display:false}}}}});

        // 4. Trend with Moving Average
        const tr=@json($trendWithMA);
        new Chart(document.getElementById('trendChart'),{type:'line',data:{labels:tr.map(t=>t.date),datasets:[{label:'Score %',data:tr.map(t=>t.score),borderColor:'#4f46e5',backgroundColor:'rgba(79,70,229,.08)',tension:.4,fill:true,pointBackgroundColor:'#4f46e5',pointRadius:3,pointHoverRadius:6},{label:'Moving Avg (5)',data:tr.map(t=>t.moving_avg),borderColor:'#f59e0b',borderWidth:2,pointRadius:0,tension:.4,fill:false},{label:'Pass Threshold',data:tr.map(()=>{{$quiz->pass_percentage}}),borderColor:'rgba(239,68,68,.4)',borderDash:[6,3],pointRadius:0,fill:false}]},options:{responsive:true,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,max:100,grid:{color:'rgba(0,0,0,.04)'}},x:{grid:{display:false}}}}});

        // 5. Performance Bands
        const bands=@json($performanceBands);
        new Chart(document.getElementById('perfBandsChart'),{type:'bar',data:{labels:Object.keys(bands),datasets:[{label:'Students',data:Object.values(bands),backgroundColor:['#10b981','#3b82f6','#f59e0b','#f97316','#ef4444'],borderRadius:8,borderSkipped:false}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1,precision:0},grid:{color:'rgba(0,0,0,.04)'}},x:{grid:{display:false}}}}});

        // 6. Time Per Question
        const tpq=@json($timePerQuestion);
        if(tpq.length){new Chart(document.getElementById('timePerQChart'),{type:'bar',data:{labels:tpq.map(t=>t.label),datasets:[{label:'Avg Seconds',data:tpq.map(t=>t.seconds),backgroundColor:'rgba(99,102,241,.6)',borderRadius:6,borderSkipped:false}]},options:{responsive:true,plugins:{legend:{display:false},tooltip:{callbacks:{afterLabel:function(ctx){return tpq[ctx.dataIndex]?.type?.replace('_',' ')||''}}}},scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.04)'}},x:{grid:{display:false}}}}})}

        // 7. Grade Distribution
        const gd=@json($gradeDistribution);
        const gdColors=['#4f46e5','#6366f1','#818cf8','#a5b4fc','#93c5fd','#60a5fa','#fbbf24','#ef4444'];
        new Chart(document.getElementById('gradeDistChart'),{type:'bar',data:{labels:Object.keys(gd),datasets:[{label:'Students',data:Object.values(gd),backgroundColor:gdColors,borderRadius:6,borderSkipped:false}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1,precision:0},grid:{color:'rgba(0,0,0,.04)'}},x:{grid:{display:false}}}}});
    });
    </script>
    @endpush
</x-app-layout>
