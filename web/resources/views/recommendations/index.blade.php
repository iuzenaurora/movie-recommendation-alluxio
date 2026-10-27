@extends('layouts.app')
@section('title', 'Dành cho bạn · MovieLab')
@section('content')
<div class="space-y-7">
    <section class="recommendation-hero"><p class="eyebrow">MỖI NGƯỜI MỘT GU PHIM</p><h1>Một chút bất ngờ.<br>Một bộ phim dành cho bạn.</h1><p>Chào {{ $currentUser['name'] }}. Từ {{ $userRatingsCount }} phim bạn đã đánh giá, khám phá những câu chuyện tiếp theo.</p><div class="flex flex-wrap gap-3 mt-6"><a class="primary" href="{{ route('movies.index') }}">Khám phá & đánh giá</a><a class="secondary" href="{{ route('recommendations.index', ['mode' => $currentMode]) }}"><i data-lucide="refresh-cw"></i>Làm mới gợi ý</a></div></section>
    <nav class="recommendation-modes" aria-label="Cách gợi ý phim">
        @foreach(['hybrid' => 'Dành cho bạn · ALS + gu phim', 'als_fold_in' => 'ALS thuần · Đối chiếu', 'genre' => 'Theo thể loại yêu thích', 'trending' => 'Phim điểm cao cộng đồng'] as $mode => $label)
            <a href="{{ route('recommendations.index', ['mode' => $mode]) }}" class="{{ $currentMode === $mode ? 'active' : '' }}" @if($currentMode === $mode) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @if($recData['isFallback'])
        <div class="taste-banner"><div class="taste-icon"><i data-lucide="sparkles"></i></div><div><h2>Hãy để MovieLab hiểu gu của bạn</h2><p>Chưa có đánh giá dùng được cho mô hình. Tạm thời hiển thị phim phổ biến; hãy chấm vài phim bạn đã xem để bắt đầu cá nhân hóa.</p></div><a class="secondary" href="{{ route('movies.index') }}">Chọn phim để chấm →</a></div>
    @elseif(($recData['supportedRatings'] ?? 0) < 5)
        <p class="recommendation-explanation">Có {{ $recData['supportedRatings'] ?? 0 }} đánh giá dùng được cho mô hình. Thêm điểm cao và điểm thấp cho những phim đã xem để hệ thống phân biệt sở thích rõ hơn.</p>
    @endif
    <section><div class="shelf-heading"><div><p class="eyebrow">CÂU CHUYỆN TIẾP THEO</p><h2>{{ $currentMode === 'trending' ? 'Phim điểm cao cộng đồng' : 'Có thể bạn sẽ thích' }}</h2></div><span class="section-note">{{ count($recData['recommendations']) }} phim</span></div>
        <div class="film-grid">@forelse($recData['recommendations'] as $movie)@include('movies.partials.card', ['movie' => $movie, 'userRating' => null])@empty<div class="catalog-empty"><h3>Chưa có phim phù hợp trong danh sách</h3><p>Thử một cách gợi ý khác hoặc khám phá thêm danh mục phim.</p><a class="secondary" href="{{ route('movies.index') }}">Khám phá phim</a></div>@endforelse</div>
    </section>
    <details class="recommendation-diagnostics"><summary>Vì sao có những gợi ý này? · Xem cách tính</summary>
        <div class="recommendation-explanation">
            @if($currentMode === 'hybrid')
                Cá nhân hóa kết hợp <strong>ALS + sở thích thể loại</strong>. Điểm trên 3 sao tăng tín hiệu thể loại, dưới 3 sao giảm tín hiệu. Đây là bước xếp hạng bổ sung sau ALS, không phải một lần huấn luyện mới.
            @elseif($currentMode === 'als_fold_in')
                <strong>ALS thuần</strong> tính vector sở thích từ điểm bạn chấm và vector phim đã học. Phim chưa chấm được xếp theo tích vô hướng, không cộng điểm thể loại.
            @elseif($currentMode === 'genre')
                Lọc theo thể loại yêu thích <strong>{{ $recData['favoriteGenre'] }}</strong>, rồi xếp hạng bằng ALS. Nếu chưa xác định được thể loại yêu thích, bộ lọc thể loại chưa được áp dụng.
            @else
                Danh sách phim điểm cao từ dữ liệu cộng đồng, không tính vector sở thích cá nhân.
            @endif
            <p class="mt-3">Phương pháp đang trả kết quả: {{ $recData['method'] }}. Điểm xếp hạng không phải xác suất phù hợp hay điểm sao cộng đồng; các phim đã đánh giá được loại khỏi danh sách.</p>
        </div>
        <dl><div><dt>Mô hình đang phục vụ</dt><dd>{{ $recData['modelVersion'] }}</dd></div><div><dt>Thời gian xử lý tại API</dt><dd>{{ $recData['latencyMs'] }} ms</dd></div><div><dt>Phiên bản lịch sử đánh giá</dt><dd>{{ $recData['historyVersion'] }}</dd></div></dl>
    </details>
</div>
@endsection
