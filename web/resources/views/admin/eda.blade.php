@extends('layouts.app')

@section('title', 'Dashboard Phân tích Dữ liệu Lớn - MovieLens EDA')

@section('content')
<div class="space-y-8">

    <!-- EDA Header -->
    <div class="rounded-3xl p-8 glass-panel border border-indigo-500/40 shadow-2xl">
        <div class="flex items-center gap-3 text-indigo-400 text-xs font-bold uppercase tracking-wider mb-2">
            <i data-lucide="bar-chart-2" class="w-4 h-4"></i> Exploratory Data Analysis • Big Data Pipeline
        </div>
        <h1 class="text-3xl font-extrabold text-white">Khảo sát & Phân tích Tập Dữ liệu MovieLens</h1>
        <p class="text-slate-300 text-sm mt-1">
            Tổng hợp các đặc trưng phân bố, độ thưa ma trận và quy tắc tiền xử lý làm sạch dữ liệu trước khi nạp vào Spark ALS & Alluxio.
        </p>
    </div>

    <!-- Big Data Key Metrics Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="p-4 rounded-2xl glass-card text-center">
            <div class="text-xs text-slate-400">Tổng Ratings Gốc</div>
            <div class="text-lg font-extrabold text-white mt-1">{{ $edaStats['rawRatingsCount'] }}</div>
            <div class="text-[10px] text-slate-500">GroupLens MovieLens</div>
        </div>
        <div class="p-4 rounded-2xl glass-card text-center">
            <div class="text-xs text-slate-400">Ratings Sau Làm Sạch</div>
            <div class="text-lg font-extrabold text-emerald-400 mt-1">{{ $edaStats['cleanedRatingsCount'] }}</div>
            <div class="text-[10px] text-emerald-500">Đã kiểm schema</div>
        </div>
        <div class="p-4 rounded-2xl glass-card text-center">
            <div class="text-xs text-slate-400">Dòng Bị Loại Bỏ</div>
            <div class="text-lg font-extrabold text-rose-400 mt-1">{{ $edaStats['removedRecords'] }}</div>
            <div class="text-[10px] text-rose-500">Trùng lặp / Lỗi ID</div>
        </div>
        <div class="p-4 rounded-2xl glass-card text-center">
            <div class="text-xs text-slate-400">Số Lượng User</div>
            <div class="text-lg font-extrabold text-indigo-400 mt-1">{{ $edaStats['totalUsers'] }}</div>
            <div class="text-[10px] text-slate-500">Tài khoản độc lập</div>
        </div>
        <div class="p-4 rounded-2xl glass-card text-center">
            <div class="text-xs text-slate-400">Số Lượng Phim</div>
            <div class="text-lg font-extrabold text-amber-400 mt-1">{{ $edaStats['totalMovies'] }}</div>
            <div class="text-[10px] text-slate-500">Danh mục đầy đủ</div>
        </div>
        <div class="p-4 rounded-2xl glass-card text-center">
            <div class="text-xs text-slate-400">Độ Thưa Ma Trận</div>
            <div class="text-lg font-extrabold text-cyan-400 mt-1">{{ $edaStats['matrixSparsity'] }}</div>
            <div class="text-[10px] text-cyan-500">Matrix Sparsity</div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Rating Distribution Chart -->
        <div class="p-6 rounded-3xl glass-panel border border-slate-700/80 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i data-lucide="pie-chart" class="w-4 h-4 text-amber-400"></i> Phân bố Điểm Đánh giá (0.5 - 5.0 ⭐)
                </h3>
                <span class="text-xs text-slate-400">Đơn vị: % tổng lượt</span>
            </div>
            <div class="h-64">
                <canvas id="ratingDistChart"></canvas>
            </div>
        </div>

        <!-- Top Genres Chart -->
        <div class="p-6 rounded-3xl glass-panel border border-slate-700/80 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i data-lucide="trending-up" class="w-4 h-4 text-brand-500"></i> Top Thể loại Phim phổ biến nhất
                </h3>
                <span class="text-xs text-slate-400">Triệu lượt đánh giá</span>
            </div>
            <div class="h-64">
                <canvas id="genreDistChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Data Cleaning Summary Table -->
    <div class="p-6 rounded-3xl glass-panel border border-slate-700/80 space-y-4">
        <h3 class="text-base font-bold text-white flex items-center gap-2">
            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i> Báo cáo Quy tắc Tiền xử lý & Làm sạch Dữ liệu
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] tracking-wider">
                        <th class="pb-3">Quy tắc làm sạch</th>
                        <th class="pb-3">Trạng thái kiểm tra</th>
                        <th class="pb-3">Số lượng bản ghi bị lọc</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @foreach($edaStats['cleaningRules'] as $rule)
                    <tr>
                        <td class="py-3 text-slate-200 font-medium">{{ $rule['rule'] }}</td>
                        <td class="py-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                {{ $rule['status'] }}
                            </span>
                        </td>
                        <td class="py-3 text-rose-400 font-semibold">{{ $rule['filtered'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

@push('scripts')
<script>
    // Rating Distribution Chart
    const ctxRating = document.getElementById('ratingDistChart').getContext('2d');
    new Chart(ctxRating, {
        type: 'bar',
        data: {
            labels: Object.keys(@json($edaStats['ratingDist'])),
            datasets: [{
                label: 'Tỷ lệ %',
                data: Object.values(@json($edaStats['ratingDist'])),
                backgroundColor: 'rgba(245, 158, 11, 0.7)',
                borderColor: '#f59e0b',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });

    // Top Genres Chart
    const ctxGenre = document.getElementById('genreDistChart').getContext('2d');
    new Chart(ctxGenre, {
        type: 'bar',
        data: {
            labels: Object.keys(@json($edaStats['topGenres'])),
            datasets: [{
                label: 'Lượt đánh giá',
                data: Object.values(@json($edaStats['topGenres'])).map(v => (v / 1000000).toFixed(1)),
                backgroundColor: 'rgba(225, 29, 72, 0.7)',
                borderColor: '#e11d48',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                y: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });
</script>
@endpush
@endsection
