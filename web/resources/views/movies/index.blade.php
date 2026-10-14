@extends('layouts.app')
@section('title', 'Khám phá phim · Movie Lab')
@section('content')
<div class="space-y-8">
<section class="hero-cinema grid md:grid-cols-[1.12fr_1fr] items-center px-7 md:px-12 py-10 md:py-12 gap-6">
<div class="space-y-6 relative z-10">
<p class="eyebrow flex items-center gap-3"><span class="w-6 h-px bg-rose-300"></span> Một vũ trụ điện ảnh. Gu của riêng bạn.</p>
<h1 class="text-4xl md:text-6xl font-black leading-[1.08] tracking-tight">Bộ phim tiếp theo.<br><span class="text-rose-300">Một cảm xúc mới.</span></h1>
<p class="text-sm leading-7 text-slate-400 max-w-md">Khám phá {{ number_format($totalCount) }} bộ phim. Xem trailer, chấm điểm và để những câu chuyện bạn yêu dẫn lối.</p>
<div class="flex flex-wrap gap-3 pt-1"><a href="{{ route('recommendations.index') }}" class="primary"><i data-lucide="sparkles" class="w-4 h-4"></i> Khám phá gu của tôi</a><a href="{{ route('movies.show', 2571) }}" class="secondary"><i data-lucide="play" class="w-4 h-4"></i> Xem trailer</a></div>
<div class="flex flex-wrap items-center gap-5 text-[11px] text-slate-500 pt-3"><span>✦ Đánh giá theo gu</span><span>✦ Gợi ý cá nhân hóa</span><span>✦ Trailer chính thức</span></div>
</div>
<div class="cinema-scene" aria-hidden="true">
<div class="cinema-orbit"></div>
@foreach($movies->take(3) as $featured)
<div class="poster-sculpture" style="--x:{{ [-100, 10, 110][$loop->index] }}px;--z:{{ [0, 75, -45][$loop->index] }}px;--r:{{ [-13, 3, 15][$loop->index] }}deg;--delay:{{ $loop->index * -2 }}s;z-index:{{ [2,3,1][$loop->index] }}">
<img src="{{ $featured['poster'] }}" alt="" onerror="this.onerror=null;this.src='{{ $featured['fallbackPoster'] }}'">
</div>
@endforeach
<div class="scene-label">Đánh giá <span class="text-rose-300 mx-2">→</span> Hiểu gu <span class="text-rose-300 mx-2">→</span> Tìm phim</div>
</div>
</section>
@if(!($apiOnline ?? false))<p class="text-amber-300">Catalog tạm thời chưa kết nối. Đang hiển thị danh mục dự phòng; đánh giá cần dịch vụ hoạt động.</p>@endif
    <!-- Search & Filter Bar -->
    <div class="space-y-4">
        <form method="GET" action="{{ route('movies.index') }}" class="flex flex-col md:flex-row gap-4">
            <!-- Live Search Input -->
            <div class="relative flex-1">
                <i data-lucide="search" class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input 
                    type="text" 
                    name="q" 
                    value="{{ $query ?? '' }}"
                    placeholder="Tìm kiếm phim theo tên (The Godfather, Inception, Matrix, Titanic, Toy Story, Pulp Fiction...)"
                    class="w-full pl-12 pr-4 py-3.5 rounded-2xl glass-panel text-white placeholder-slate-400 border border-slate-700/80 focus:outline-none focus:border-brand-500 transition shadow-lg text-sm"
                >
            </div>

            <!-- Submit Button -->
            <button type="submit" class="px-6 py-3.5 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-semibold shadow-lg shadow-brand-500/30 transition flex items-center justify-center gap-2 text-sm shrink-0">
                <i data-lucide="filter" class="w-4 h-4"></i> Lọc phim
            </button>
        </form>

        <!-- Genre Pills Filter -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
            <a href="{{ route('movies.index', ['q' => $query]) }}" class="px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition {{ empty($selectedGenre) || $selectedGenre === 'all' ? 'bg-brand-600 text-white shadow-lg shadow-brand-500/30' : 'bg-slate-800/80 text-slate-300 hover:bg-slate-700' }}">
                Tất cả thể loại
            </a>
            @foreach($allGenres as $g)
            <a href="{{ route('movies.index', ['genre' => $g, 'q' => $query]) }}" class="px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition {{ ($selectedGenre ?? '') === $g ? 'bg-brand-600 text-white shadow-lg shadow-brand-500/30' : 'bg-slate-800/80 text-slate-300 hover:bg-slate-700' }}">
                {{ $g }}
            </a>
            @endforeach
        </div>
    </div>

    <!-- Movie Catalog Grid -->
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                <i data-lucide="film" class="w-5 h-5 text-brand-500"></i>
                Kho Phim MovieLens (Trang {{ $movies->currentPage() }} / {{ $movies->lastPage() }} • {{ $totalCount }} phim)
            </h2>
            <div class="text-xs text-slate-400">
                Tài khoản hiện tại: <span class="font-semibold text-white">{{ $currentUser['name'] }}</span>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-5">
            @forelse($movies as $movie)
            @php
                $userRating = $userRatingMap[$movie['movieId']] ?? null;
            @endphp
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col group">
                <!-- Movie Poster Image with onerror Fallback -->
                <div class="relative aspect-[2/3] w-full overflow-hidden bg-slate-900">
                    <img 
                        src="{{ $movie['poster'] }}" 
                        alt="{{ $movie['title'] }}"
                        referrerpolicy="no-referrer"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                        loading="lazy"
                        onerror="this.onerror=null; this.src='{{ \App\Services\MovieDataService::generateDynamicSvgPoster($movie['title'], $movie['year'], $movie['genres'][0] ?? 'Drama', $movie['avgRating']) }}';"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/20 to-transparent opacity-80"></div>
                    
                    <!-- Community Rating Badge -->
                    <div class="absolute top-3 right-3 px-2.5 py-1 rounded-lg bg-slate-900/80 backdrop-blur border border-slate-700 text-amber-400 text-xs font-bold flex items-center gap-1 shadow">
                        <i data-lucide="star" class="w-3.5 h-3.5 fill-amber-400"></i>
                        <span>{{ number_format($movie['avgRating'], 1) }}</span>
                    </div>

                    <!-- Year Badge -->
                    <div class="absolute top-3 left-3 px-2 py-0.5 rounded-md bg-slate-900/80 backdrop-blur border border-slate-700 text-slate-300 text-[10px] font-semibold">
                        {{ $movie['year'] }}
                    </div>

                    <!-- Personal Rated Indicator -->
                    @if($userRating)
                    <div class="absolute bottom-3 left-3 px-2.5 py-1 rounded-lg bg-emerald-500/90 backdrop-blur text-white text-xs font-bold flex items-center gap-1.5 shadow">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                        <span>Đã chấm: {{ $userRating }}⭐</span>
                    </div>
                    @endif
                </div>

                <!-- Movie Info & Actions -->
                <div class="p-4 flex-1 flex flex-col justify-between space-y-3">
                    <div>
                        <h3 class="font-bold text-white text-base leading-snug line-clamp-1 group-hover:text-brand-400 transition">
                            <a href="{{ route('movies.show', $movie['movieId']) }}">
                                {{ $movie['title'] }}
                            </a>
                        </h3>

                        <!-- Genres -->
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach(array_slice($movie['genres'], 0, 3) as $genre)
                            <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-800 text-slate-300 border border-slate-700">
                                {{ $genre }}
                            </span>
                            @endforeach
                        </div>
                    </div>

                    <!-- Quick Rating Bar & Detail Button -->
                    <div class="pt-3 border-t border-slate-800 flex items-center justify-between gap-2">
                        <form method="POST" action="{{ route('movies.rate', $movie['movieId']) }}" class="flex items-center gap-1">
                            @csrf
                            <input type="hidden" name="rating" value="5.0">
                            <button type="submit" title="Chấm nhanh 5 sao" class="p-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/30 text-amber-400 border border-amber-500/30 transition text-xs font-semibold flex items-center gap-1">
                                <i data-lucide="star" class="w-3.5 h-3.5 fill-amber-400"></i> Chấm 5⭐
                            </button>
                        </form>

                        <a href="{{ route('movies.show', $movie['movieId']) }}" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium transition flex items-center gap-1">
                            Chi tiết <i data-lucide="chevron-right" class="w-3 h-3"></i>
                        </a>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full py-16 text-center glass-panel rounded-3xl">
                <i data-lucide="film" class="w-12 h-12 text-slate-600 mx-auto mb-3"></i>
                <h3 class="text-lg font-bold text-white">Không tìm thấy bộ phim nào</h3>
                <p class="text-sm text-slate-400 mt-1">Thử thay đổi từ khóa hoặc chọn thể loại khác.</p>
                <a href="{{ route('movies.index') }}" class="inline-block mt-4 px-4 py-2 rounded-xl bg-brand-600 text-white text-xs font-semibold">
                    Xem tất cả phim
                </a>
            </div>
            @endforelse
        </div>

        <!-- Custom Smart Pagination UI (Hỗ trợ 5,200+ trang từ 62k phim) -->
        @if($movies->hasPages())
        <div class="p-4 rounded-2xl glass-panel border border-slate-800 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-xs text-slate-400">
                Hiển thị từ <span class="font-bold text-white">{{ number_format($movies->firstItem()) }}</span> đến <span class="font-bold text-white">{{ number_format($movies->lastItem()) }}</span> trong tổng số <span class="font-bold text-brand-400">{{ number_format($movies->total()) }}</span> phim MovieLens
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                <!-- Previous Button -->
                @if($movies->onFirstPage())
                    <span class="px-3 py-2 rounded-xl bg-slate-900/50 text-slate-600 text-xs cursor-not-allowed border border-slate-800 flex items-center gap-1">
                        <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Trước
                    </span>
                @else
                    <a href="{{ $movies->previousPageUrl() }}" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-brand-600 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition flex items-center gap-1">
                        <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Trước
                    </a>
                @endif

                @php
                    $current = $movies->currentPage();
                    $last = $movies->lastPage();
                    $start = max(1, $current - 2);
                    $end = min($last, $current + 2);
                @endphp

                @if($start > 1)
                    <a href="{{ $movies->url(1) }}" class="px-3 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700 transition">1</a>
                    @if($start > 2)
                        <span class="px-1 text-slate-500 text-xs font-bold">...</span>
                    @endif
                @endif

                @for($p = $start; $p <= $end; $p++)
                    @if($p == $current)
                        <span class="px-3.5 py-2 rounded-xl bg-brand-600 text-white text-xs font-bold shadow-lg shadow-brand-500/30 border border-brand-400">
                            {{ $p }}
                        </span>
                    @else
                        <a href="{{ $movies->url($p) }}" class="px-3 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700 transition">
                            {{ $p }}
                        </a>
                    @endif
                @endfor

                @if($end < $last)
                    @if($end < $last - 1)
                        <span class="px-1 text-slate-500 text-xs font-bold">...</span>
                    @endif
                    <a href="{{ $movies->url($last) }}" class="px-3 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700 transition">{{ $last }}</a>
                @endif

                <!-- Next Button -->
                @if($movies->hasMorePages())
                    <a href="{{ $movies->nextPageUrl() }}" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-brand-600 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition flex items-center gap-1">
                        Sau <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </a>
                @else
                    <span class="px-3 py-2 rounded-xl bg-slate-900/50 text-slate-600 text-xs cursor-not-allowed border border-slate-800 flex items-center gap-1">
                        Sau <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </span>
                @endif

                <!-- Jump to Page Form -->
                <form method="GET" action="{{ route('movies.index') }}" class="inline-flex items-center gap-1.5 ml-2">
                    @if(!empty($query))<input type="hidden" name="q" value="{{ $query }}">@endif
                    @if(!empty($selectedGenre))<input type="hidden" name="genre" value="{{ $selectedGenre }}">@endif
                    <input type="number" name="page" min="1" max="{{ $movies->lastPage() }}" placeholder="Trang..." class="w-16 px-2 py-1.5 rounded-xl bg-slate-900 text-white text-xs border border-slate-700 focus:outline-none focus:border-brand-500 text-center">
                    <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-slate-800 hover:bg-brand-600 text-slate-200 hover:text-white text-xs font-medium border border-slate-700 transition">Đi</button>
                </form>
            </div>
        </div>
        @endif

    </div>

</div>
@endsection
