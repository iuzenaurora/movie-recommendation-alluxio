@extends('layouts.app')

@section('title', 'Hồ sơ & Lịch sử Đánh giá')

@section('content')
<div class="space-y-8">

    <!-- Profile Header Card -->
    <div class="rounded-3xl p-8 glass-panel border border-slate-700/80 shadow-2xl">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
            <div class="flex items-center gap-5">
                <img src="{{ $currentUser['avatar'] }}" alt="{{ $currentUser['name'] }}" class="w-20 h-20 rounded-2xl object-cover ring-4 ring-brand-500/40 shadow-xl">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-extrabold text-white">{{ $currentUser['name'] }}</h1>
                        @if(($currentUser['role'] ?? '') === 'admin')
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">Admin</span>
                        @endif
                    </div>
                    <div class="text-xs font-semibold text-brand-400">{{ $currentUser['persona'] ?? 'User' }}</div>
                    <p class="text-xs text-slate-400 max-w-xl">{{ $currentUser['description'] ?? '' }}</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="flex-1 sm:flex-initial p-4 rounded-2xl bg-slate-900/60 border border-slate-800 text-center min-w-[100px]">
                    <div class="text-xs text-slate-400">Đã chấm</div>
                    <div class="text-xl font-bold text-white mt-0.5">{{ $totalRated }} phim</div>
                </div>
                <div class="flex-1 sm:flex-initial p-4 rounded-2xl bg-slate-900/60 border border-slate-800 text-center min-w-[100px]">
                    <div class="text-xs text-slate-400">Điểm TB</div>
                    <div class="text-xl font-bold text-amber-400 mt-0.5">{{ $avgScore }}⭐</div>
                </div>
                <div class="flex-1 sm:flex-initial p-4 rounded-2xl bg-slate-900/60 border border-slate-800 text-center min-w-[100px]">
                    <div class="text-xs text-slate-400">Gu phim</div>
                    <div class="text-sm font-bold text-emerald-400 mt-1 truncate">{{ $topGenre }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Persona Switcher Section -->
    <div class="space-y-3">
        <h2 class="text-sm font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
            <i data-lucide="users" class="w-4 h-4 text-brand-500"></i>
            Chuyển nhanh sang các Persona Demo khác (1-Click Switch)
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach($demoUsers as $du)
            <a href="{{ route('auth.quick-login', $du['id']) }}" class="p-3 rounded-2xl glass-card flex flex-col items-center text-center space-y-2 {{ $du['id'] == $currentUser['id'] ? 'border-brand-500 bg-brand-500/10' : '' }}">
                <img src="{{ $du['avatar'] }}" alt="{{ $du['name'] }}" class="w-10 h-10 rounded-xl object-cover">
                <div class="text-xs font-bold text-white truncate w-full">{{ $du['name'] }}</div>
                <div class="text-[10px] text-slate-400 truncate w-full">{{ $du['persona'] }}</div>
            </a>
            @endforeach
        </div>
    </div>

    <!-- Rating History List -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i data-lucide="history" class="w-5 h-5 text-amber-400"></i>
                Lịch sử Đánh giá Phim (Lưu bền vững MongoDB - historyVersion: v{{ $historyVersion }})
            </h2>
            <span class="text-xs text-slate-400">Có thể sửa điểm hoặc xóa rating</span>
        </div>

        <div class="space-y-3">
            @forelse($ratings as $item)
            <div class="p-4 rounded-2xl glass-card flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <img src="{{ $item['poster'] }}" alt="{{ $item['title'] }}" referrerpolicy="no-referrer" class="w-14 h-20 rounded-xl object-cover shrink-0" onerror="this.onerror=null; this.src='{{ \App\Services\MovieDataService::generateDynamicSvgPoster($item['title'], $item['year'], $item['genres'][0] ?? 'Drama', $item['userRating']) }}';">
                    <div class="space-y-1">
                        <h3 class="font-bold text-white text-base">
                            <a href="{{ route('movies.show', $item['movieId']) }}" class="hover:text-brand-400 transition">{{ $item['title'] }}</a>
                        </h3>
                        <div class="flex flex-wrap gap-1">
                            @foreach($item['genres'] as $g)
                            <span class="px-2 py-0.5 rounded text-[10px] bg-slate-800 text-slate-300 border border-slate-700">{{ $g }}</span>
                            @endforeach
                        </div>
                        <div class="text-[11px] text-slate-400">Thời gian đánh giá: {{ $item['ratedAt'] }}</div>
                    </div>
                </div>

                <!-- Rating Score & Actions -->
                <div class="flex items-center gap-4 shrink-0 justify-between sm:justify-end">
                    <div class="px-4 py-2 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 font-black text-sm flex items-center gap-1.5">
                        <i data-lucide="star" class="w-4 h-4 fill-amber-400"></i>
                        <span>{{ $item['userRating'] }} ⭐</span>
                    </div>

                    <a href="{{ route('movies.show', $item['movieId']) }}" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition">
                        Sửa điểm
                    </a>

                    <form method="POST" action="{{ route('profile.delete-rating', $item['movieId']) }}" onsubmit="return confirm('Bạn có chắc muốn xóa đánh giá này?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/30 text-rose-400 border border-rose-500/30 transition text-xs">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
            </div>
            @empty
            <div class="p-12 rounded-3xl glass-panel text-center">
                <i data-lucide="star-off" class="w-10 h-10 text-slate-600 mx-auto mb-2"></i>
                <div class="text-base font-bold text-white">Chưa có đánh giá nào</div>
                <p class="text-xs text-slate-400 mt-1">Hãy khám phá danh mục và chấm điểm cho các bộ phim bạn đã xem!</p>
                <a href="{{ route('movies.index') }}" class="inline-block mt-4 px-4 py-2 rounded-xl bg-brand-600 text-white text-xs font-semibold">
                    Khám phá phim ngay
                </a>
            </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
