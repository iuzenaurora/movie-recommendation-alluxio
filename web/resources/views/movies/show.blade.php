@extends('layouts.app')
@section('title', $movie['title'].' · Movie Lab')
@section('content')
<div class="space-y-8">
<a href="{{ route('movies.index') }}" class="text-sm text-slate-400">← Khám phá phim</a>
<div class="detail-hero">
<img src="{{ $movie['poster'] }}" alt="" class="detail-backdrop" onerror="this.onerror=null;this.src='{{ $movie['fallbackPoster'] ?? '' }}'">
<div class="space-y-6 py-4">
<p class="text-rose-400 text-sm tracking-widest">MOVIELENS #{{ $movie['movieId'] }} · {{ $movie['year'] }}</p>
<h1 class="detail-title">{{ $movie['title'] }}</h1>
<div class="flex flex-wrap gap-2">@foreach($movie['genres'] as $genre)<a class="rounded-full border border-slate-700 px-4 py-1 text-sm" href="{{ route('movies.index', ['genre' => $genre]) }}">{{ $genre }}</a>@endforeach</div>
<p class="detail-overview">{{ $movie['overview'] }}</p>
<p class="text-amber-300">{{ $movie['ratingCount'] > 0 ? number_format($movie['avgRating'], 2).' / 5 · '.number_format($movie['ratingCount']).' đánh giá MovieLens' : 'Chưa có điểm trung bình' }}</p>
@if($personalRating)<p class="text-emerald-300">Bạn đã chấm {{ $personalRating['rating'] }} / 5</p>@endif
@auth
<div class="rating-panel"><h2 class="font-bold mb-4">Bạn đánh giá bộ phim này thế nào?</h2>
<form method="POST" action="{{ route('movies.rate', $movie['movieId']) }}" class="rating-options">@csrf
@foreach([0.5,1,1.5,2,2.5,3,3.5,4,4.5,5] as $rating)<button name="rating" value="{{ $rating }}" aria-label="Chấm {{ $rating }} sao" class="{{ ($personalRating['rating'] ?? 0) == $rating ? 'is-selected' : '' }}">{{ $rating }} ★</button>@endforeach</form>
<p class="text-xs text-slate-500 mt-4">Gợi ý cá nhân cập nhật theo đánh giá vừa lưu.</p></div>
@else<a href="{{ route('login') }}" class="primary inline-block">Đăng nhập để đánh giá</a>@endauth
<div class="detail-actions"><a href="#trailer" class="primary"><i data-lucide="play"></i> Xem trailer</a><a href="{{ route('recommendations.index') }}" class="secondary">Tìm phim theo gu →</a></div>
</div><img src="{{ $movie['poster'] }}" alt="{{ $movie['title'] }}" class="detail-poster" onerror="this.onerror=null;this.src='{{ $movie['fallbackPoster'] ?? '' }}'"></div>
<section id="trailer" class="trailer-section space-y-4"><h2 class="text-2xl font-bold">Trailer</h2>
@if($trailerId)
<iframe class="w-full aspect-video rounded-2xl bg-black" src="https://www.youtube-nocookie.com/embed/{{ $trailerId }}" title="Trailer {{ $movie['title'] }}" allow="encrypted-media; picture-in-picture" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
<a class="text-sm text-rose-400" href="https://www.youtube.com/watch?v={{ $trailerId }}" target="_blank" rel="noopener noreferrer">Xem trên YouTube nếu video không cho phép nhúng ↗</a>
@elseif($studioUrl)<a href="{{ $studioUrl }}" target="_blank" rel="noopener noreferrer" class="primary inline-block">Xem trailer từ hãng phim ↗</a>
@else<p class="p-6 border border-slate-800 rounded-xl text-slate-400">Chưa có trailer được xác minh cho phim này.</p>@endif
</section></div>
@endsection
