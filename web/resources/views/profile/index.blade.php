@extends('layouts.app')
@section('title', 'Đánh giá của tôi · Movie Lab')
@section('content')
<div class="space-y-8"><p class="text-rose-400 text-xs tracking-widest">YOUR COLLECTION / RATINGS</p>
<h1 class="text-4xl font-black">Gu điện ảnh của {{ $currentUser['name'] }}</h1>
<p class="text-slate-400">{{ $totalRated }} phim đã đánh giá · Trung bình {{ $avgScore }} / 5 · Thể loại nổi bật {{ $topGenre }}</p>
<div class="space-y-3">
@forelse($ratings as $item)
<div class="flex flex-wrap gap-5 items-center rounded-2xl border border-slate-800 bg-slate-900 p-5">
<div class="flex-1"><a class="font-bold" href="{{ route('movies.show', $item['movieId']) }}">{{ $item['title'] }}</a><p class="text-xs text-slate-500 mt-2">{{ implode(' · ', $item['genres']) }}</p></div>
<span class="text-amber-300">{{ $item['userRating'] }} ★</span>
<a href="{{ route('movies.show', $item['movieId']) }}" class="text-sm text-rose-400">Sửa điểm</a>
<form method="POST" action="{{ route('profile.delete-rating', $item['movieId']) }}">@csrf @method('DELETE')<button class="text-sm text-slate-400">Xóa đánh giá</button></form>
</div>
@empty<div class="p-10 rounded-3xl border border-slate-800"><h2 class="font-bold text-xl mb-4">Bắt đầu với bộ phim bạn yêu thích.</h2><a href="{{ route('movies.index') }}" class="primary inline-block">Khám phá phim</a></div>@endforelse
</div>
<a class="primary inline-block" href="{{ route('recommendations.index') }}">Xem gợi ý dành cho bạn</a></div>
@endsection
