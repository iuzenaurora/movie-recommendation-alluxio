<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\MovieDataService;

class MovieController extends Controller
{
    public function index(Request $request)
    {
        $allMovies = MovieDataService::getMovies();
        $query = $request->input('q');
        $genre = $request->input('genre');

        $filtered = collect($allMovies);

        if (!empty($query)) {
            $filtered = $filtered->filter(function ($m) use ($query) {
                return stripos($m['title'], $query) !== false || stripos($m['overview'], $query) !== false;
            });
        }

        if (!empty($genre) && $genre !== 'all') {
            $filtered = $filtered->filter(function ($m) use ($genre) {
                return in_array($genre, $m['genres']);
            });
        }

        // Pagination: 12 movies per page
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 12;
        $currentPageItems = $filtered->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginatedMovies = new LengthAwarePaginator(
            $currentPageItems,
            $filtered->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        $allGenres = ['Action', 'Adventure', 'Animation', 'Children', 'Comedy', 'Crime', 'Drama', 'Fantasy', 'Horror', 'Mystery', 'Romance', 'Sci-Fi', 'Thriller', 'War'];

        $user = session('current_user', MovieDataService::getDemoUsers()[0]);
        $userRatings = MovieDataService::getUserRatings($user['id']);
        $userRatingMap = collect($userRatings)->pluck('rating', 'movieId')->toArray();

        return view('movies.index', [
            'movies' => $paginatedMovies,
            'allGenres' => $allGenres,
            'selectedGenre' => $genre,
            'query' => $query,
            'currentUser' => $user,
            'userRatingMap' => $userRatingMap,
            'totalCount' => $filtered->count(),
        ]);
    }

    public function show($id)
    {
        $allMovies = MovieDataService::getMovies();
        $movie = collect($allMovies)->firstWhere('movieId', intval($id));

        if (!$movie) {
            return redirect()->route('movies.index')->with('error', 'Không tìm thấy bộ phim yêu cầu.');
        }

        $user = session('current_user', MovieDataService::getDemoUsers()[0]);
        $userRatings = MovieDataService::getUserRatings($user['id']);
        $personalRating = collect($userRatings)->firstWhere('movieId', intval($id));

        return view('movies.show', [
            'movie' => $movie,
            'currentUser' => $user,
            'personalRating' => $personalRating,
        ]);
    }

    public function rate(Request $request, $id)
    {
        $user = session('current_user', MovieDataService::getDemoUsers()[0]);
        $rating = $request->input('rating');

        if ($rating < 0.5 || $rating > 5.0) {
            return response()->json(['status' => 'error', 'message' => 'Điểm đánh giá phải từ 0.5 đến 5.0'], 422);
        }

        MovieDataService::saveUserRating($user['id'], intval($id), floatval($rating));

        $historyVersion = session("history_version_{$user['id']}", 1);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Đã lưu đánh giá ' . $rating . '⭐ vào MongoDB thành công!',
                'historyVersion' => $historyVersion,
                'rating' => $rating,
            ]);
        }

        return redirect()->back()->with('success', "Đã chấm {$rating}⭐ cho bộ phim. historyVersion tăng lên v{$historyVersion}!");
    }
}
