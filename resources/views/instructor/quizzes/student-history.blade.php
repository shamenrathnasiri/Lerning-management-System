<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Student Quiz History</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $student->name }} · {{ $quiz->title }}</p>
            </div>
            <a href="{{ route('instructor.quiz-analytics.index', $quiz) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/></svg>
                Back to Analytics
            </a>
        </div>
    </x-slot>

    @push('styles')
    <style>
        .hist-card{transition:all .2s ease}
        .hist-card:hover{transform:translateY(-2px);box-shadow:0 10px 15px -3px rgba(0,0,0,.1)}
        .profile-glass{background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#a855f7 100%);border-radius:1rem;padding:1.75rem 2rem;color:#fff;position:relative;overflow:hidden}
        .profile-glass::before{content:'';position:absolute;top:-40%;right:-15%;width:250px;height:250px;background:rgba(255,255,255,.06);border-radius:50%}
        .chart-box{border-radius:1rem;border:1px solid #e5e7eb;background:#fff;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,.04)}
    </style>
    @endpush

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            {{-- ═══ STUDENT PROFILE CARD ═══ --}}
            <div class="profile-glass mb-8">
                <div class="relative z-10 flex flex-wrap items-center gap-6">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 text-2xl font-extrabold text-white shadow-md backdrop-blur-sm">
                        {{ strtoupper(substr($student->name, 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <h3 class="text-xl font-bold">{{ $student->name }}</h3>
                        <p class="text-sm text-indigo-200">{{ $student->email }}</p>
                        @if($rank)
                        <p class="mt-1 text-xs text-indigo-200">Rank #{{ $rank }} of {{ $totalStudents }} students</p>
                        @endif
                    </div>
                    <div class="flex gap-6 text-center">
                        <div><p class="text-2xl font-extrabold">{{ $attempts->count() }}</p><p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-200">Attempts</p></div>
                        <div><p class="text-2xl font-extrabold">{{ $bestScore }}%</p><p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-200">Best</p></div>
                        <div><p class="text-2xl font-extrabold {{ $improvement >= 0 ? '' : 'text-red-300' }}">{{ $improvement >= 0 ? '+' : '' }}{{ $improvement }}%</p><p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-200">Improvement</p></div>
                        <div><p class="text-2xl font-extrabold">{{ round($avgScore) }}%</p><p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-200">Average</p></div>
                        <div><p class="text-2xl font-extrabold">{{ $classAvg }}%</p><p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-200">Class Avg</p></div>
                    </div>
                </div>
            </div>

            {{-- ═══ COMPARISON STATS ═══ --}}
            <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @php $cmp = [
                    ['l'=>'Best Score','v'=>$bestScore.'%','c'=>'text-emerald-600'],
                    ['l'=>'Worst Score','v'=>$worstScore.'%','c'=>'text-red-600'],
                    ['l'=>'Avg Time','v'=>intdiv($avgTime,60).'m '.($avgTime%60).'s','c'=>'text-gray-900'],
                    ['l'=>'vs Class','v'=>(round($avgScore - $classAvg,1) >= 0 ? '+' : '').round($avgScore - $classAvg,1).'%','c'=>round($avgScore - $classAvg,1) >= 0 ? 'text-emerald-600' : 'text-red-600'],
                ]; @endphp
                @foreach($cmp as $c)
                <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm text-center">
                    <p class="text-lg font-extrabold {{ $c['c'] }}">{{ $c['v'] }}</p>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">{{ $c['l'] }}</p>
                </div>
                @endforeach
            </div>

            {{-- ═══ CHARTS ═══ --}}
            <div class="mb-8 grid gap-6 lg:grid-cols-2">
                @if($attempts->count() >= 2)
                <div class="chart-box">
                    <h3 class="mb-4 text-sm font-bold text-gray-900">Score Progress Over Attempts</h3>
                    <canvas id="improvementChart" height="220"></canvas>
                </div>
                @endif
                @if(count($questionPerformance) > 0)
                <div class="chart-box">
                    <h3 class="mb-4 text-sm font-bold text-gray-900">Per-Question Performance</h3>
                    <canvas id="radarChart" height="220"></canvas>
                </div>
                @endif
            </div>

            @if($attempts->count() >= 2)
            <div class="mb-8 chart-box">
                <h3 class="mb-4 text-sm font-bold text-gray-900">Time Per Attempt</h3>
                <canvas id="timeChart" height="140"></canvas>
            </div>
            @endif

            {{-- ═══ ALL ATTEMPTS ═══ --}}
            <div class="mb-8">
                <h3 class="mb-4 text-sm font-bold text-gray-900">All Attempts</h3>
                <div class="space-y-3">
                    @foreach($attempts as $idx => $attempt)
                        @php
                            $dur = $attempt->started_at && $attempt->completed_at ? $attempt->started_at->diffInSeconds($attempt->completed_at) : 0;
                            $dM = intdiv($dur, 60); $dS = $dur % 60;
                        @endphp
                        <div class="hist-card flex flex-wrap items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $attempt->passed ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' }} text-sm font-extrabold">
                                #{{ $idx + 1 }}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg font-bold {{ $attempt->passed ? 'text-emerald-600' : 'text-red-600' }}">{{ round($attempt->percentage) }}%</span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $attempt->passed ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">{{ $attempt->passed ? 'Passed' : 'Failed' }}</span>
                                    @if($idx > 0)
                                        @php $diff = round($attempt->percentage - $attempts[$idx-1]->percentage, 1); @endphp
                                        <span class="text-xs font-semibold {{ $diff >= 0 ? 'text-emerald-500' : 'text-red-500' }}">{{ $diff >= 0 ? '↑' : '↓' }} {{ abs($diff) }}%</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-gray-400">{{ $attempt->score }} pts · {{ $dM }}m {{ $dS }}s · {{ $attempt->completed_at->format('M d, Y h:i A') }}</p>
                            </div>
                            <a href="{{ route('instructor.quiz-analytics.export-pdf', ['quiz'=>$quiz->slug,'attempt'=>$attempt->id]) }}"
                               class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 hover:text-indigo-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                PDF Report
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ═══ WEAK & STRONG AREAS ═══ --}}
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center gap-2 border-b border-gray-200 px-6 py-4">
                        <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                        <h3 class="text-sm font-bold text-gray-900">Weak Areas (Most Missed)</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($weakAreas as $wa)
                        <div class="flex items-center gap-3 px-6 py-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-100 text-xs font-bold text-red-600">{{ $wa->miss_count }}×</span>
                            <div class="flex-1 min-w-0">
                                <p class="truncate text-sm text-gray-700">{{ strip_tags($wa->question->question_text ?? 'Deleted') }}</p>
                                <p class="text-[10px] text-gray-400 uppercase">{{ ucfirst(str_replace('_',' ',$wa->question->type ?? 'unknown')) }} · {{ $wa->question->points ?? 0 }} pts</p>
                            </div>
                        </div>
                        @empty
                        <p class="px-6 py-6 text-sm text-gray-400 text-center">No weak areas identified yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center gap-2 border-b border-gray-200 px-6 py-4">
                        <svg class="h-5 w-5 text-emerald-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        <h3 class="text-sm font-bold text-gray-900">Strong Areas (Always Correct)</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($strongAreas as $sa)
                        <div class="flex items-center gap-3 px-6 py-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-xs font-bold text-emerald-600">{{ $sa->correct_count }}×</span>
                            <div class="flex-1 min-w-0">
                                <p class="truncate text-sm text-gray-700">{{ strip_tags($sa->question->question_text ?? 'Deleted') }}</p>
                                <p class="text-[10px] text-gray-400 uppercase">{{ ucfirst(str_replace('_',' ',$sa->question->type ?? 'unknown')) }} · {{ $sa->question->points ?? 0 }} pts</p>
                            </div>
                        </div>
                        @empty
                        <p class="px-6 py-6 text-sm text-gray-400 text-center">No strong areas identified yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        Chart.defaults.font.family="'Figtree','Inter',system-ui,sans-serif";
        Chart.defaults.font.size=12;Chart.defaults.color='#6b7280';

        // Score Progress
        const scores=@json($scores);
        const impCanvas=document.getElementById('improvementChart');
        if(impCanvas&&scores.length>=2){
            new Chart(impCanvas,{type:'line',data:{labels:scores.map((_,i)=>'Attempt '+(i+1)),datasets:[
                {label:'Score %',data:scores.map(s=>parseFloat(s)),borderColor:'#4f46e5',backgroundColor:'rgba(79,70,229,.08)',tension:.4,fill:true,pointBackgroundColor:scores.map(s=>parseFloat(s)>={{$quiz->pass_percentage}}?'#10b981':'#ef4444'),pointRadius:6,pointHoverRadius:9,pointBorderWidth:2,pointBorderColor:'#fff'},
                {label:'Class Average',data:scores.map(()=>{{$classAvg}}),borderColor:'#f59e0b',borderDash:[4,4],pointRadius:0,fill:false},
                {label:'Pass Threshold',data:scores.map(()=>{{$quiz->pass_percentage}}),borderColor:'rgba(239,68,68,.4)',borderDash:[6,3],pointRadius:0,fill:false}
            ]},options:{responsive:true,plugins:{legend:{position:'bottom',labels:{usePointStyle:true}}},scales:{y:{beginAtZero:true,max:100,grid:{color:'rgba(0,0,0,.04)'}},x:{grid:{display:false}}}}});
        }

        // Radar Chart
        const qp=@json($questionPerformance);
        const radarCanvas=document.getElementById('radarChart');
        if(radarCanvas&&qp.length>0){
            new Chart(radarCanvas,{type:'radar',data:{labels:qp.map(q=>q.label),datasets:[{label:'Correct %',data:qp.map(q=>q.correct_rate),backgroundColor:'rgba(79,70,229,.15)',borderColor:'#4f46e5',pointBackgroundColor:'#4f46e5',pointRadius:4}]},options:{responsive:true,scales:{r:{beginAtZero:true,max:100,ticks:{stepSize:25},grid:{color:'rgba(0,0,0,.06)'},pointLabels:{font:{size:11}}}},plugins:{legend:{display:false}}}});
        }

        // Time Per Attempt
        const times=@json($timesPerAttempt);
        const timeCanvas=document.getElementById('timeChart');
        if(timeCanvas&&times.length>=2){
            new Chart(timeCanvas,{type:'bar',data:{labels:times.map((_,i)=>'#'+(i+1)),datasets:[{label:'Seconds',data:times,backgroundColor:'rgba(99,102,241,.5)',borderRadius:6,borderSkipped:false}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.04)'}},x:{grid:{display:false}}}}});
        }
    });
    </script>
    @endpush
</x-app-layout>
