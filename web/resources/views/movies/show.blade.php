@extends('layouts.app')

@section('title', $movie['title'] . ' - Chi tiết Phim')

@section('content')
<div class="space-y-8 max-w-5xl mx-auto">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('movies.index') }}" class="hover:text-white transition flex items-center gap-1">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Danh mục phim
        </a>
        <span>/</span>
        <span class="text-slate-200">{{ $movie['title'] }}</span>
    </div>

    <!-- Movie Detail Header Card -->
    <div class="relative overflow-hidden rounded-3xl glass-panel border border-slate-700/60 p-6 sm:p-8 shadow-2xl">
        <div class="flex flex-col md:flex-row gap-8 items-start">
            
            <!-- Movie Poster with onerror Fallback -->
            <div class="w-full md:w-72 shrink-0 aspect-[2/3] rounded-2xl overflow-hidden bg-slate-900 border border-slate-700 shadow-2xl">
                <img 
                    src="{{ $movie['poster'] }}" 
                    alt="{{ $movie['title'] }}" 
                    referrerpolicy="no-referrer"
                    class="w-full h-full object-cover"
                    onerror="this.onerror=null; this.src='{{ \App\Services\MovieDataService::generateDynamicSvgPoster($movie['title'], $movie['year'], $movie['genres'][0] ?? 'Drama', $movie['avgRating']) }}';"
                >
            </div>

            <!-- Movie Metadata & Rating Widget -->
            <div class="flex-1 space-y-6">
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="px-2.5 py-1 rounded-lg bg-brand-500/20 text-brand-400 text-xs font-bold border border-brand-500/30">
                            Năm {{ $movie['year'] }}
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-xs font-semibold border border-slate-700">
                            ID: #{{ $movie['movieId'] }}
                        </span>
                        @if($movie['hasFactor'])
                        <span class="px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-400 text-xs font-bold border border-emerald-500/30 flex items-center gap-1">
                            <i data-lucide="check" class="w-3 h-3"></i> Có trong ALS Item Factors
                        </span>
                        @endif
                    </div>

                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white leading-tight">
                        {{ $movie['title'] }}
                    </h1>

                    <!-- Genres -->
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        @foreach($movie['genres'] as $genre)
                        <span class="px-3 py-1 rounded-lg text-xs font-semibold bg-slate-800/90 text-slate-200 border border-slate-700/80">
                            {{ $genre }}
                        </span>
                        @endforeach
                    </div>
                </div>

                <!-- Community Rating vs Personal Rating -->
                <div class="grid grid-cols-2 gap-4 p-4 rounded-2xl bg-slate-900/60 border border-slate-800">
                    <div>
                        <div class="text-xs text-slate-400">Điểm cộng đồng MovieLens</div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-2xl font-black text-amber-400">{{ number_format($movie['avgRating'], 2) }}</span>
                            <div class="flex text-amber-400">
                                @for($i = 1; $i <= 5; $i++)
                                    <i data-lucide="star" class="w-4 h-4 {{ $i <= round($movie['avgRating']) ? 'fill-amber-400' : 'text-slate-600' }}"></i>
                                @endfor
                            </div>
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5">{{ $movie['ratingCount'] }} lượt đánh giá</div>
                    </div>

                    <div class="border-l border-slate-800 pl-4">
                        <div class="text-xs text-slate-400">Đánh giá của bạn ({{ $currentUser['name'] }})</div>
                        @if($personalRating)
                        <div class="flex items-center gap-2 mt-1 text-emerald-400">
                            <span class="text-2xl font-black">{{ $personalRating['rating'] }}⭐</span>
                            <span class="text-xs font-semibold">(Đã lưu MongoDB)</span>
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Chấm lúc: {{ $personalRating['ratedAt'] ?? 'Vừa xong' }}</div>
                        @else
                        <div class="text-sm font-semibold text-slate-400 mt-1">Chưa chấm điểm</div>
                        <div class="text-[11px] text-brand-400 mt-0.5">Chấm điểm bên dưới để cập nhật gợi ý</div>
                        @endif
                    </div>
                </div>

                <!-- Interactive Star Rating Form (Hỗ trợ đầy đủ thang 0.5 - 5.0) -->
                <div class="p-5 rounded-2xl bg-gradient-to-r from-slate-900/90 to-brand-950/30 border border-brand-500/30 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-white flex items-center gap-2">
                            <i data-lucide="star" class="w-4 h-4 text-amber-400 fill-amber-400"></i>
                            Chấm điểm bộ phim này (Thang điểm chuẩn MovieLens 0.5 - 5.0 ⭐)
                        </h3>
                    </div>

                    <form method="POST" action="{{ route('movies.rate', $movie['movieId']) }}" class="grid grid-cols-5 sm:grid-cols-10 gap-1.5">
                        @csrf
                        @foreach([0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0] as $star)
                        <button 
                            type="submit" 
                            name="rating" 
                            value="{{ $star }}"
                            class="py-2 px-1 rounded-xl font-bold text-[11px] transition flex flex-col items-center justify-center gap-0.5 border {{ ($personalRating['rating'] ?? 0) == $star ? 'bg-amber-500 text-slate-950 border-amber-400 shadow-lg shadow-amber-500/30 font-black scale-105' : 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700' }}"
                        >
                            <span>{{ $star }}</span>
                            <i data-lucide="star" class="w-3 h-3 fill-current"></i>
                        </button>
                        @endforeach
                    </form>
                    <p class="text-[11px] text-slate-400">
                        * Khi bạn bấm chấm điểm, điểm sẽ được lưu vào MongoDB và kích hoạt tăng <code>historyVersion</code> để cập nhật danh sách Top 10 gợi ý bằng thuật toán Fold-in ngay lập tức.
                    </p>
                </div>

                <!-- Overview -->
                <div class="space-y-2">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">Tóm tắt nội dung</h3>
                    <p class="text-slate-300 text-sm leading-relaxed">
                        {{ $movie['overview'] }}
                    </p>
                </div>

            </div>
        </div>
    </div>

    <!-- Similar Movies Section (Item-to-Item Similarity) -->
    @php
        $similarMovies = \App\Services\MovieDataService::getSimilarMovies($movie['movieId'], 4);
    @endphp
    @if(!empty($similarMovies))
    <div class="space-y-4">
        <h2 class="text-xl font-bold text-white flex items-center gap-2">
            <i data-lucide="sparkles" class="w-5 h-5 text-amber-400"></i>
            Phim Tương Tự (Item-to-Item Similarity: Vì bạn đang xem {{ $movie['title'] }})
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            @foreach($similarMovies as $sm)
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col justify-between group">
                <div class="relative aspect-[2/3] w-full bg-slate-900">
                    <img 
                        src="{{ $sm['poster'] }}" 
                        alt="{{ $sm['title'] }}" 
                        class="w-full h-full object-cover group-hover:scale-105 transition"
                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=500&auto=format&fit=crop&q=80';"
                    >
                    <div class="absolute top-2 right-2 px-2 py-0.5 rounded bg-emerald-500/90 text-slate-950 font-black text-[10px]">
                        {{ $sm['similarityScore'] }}% Tương đồng
                    </div>
                </div>
                <div class="p-3 space-y-1">
                    <h3 class="font-bold text-white text-xs truncate group-hover:text-brand-400 transition">
                        <a href="{{ route('movies.show', $sm['movieId']) }}">{{ $sm['title'] }}</a>
                    </h3>
                    <div class="text-[10px] text-slate-400 truncate">{{ implode(', ', $sm['genres']) }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
