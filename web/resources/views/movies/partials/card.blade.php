<article class="film-card">
    <a href="{{ route('movies.show', $movie['movieId']) }}" class="film-art" aria-label="Xem chi tiết {{ $movie['title'] }}">
        <img src="{{ $movie['poster'] }}" alt="{{ $movie['title'] }}" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src='{{ $movie['fallbackPoster'] }}'">
        <span class="film-play" aria-hidden="true"><i data-lucide="play"></i></span>
        <span class="film-year">{{ $movie['year'] ?: 'MovieLens' }}</span>
        @if(($movie['ratingCount'] ?? 0) > 0)<span class="film-score">★ {{ number_format($movie['avgRating'], 1) }}<small>/5</small></span>@endif
        @if(isset($userRating))<span class="film-rated">✓ Bạn chấm {{ $userRating }} ★</span>@endif
    </a>
    <div class="film-info"><h3><a href="{{ route('movies.show', $movie['movieId']) }}">{{ $movie['title'] }}</a></h3><p>{{ implode(' · ', array_slice($movie['genres'], 0, 2)) }}</p>
        @if(isset($movie['predictedScore']))<p class="film-prediction">Điểm xếp hạng: {{ $movie['predictedScore'] }}</p>@endif
    </div>
</article>
