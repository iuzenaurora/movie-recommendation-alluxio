@extends('layouts.app')

@section('title', 'Dashboard Thực nghiệm Benchmark - Alluxio vs HDFS vs Lustre')

@section('content')
<div class="space-y-8">

    <!-- Benchmark Header -->
    <div class="rounded-3xl p-8 glass-panel border border-emerald-500/40 shadow-2xl">
        <div class="flex items-center gap-3 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-2">
            <i data-lucide="gauge" class="w-4 h-4"></i> Performance Benchmark • 6 Storage Configurations
        </div>
        <h1 class="text-3xl font-extrabold text-white">Thực nghiệm So sánh Hiệu năng Bộ nhớ đệm Alluxio</h1>
        <p class="text-slate-300 text-sm mt-1">
            Đo đạc đối chứng giữa <strong>HDFS</strong> và <strong>Lustre</strong> qua 3 trạng thái: <em>Đọc trực tiếp</em>, <em>Alluxio Cache Rỗng (Cold)</em>, và <em>Alluxio Cache Ấm (Warm)</em> qua các Workloads S1, S2, S3.
        </p>
    </div>

    <!-- 6 Configurations Comparison Matrix Table -->
    <div class="p-6 rounded-3xl glass-panel border border-slate-700/80 space-y-4 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i data-lucide="layers" class="w-5 h-5 text-brand-500"></i> Bảng Kết quả Thực nghiệm 6 Cấu hình (5 Lần chạy độc lập)
            </h2>
            <span class="text-xs text-slate-400 font-mono">Environment: Linux Cluster • Spark 3.5.6</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] tracking-wider bg-slate-900/50">
                        <th class="p-3">Mã</th>
                        <th class="p-3">Cấu hình Lưu trữ</th>
                        <th class="p-3">Trạng thái Cache</th>
                        <th class="p-3">S1 Thời gian (Median ± IQR)</th>
                        <th class="p-3">S1 Thông lượng</th>
                        <th class="p-3">S2 Thời gian (Median)</th>
                        <th class="p-3">S2 Thông lượng</th>
                        <th class="p-3">Cache Hit %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @foreach($storageConfigs as $cfg)
                    <tr class="hover:bg-slate-800/40 transition">
                        <td class="p-3 font-bold font-mono" style="color: {{ $cfg['color'] }}">{{ $cfg['id'] }}</td>
                        <td class="p-3 font-bold text-slate-200">{{ $cfg['name'] }}</td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                {{ $cfg['cacheState'] }}
                            </span>
                        </td>
                        <td class="p-3 font-bold text-white font-mono">{{ $cfg['s1_median'] }}s <span class="text-slate-500 font-normal">± {{ $cfg['s1_iqr'] }}s</span></td>
                        <td class="p-3 font-extrabold text-emerald-400 font-mono">{{ $cfg['s1_throughput'] }} MiB/s</td>
                        <td class="p-3 font-bold text-white font-mono">{{ $cfg['s2_median'] }}s</td>
                        <td class="p-3 font-extrabold text-cyan-400 font-mono">{{ $cfg['s2_throughput'] }} MiB/s</td>
                        <td class="p-3 font-bold font-mono {{ str_contains($cfg['hitRatio'], '100%') ? 'text-emerald-400' : 'text-slate-400' }}">
                            {{ $cfg['hitRatio'] }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Throughput Chart Comparison -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- S1 Throughput Chart -->
        <div class="p-6 rounded-3xl glass-panel border border-slate-700/80 space-y-4">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i data-lucide="zap" class="w-4 h-4 text-amber-400"></i> Workload S1: Thông lượng Đọc Toàn bộ (MiB/s)
            </h3>
            <div class="h-64">
                <canvas id="s1ThroughputChart"></canvas>
            </div>
            <p class="text-[11px] text-slate-400 text-center">
                * Alluxio Warm (H2 & L2) đạt thông lượng đọc từ RAM lên đến <strong>160 - 170 MiB/s</strong> (gấp 3.8x so với HDFS direct).
            </p>
        </div>

        <!-- ALS Model Quality Metrics -->
        <div class="p-6 rounded-3xl glass-panel border border-slate-700/80 space-y-4 flex flex-col justify-between">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i data-lucide="award" class="w-4 h-4 text-brand-500"></i> Chất lượng Mô hình Lọc Cộng tác Spark ALS
            </h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-center">
                    <div class="text-[10px] text-slate-400 uppercase">RMSE Train</div>
                    <div class="text-xl font-extrabold text-white mt-1">{{ $modelMetrics['rmse_train'] }}</div>
                </div>
                <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-center">
                    <div class="text-[10px] text-slate-400 uppercase">RMSE Validation</div>
                    <div class="text-xl font-extrabold text-amber-400 mt-1">{{ $modelMetrics['rmse_val'] }}</div>
                </div>
                <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-center">
                    <div class="text-[10px] text-slate-400 uppercase">RMSE Test</div>
                    <div class="text-xl font-extrabold text-emerald-400 mt-1">{{ $modelMetrics['rmse_test'] }}</div>
                </div>
                <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-center">
                    <div class="text-[10px] text-slate-400 uppercase">Precision@10</div>
                    <div class="text-lg font-bold text-cyan-400 mt-1">{{ $modelMetrics['precision10'] }}</div>
                </div>
                <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-center">
                    <div class="text-[10px] text-slate-400 uppercase">Recall@10</div>
                    <div class="text-lg font-bold text-indigo-400 mt-1">{{ $modelMetrics['recall10'] }}</div>
                </div>
                <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-center">
                    <div class="text-[10px] text-slate-400 uppercase">NDCG@10</div>
                    <div class="text-lg font-bold text-brand-400 mt-1">{{ $modelMetrics['ndcg10'] }}</div>
                </div>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/40 border border-slate-800 text-xs text-slate-400 flex items-center justify-between">
                <span>Siêu tham số tối ưu:</span>
                <span class="font-mono text-slate-200">rank=10, regParam=0.05, maxIter=10</span>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
    const cfgs = @json($storageConfigs);
    const ctxS1 = document.getElementById('s1ThroughputChart').getContext('2d');
    new Chart(ctxS1, {
        type: 'bar',
        data: {
            labels: cfgs.map(c => c.id + ': ' + c.name.split(' ')[0]),
            datasets: [{
                label: 'Throughput (MiB/s)',
                data: cfgs.map(c => c.s1_throughput),
                backgroundColor: cfgs.map(c => c.color),
                borderRadius: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { 
                    title: { display: true, text: 'MiB/s', color: '#94a3b8' },
                    grid: { color: 'rgba(255,255,255,0.05)' }, 
                    ticks: { color: '#94a3b8' } 
                },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });
</script>
@endpush
@endsection
