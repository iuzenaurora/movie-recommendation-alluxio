@extends('layouts.app')
@section('title', 'Khám phá phim · MovieLab')
@section('content')
@php($isBrowsing = empty($query) && empty($selectedGenre) && $movies->currentPage() === 1)
<div class="discovery-page">
@if($isBrowsing && $movies->count())
    <section class="spotlight" data-spotlight aria-label="Tiêu điểm điện ảnh" aria-roledescription="carousel">
        @foreach($movies->take(4) as $featured)
            <article class="spotlight-slide {{ $loop->first ? 'is-active' : '' }}" data-slide @if(!$loop->first) hidden inert @endif aria-label="{{ $loop->iteration }} / {{ min(4, $movies->count()) }} · {{ $featured['title'] }}">
                <img class="spotlight-image" src="{{ $featured['poster'] }}" alt="" referrerpolicy="no-referrer" @if(!$loop->first) loading="lazy" @endif onerror="this.onerror=null;this.src='{{ $featured['fallbackPoster'] }}'">
                <div class="spotlight-shade"></div>
                <div class="spotlight-content">
                    <p class="eyebrow">MOVIELAB SPOTLIGHT</p><h1>{{ $featured['title'] }}</h1>
                    <div class="spotlight-meta"><span>{{ $featured['year'] ?: 'MovieLens' }}</span><span>Trailer & đánh giá</span>@if($featured['ratingCount'] > 0)<span class="spotlight-rating">★ {{ number_format($featured['avgRating'], 1) }} / 5</span>@endif</div>
                    <div class="spotlight-genres">@foreach(array_slice($featured['genres'], 0, 4) as $genre)<a href="{{ route('movies.index', ['genre' => $genre]) }}">{{ $genre }}</a>@endforeach</div>
                    <p class="spotlight-description">{{ $featured['overview'] }}</p>
                    <div class="spotlight-actions"><a class="primary" href="{{ route('movies.show', $featured['movieId']) }}"><i data-lucide="play"></i>Khám phá phim</a><a class="spotlight-personal" href="{{ route('recommendations.index') }}" aria-label="Tìm phim theo gu của bạn"><i data-lucide="sparkles"></i></a></div>
                </div>
            </article>
        @endforeach
        @if($movies->count() > 1)
            <div class="spotlight-controls"><button class="icon-button" data-hero-prev aria-label="Phim tiêu điểm trước">←</button>
                @foreach($movies->take(4) as $featured)<button class="spotlight-thumb {{ $loop->first ? 'is-active' : '' }}" data-hero-dot="{{ $loop->index }}" aria-label="Hiển thị {{ $featured['title'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><img src="{{ $featured['poster'] }}" alt="" onerror="this.onerror=null;this.src='{{ $featured['fallbackPoster'] }}'"></button>@endforeach
                <button class="icon-button" data-hero-next aria-label="Phim tiêu điểm tiếp theo">→</button><button class="icon-button" data-hero-pause aria-label="Tạm dừng chuyển phim" aria-pressed="false"><i data-lucide="pause"></i></button>
            </div>
        @endif
    </section>
    <section class="topic-section"><div class="shelf-heading"><div><p class="eyebrow">TÌM MỘT CẢM HỨNG</p><h2>Hôm nay, bạn muốn xem gì?</h2></div></div><div class="topic-grid">
        @foreach([['Adventure','Một chuyến phiêu lưu','compass'],['Animation','Thế giới hoạt hình','sparkles'],['Sci-Fi','Vượt ngoài tưởng tượng','orbit'],['Romance','Một chút rung động','heart'],['Thriller','Căng thẳng từng phút','zap']] as [$genre, $label, $icon])
            <a class="topic-card" href="{{ route('movies.index', ['genre' => $genre]) }}"><i data-lucide="{{ $icon }}"></i><strong>{{ $label }}</strong><span>{{ $genre }} <b>↗</b></span></a>
        @endforeach
    </div></section>
    <section class="movie-shelf" data-shelf><div class="shelf-heading"><div><p class="eyebrow">MỘT THẾ GIỚI ĐIỆN ẢNH</p><h2>Dừng lại ở một câu chuyện hay</h2></div><div class="shelf-arrows"><button class="icon-button" data-shelf-prev aria-label="Cuộn phim sang trái">←</button><button class="icon-button" data-shelf-next aria-label="Cuộn phim sang phải">→</button></div></div>
        <div class="film-rail" data-rail tabindex="0" aria-label="Dải phim trong danh mục">@foreach($movies->take(12) as $movie)@include('movies.partials.card', ['movie' => $movie, 'userRating' => $userRatingMap[$movie['movieId']] ?? null])@endforeach</div>
    </section>
    <section class="taste-banner"><div class="taste-icon"><i data-lucide="sparkles"></i></div><div><p class="eyebrow">GU PHIM CỦA RIÊNG BẠN</p><h2>Càng đánh giá, càng hiểu gu.</h2><p>Chia sẻ điểm sao cho những phim bạn đã xem. Khám phá gợi ý dành riêng cho mình.</p></div><a class="secondary" href="{{ route('recommendations.index') }}">Dành cho bạn →</a></section>
@endif
    <section id="catalog" class="catalog-section">
        <div class="shelf-heading"><div><p class="eyebrow">KHÁM PHÁ KHÔNG GIỚI HẠN</p><h2>{{ $query ? 'Kết quả tìm kiếm' : ($selectedGenre ?: 'Kho phim MovieLens') }}</h2><p class="section-note">{{ number_format($totalCount) }} phim{{ $query ? ' cho “'.$query.'”' : '' }} · Trang {{ $movies->currentPage() }} / {{ $movies->lastPage() }}</p></div></div>
        <form method="GET" action="{{ route('movies.index') }}" class="catalog-search"><label class="sr-only" for="catalog-query">Tên phim</label><i data-lucide="search" aria-hidden="true"></i><input id="catalog-query" name="q" value="{{ $query }}" placeholder="Tìm theo tên phim…">@if($selectedGenre)<input type="hidden" name="genre" value="{{ $selectedGenre }}">@endif<button type="submit" class="secondary">Tìm phim</button></form>
        <nav class="genre-tabs" aria-label="Lọc thể loại"><a href="{{ route('movies.index', ['q' => $query]) }}" class="{{ !$selectedGenre || $selectedGenre === 'all' ? 'active' : '' }}">Tất cả</a>@foreach($allGenres as $genre)<a class="{{ $selectedGenre === $genre ? 'active' : '' }}" href="{{ route('movies.index', ['genre' => $genre, 'q' => $query]) }}">{{ $genre }}</a>@endforeach</nav>
        <div class="film-grid">
            @forelse($movies as $movie)
                @include('movies.partials.card', ['movie' => $movie, 'userRating' => $userRatingMap[$movie['movieId']] ?? null])
            @empty
                <div class="catalog-empty"><i data-lucide="search-x"></i><h3>Chưa tìm thấy bộ phim phù hợp</h3><p>Thử tên phim khác hoặc bỏ bộ lọc thể loại.</p><a class="secondary" href="{{ route('movies.index') }}">Xem tất cả phim</a></div>
            @endforelse
        </div>
        @if($movies->hasPages())
            <nav class="catalog-pagination" aria-label="Phân trang danh mục"><span>{{ number_format($movies->firstItem()) }}–{{ number_format($movies->lastItem()) }} / {{ number_format($movies->total()) }} phim</span><div>
                @if(!$movies->onFirstPage())<a class="secondary" href="{{ $movies->previousPageUrl() }}">← Trước</a>@endif
                <span>{{ $movies->currentPage() }} / {{ $movies->lastPage() }}</span>
                @if($movies->hasMorePages())<a class="secondary" href="{{ $movies->nextPageUrl() }}">Sau →</a>@endif
            </div><form method="GET" action="{{ route('movies.index') }}"><input type="hidden" name="q" value="{{ $query }}"><input type="hidden" name="genre" value="{{ $selectedGenre }}"><label for="jump-page">Đến trang</label><input id="jump-page" type="number" name="page" min="1" max="{{ $movies->lastPage() }}" required><button class="icon-button" aria-label="Chuyển trang">→</button></form></nav>
        @endif
    </section>
</div>
@endsection
