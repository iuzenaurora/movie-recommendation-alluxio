@extends('layouts.app')

@section('title', 'Gợi ý Phim Đa Chế độ - Spark ALS & Fold-in')

@section('content')
<div class="space-y-8">
    @if(!($apiOnline ?? false))
    <p class="p-4 rounded-xl bg-amber-950 text-amber-200">API chưa sẵn sàng. Đây là demo xếp hạng cục bộ theo thể loại, không phải kết quả ALS.</p>
    @else
    <p class="text-slate-400">Phương pháp: {{ $recData['method'] ?? 'ALS fold-in' }}. Điểm xếp hạng không phải xác suất phù hợp hoặc điểm sao cộng đồng.</p>
    @endif

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
                <div class="text-[11px] text-slate-400">Thời gian xử lý gợi ý</div>
                <div class="text-lg font-bold text-emerald-400 mt-0.5">{{ $recData['latencyMs'] }} ms</div>
                <div class="text-[10px] text-slate-500">Đo tại API cho mỗi yêu cầu</div>
            </div>
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                <div class="text-[11px] text-slate-400">Phiên bản Mô hình ALS</div>
                <div class="text-sm font-bold text-white mt-0.5 truncate">{{ $recData['modelVersion'] }}</div>
                <div class="text-[10px] text-indigo-400">Phiên bản model đang phục vụ</div>
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

    @if($currentMode === 'hybrid')
    <div class="glass-panel rounded-2xl p-5 text-sm text-slate-300 leading-7">Cá nhân hóa kết hợp <strong>ALS + sở thích thể loại</strong>. Điểm thích trên 3 sao tăng tín hiệu thể loại; điểm dưới 3 sao giảm tín hiệu. Đây là bước xếp hạng bổ sung, không phải kết quả ALS thuần hay một lần huấn luyện model mới.</div>
    @elseif($currentMode === 'als_fold_in')
    <div class="rounded-2xl p-5 border border-amber-500/20 text-sm text-amber-200 leading-7">Bạn đang xem <strong>ALS thuần</strong> để đối chiếu. Model hiện tại có thể ưu tiên các phim tài liệu điểm cao dù khác gu. Chọn <a class="underline" href="{{ route('recommendations.index', ['mode'=>'hybrid']) }}">ALS + gu phim</a> để kết hợp tín hiệu thể loại.</div>
    @endif
    @if(($recData['supportedRatings'] ?? 0) < 5)
    <p class="text-xs text-slate-400">Có {{ $recData['supportedRatings'] ?? 0 }} đánh giá dùng được cho fold-in. Ít đánh giá khiến gợi ý chưa ổn định; điểm cao và điểm thấp đều giúp phân biệt sở thích.</p>
    @endif
    <!-- Recommendation modes -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-800 pb-3">
        <a href="{{ route('recommendations.index', ['mode' => 'hybrid']) }}" class="px-4 py-2.5 rounded-xl text-xs font-bold transition {{ $currentMode === 'hybrid' ? 'bg-brand-600 text-white' : 'bg-slate-800 text-slate-300' }}">ALS + gu phim · Đề xuất</a>
        <a href="{{ route('recommendations.index', ['mode' => 'als_fold_in']) }}" class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ $currentMode === 'als_fold_in' ? 'bg-brand-600 text-white shadow-lg shadow-brand-500/30' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
            <i data-lucide="sparkles" class="w-4 h-4 text-amber-400"></i>
            Top 10 ALS thuần · Đối chiếu
        </a>
        <a href="{{ route('recommendations.index', ['mode' => 'genre']) }}" class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ $currentMode === 'genre' ? 'bg-brand-600 text-white shadow-lg shadow-brand-500/30' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
            <i data-lucide="tag" class="w-4 h-4 text-cyan-400"></i>
            Theo thể loại yêu thích ({{ $recData['favoriteGenre'] }})
        </a>
        <a href="{{ route('recommendations.index', ['mode' => 'trending']) }}" class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 {{ $currentMode === 'trending' ? 'bg-brand-600 text-white shadow-lg shadow-brand-500/30' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
            <i data-lucide="flame" class="w-4 h-4 text-rose-400"></i>
            Phim điểm cao cộng đồng
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
                        #{{ $loop->iteration }}
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
                            <span>Điểm xếp hạng:</span>
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
