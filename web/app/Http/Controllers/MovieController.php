<?php

namespace App\Http\Controllers;

use App\Services\ApiService;
use App\Services\MovieDataService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class MovieController extends Controller
{
    public static function normalizeMovie(array $movie): array
    {
        $id = (int) ($movie['movieId'] ?? 0);
        $title = $movie['title'] ?? 'Phim #'.$id;
        preg_match('/\\((\\d{4})\\)/', $title, $match);
        $year = isset($match[1]) ? (int) $match[1] : 0;
        $genres = is_array($movie['genres'] ?? '') ? $movie['genres'] : array_values(array_filter(explode('|', $movie['genres'] ?? '')));
        $average = (float) ($movie['avgRating'] ?? 0);
        $fallback = MovieDataService::generateDynamicSvgPoster($title, $year, $genres[0] ?? 'Cinema', $average);

        return ['movieId' => $id, 'title' => $title, 'year' => $year, 'genres' => $genres,
            'overview' => $movie['overview'] ?? 'Khám phá phim và chia sẻ đánh giá để xây dựng gu điện ảnh của bạn.',
            'poster' => $movie['poster'] ?? $fallback, 'fallbackPoster' => $fallback,
            'avgRating' => $average, 'rating' => $average, 'ratingCount' => (int) ($movie['ratingCount'] ?? 0),
            'hasFactor' => (bool) ($movie['hasFactor'] ?? false), 'score' => $movie['score'] ?? null];
    }

    public function index(Request $request): mixed
    {
        $query = $request->string('q')->toString();
        $genre = $request->string('genre')->toString();
        $page = max(1, $request->integer('page', 1));
        $data = ApiService::getMovies($query, $genre === 'all' ? '' : $genre, $page);
        abort_unless($data !== null, 503, 'Catalog đang bảo trì. Vui lòng thử lại sau.');
        $userRatingMap = [];
        if (auth()->check()) {
            $uid = ApiService::sessionUser();
            $history = ApiService::getHistory($uid);
            $userRatingMap = collect($history['items'] ?? [])->pluck('rating', 'movieId')->all();
        }
        $items = collect($data['items'])->map(fn ($item) => self::normalizeMovie($item));
        $movies = new LengthAwarePaginator($items, $data['total'], 24, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('movies.index', ['movies' => $movies, 'allGenres' => ['Action', 'Adventure', 'Animation', 'Children', 'Comedy', 'Crime', 'Documentary', 'Drama', 'Fantasy', 'Film-Noir', 'Horror', 'Musical', 'Mystery', 'Romance', 'Sci-Fi', 'Thriller', 'War', 'Western'],
            'selectedGenre' => $genre, 'query' => $query, 'currentUser' => session('current_user', ['id' => 0, 'name' => 'Khách']),
            'userRatingMap' => $userRatingMap, 'totalCount' => $data['total'], 'apiOnline' => true]);
    }

    public function show(int $id): mixed
    {
        $data = ApiService::getMovie($id);
        abort_unless($data !== null, 404, 'Không tìm thấy phim.');
        $movie = self::normalizeMovie($data);
        $personalRating = null;
        if (auth()->check()) {
            $history = ApiService::getHistory(ApiService::sessionUser());
            $personalRating = collect($history['items'] ?? [])->firstWhere('movieId', $id);
        }
        $path = config('recommender.runtime_dir', '/runtime').'/trailers.json';
        $trailers = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        $trailerId = $trailers[(string) $id] ?? config('trailers.youtube.'.$id);
        if (! is_string($trailerId) || ! preg_match('/^[A-Za-z0-9_-]{11}$/', $trailerId)) {
            $trailerId = null;
        }

        return view('movies.show', ['movie' => $movie, 'currentUser' => session('current_user', ['name' => 'Khách']),
            'personalRating' => $personalRating, 'trailerId' => $trailerId, 'studioUrl' => config('trailers.studio.'.$id)]);
    }

    public function rate(Request $request, int $id): mixed
    {
        $data = $request->validate(['rating' => ['required', 'numeric', 'between:0.5,5']]);
        $rating = (float) $data['rating'];
        if ($rating * 2 !== floor($rating * 2)) {
            throw ValidationException::withMessages(['rating' => 'Chọn điểm theo bước 0,5 sao.']);
        }
        $result = ApiService::rateMovie(ApiService::sessionUser(), $id, $rating);
        abort_unless($result && ! empty($result['ok']), 503, 'Không lưu được đánh giá. Vui lòng thử lại.');
        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'savedViaApi' => true, 'rating' => $rating, 'historyVersion' => $result['historyVersion']]);
        }

        return back()->with('success', 'Đã lưu '.$rating.' sao. Gợi ý cá nhân đã sẵn sàng cập nhật.');
    }
}
