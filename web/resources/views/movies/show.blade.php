@extends('layouts.app')
@section('title', $movie['title'].' · Movie Lab')
@section('content')
<div class="space-y-8">
<a href="{{ route('movies.index') }}" class="text-sm text-slate-400">← Khám phá phim</a>
<div class="grid md:grid-cols-[280px_1fr] gap-10">
<img src="{{ $movie['poster'] }}" alt="{{ $movie['title'] }}" class="w-full rounded-3xl aspect-[2/3] object-cover bg-slate-900" onerror="this.onerror=null;this.src='{{ $movie['fallbackPoster'] ?? '' }}'">
<div class="space-y-6 py-4">
<p class="text-rose-400 text-sm tracking-widest">MOVIELENS #{{ $movie['movieId'] }} · {{ $movie['year'] }}</p>
<h1 class="text-4xl md:text-5xl font-black leading-tight">{{ $movie['title'] }}</h1>
<div class="flex flex-wrap gap-2">@foreach($movie['genres'] as $genre)<a class="rounded-full border border-slate-700 px-4 py-1 text-sm" href="{{ route('movies.index', ['genre' => $genre]) }}">{{ $genre }}</a>@endforeach</div>
<p class="text-slate-400">{{ $movie['overview'] }}</p>
<p class="text-amber-300">{{ $movie['ratingCount'] > 0 ? number_format($movie['avgRating'], 2).' / 5 · '.number_format($movie['ratingCount']).' đánh giá MovieLens' : 'Chưa có điểm trung bình' }}</p>
@if($personalRating)<p class="text-emerald-300">Bạn đã chấm {{ $personalRating['rating'] }} / 5</p>@endif
@auth
<div class="p-6 rounded-2xl border border-slate-700 bg-slate-900"><h2 class="font-bold mb-4">Bạn đánh giá bộ phim này thế nào?</h2>
<form method="POST" action="{{ route('movies.rate', $movie['movieId']) }}" class="flex flex-wrap gap-2">@csrf
@foreach([0.5,1,1.5,2,2.5,3,3.5,4,4.5,5] as $rating)<button name="rating" value="{{ $rating }}" class="rounded-lg px-3 py-3 border {{ ($personalRating['rating'] ?? 0) == $rating ? 'bg-rose-600 border-rose-500' : 'border-slate-700 hover:border-rose-400' }}">{{ $rating }} ★</button>@endforeach</form>
<p class="text-xs text-slate-500 mt-4">Gợi ý cá nhân cập nhật theo đánh giá vừa lưu.</p></div>
@else<a href="{{ route('login') }}" class="primary inline-block">Đăng nhập để đánh giá</a>@endauth
</div></div>
<section class="space-y-4"><h2 class="text-2xl font-bold">Trailer</h2>
@if($trailerId)
<iframe class="w-full aspect-video rounded-2xl bg-black" src="https://www.youtube-nocookie.com/embed/{{ $trailerId }}" title="Trailer {{ $movie['title'] }}" allow="encrypted-media; picture-in-picture" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
<a class="text-sm text-rose-400" href="https://www.youtube.com/watch?v={{ $trailerId }}" target="_blank" rel="noopener noreferrer">Xem trên YouTube nếu video không cho phép nhúng ↗</a>
@elseif($studioUrl)<a href="{{ $studioUrl }}" target="_blank" rel="noopener noreferrer" class="primary inline-block">Xem trailer từ hãng phim ↗</a>
@else<p class="p-6 border border-slate-800 rounded-xl text-slate-400">Chưa có trailer được xác minh cho phim này.</p>@endif
</section></div>
@endsection
