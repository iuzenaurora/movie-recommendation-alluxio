@extends('layouts.app')

@section('title', 'Gợi ý Phim Đa Chế độ - Spark ALS & Fold-in')

@section('content')
<div class="space-y-8">

    <!-- Recommender Header & Performance Metrics -->
    <div class="relative overflow-hidden rounded-3xl p-8 glass-panel border border-brand-500/40 shadow-2xl">
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-brand-600/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-semibold uppercase tracking-wider">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Multi-Mode Recommendation Engine
                </div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-white">
                    Gợi ý Phim dành riêng cho <span class="bg-gradient-to-r from-brand-400 to-amber-400 bg-clip-text text-transparent">{{ $currentUser['name'] }}</span>
                </h1>
                <p class="text-slate-300 text-xs sm:text-sm">
                    Thuật toán <strong>Fold-in Real-time</strong> ước lượng vector người dùng tức thì dựa trên <strong>{{ $userRatingsCount }} phim</strong> bạn đã đánh giá theo thang điểm <strong>0.5 - 5.0 ⭐</strong>, loại trừ các phim đã xem.
                </p>
            </div>

            <!-- Action Button -->
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('recommendations.index', ['mode' => $currentMode]) }}" class="px-5 py-3 rounded-2xl bg-gradient-to-r from-brand-600 to-rose-600 hover:from-brand-500 hover:to-rose-500 text-white font-bold text-xs shadow-lg shadow-brand-500/30 transition flex items-center gap-2">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i> Tính lại Gợi ý
                </a>
            </div>
        </div>

        <!-- Latency & Model Version Badges -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 pt-6 border-t border-slate-800">
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                <div class="text-[11px] text-slate-400">Độ trễ tính toán Fold-in</div>
                <div class="text-lg font-bold text-emerald-400 mt-0.5">{{ $recData['latencyMs'] }} ms</div>
                <div class="text-[10px] text-slate-500">Vector RAM Computation</div>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                <div class="text-[11px] text-slate-400">Phiên bản Mô hình ALS</div>
                <div class="text-sm font-bold text-white mt-0.5 truncate">{{ $recData['modelVersion'] }}</div>
                <div class="text-[10px] text-indigo-400">Spark + Alluxio Cache</div>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                <div class="text-[11px] text-slate-400">Lịch sử Đánh giá</div>
                <div class="text-lg font-bold text-amber-400 mt-0.5">v{{ $recData['historyVersion'] }}</div>
                <div class="text-[10px] text-slate-500">MongoDB Sync Version</div>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                <div class="text-[11px] text-slate-400">Thể loại Ưa thích</div>
                <div class="text-sm font-bold text-cyan-400 mt-0.5">
                    {{ $recData['favoriteGenre'] ?? 'Đa dạng' }}
                </div>
                <div class="text-[10px] text-slate-500">Dựa trên rating cao</div>
            </div>
        </div>
    </div>

    <!-- 3 Recommendation Modes Navigation Tabs -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-800 pb-3">
        <a href="{{ route('recommendations.index', ['mode' => 'als_fold_in']) }}" class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ $currentMode === 'als_fold_in' ? 'bg-brand-600 text-white shadow-lg shadow-brand-500/30' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
            <i data-lucide="sparkles" class="w-4 h-4 text-amber-400"></i>
            1. Top 10 ALS Fold-in (Lọc Cộng tác)
        </a>
        <a href="{{ route('recommendations.index', ['mode' => 'genre']) }}" class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ $currentMode === 'genre' ? 'bg-brand-600 text-white shadow-lg shadow-brand-500/30' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
            <i data-lucide="tag" class="w-4 h-4 text-cyan-400"></i>
            2. Gợi ý theo Thể loại Gu của bạn ({{ $recData['favoriteGenre'] }})
        </a>
        <a href="{{ route('recommendations.index', ['mode' => 'trending']) }}" class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ $currentMode === 'trending' ? 'bg-brand-600 text-white shadow-lg shadow-brand-500/30' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
            <i data-lucide="flame" class="w-4 h-4 text-rose-400"></i>
            3. Phim Thịnh hành & Được xem nhiều nhất
        </a>
    </div>

    <!-- Cold Start Notice if any -->
    @if($recData['isFallback'])
    <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 flex items-center justify-between text-xs">
        <div class="flex items-center gap-3">
            <i data-lucide="info" class="w-5 h-5 text-amber-400 shrink-0"></i>
            <span>Bạn đang dùng tài khoản <strong>Cold-Start (chưa có đánh giá nào)</strong>. Hệ thống đang gợi ý các phim phổ biến nhất. Hãy chấm 1 - 2 phim để thuật toán Fold-in tính toán theo gu riêng của bạn!</span>
        </div>
        <a href="{{ route('movies.index') }}" class="px-3 py-1.5 rounded-lg bg-amber-500 text-slate-950 font-bold hover:bg-amber-400 transition shrink-0 ml-4">
            Đi chấm điểm
        </a>
    </div>
    @endif

    <!-- Recommendations Grid -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i data-lucide="award" class="w-5 h-5 text-amber-400"></i>
                Danh sách Phim được Đề xuất
            </h2>
            <span class="text-xs text-slate-400">
                Chế độ: <strong class="text-white uppercase">{{ str_replace('_', ' ', $currentMode) }}</strong>
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-5">
            @foreach($recData['recommendations'] as $index => $movie)
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col justify-between group relative">
                
                <div>
                    <!-- Rank Ribbon -->
                    <div class="absolute top-2 left-2 z-10 w-7 h-7 rounded-lg bg-brand-600 font-extrabold text-white text-xs flex items-center justify-center shadow-lg border border-brand-400">
                        #{{ $index + 1 }}
                    </div>

                    <!-- Match Score Badge -->
                    <div class="absolute top-2 right-2 z-10 px-2 py-0.5 rounded-md bg-emerald-500/90 text-slate-950 font-black text-[11px] shadow">
                        {{ $movie['matchPercentage'] }}% Match
                    </div>

                    <!-- Poster with onerror Fallback -->
                    <div class="relative aspect-[2/3] w-full overflow-hidden bg-slate-900">
                        <img 
                            src="{{ $movie['poster'] }}" 
                            alt="{{ $movie['title'] }}" 
                            referrerpolicy="no-referrer"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            loading="lazy"
                            onerror="this.onerror=null; this.src='{{ \App\Services\MovieDataService::generateDynamicSvgPoster($movie['title'], $movie['year'], $movie['genres'][0] ?? 'Drama', $movie['avgRating']) }}';"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent"></div>
                    </div>

                    <!-- Content -->
                    <div class="p-3.5 space-y-1.5">
                        <h3 class="font-bold text-white text-sm leading-snug line-clamp-1 group-hover:text-brand-400 transition">
                            <a href="{{ route('movies.show', $movie['movieId']) }}">{{ $movie['title'] }}</a>
                        </h3>
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Điểm dự đoán:</span>
                            <span class="font-extrabold text-amber-400">{{ $movie['predictedScore'] }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-3.5 pt-0">
                    <a href="{{ route('movies.show', $movie['movieId']) }}" class="block w-full py-1.5 rounded-lg bg-slate-800 hover:bg-brand-600 text-white text-center text-xs font-semibold transition">
                        Xem & Chấm điểm
                    </a>
                </div>

            </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
