<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MovieDataService;

class ProfileController extends Controller
{
    public function index()
    {
        $user = session('current_user', MovieDataService::getDemoUsers()[0]);
        $userRatings = MovieDataService::getUserRatings($user['id']);
        $movies = MovieDataService::getMovies();

        $detailedRatings = [];
        $genreCounts = [];

        foreach ($userRatings as $r) {
            $movie = collect($movies)->firstWhere('movieId', $r['movieId']);
            if ($movie) {
                $detailedRatings[] = array_merge($movie, [
                    'userRating' => $r['rating'],
                    'ratedAt' => $r['ratedAt'] ?? date('Y-m-d H:i:s'),
                ]);

                foreach ($movie['genres'] as $g) {
                    $genreCounts[$g] = ($genreCounts[$g] ?? 0) + 1;
                }
            }
        }

        arsort($genreCounts);
        $topGenre = !empty($genreCounts) ? array_key_first($genreCounts) : 'Chưa xác định';
        $avgScore = count($userRatings) > 0 ? round(array_sum(array_column($userRatings, 'rating')) / count($userRatings), 2) : 0;
        $historyVersion = session("history_version_{$user['id']}", count($userRatings));

        return view('profile.index', [
            'currentUser' => $user,
            'ratings' => $detailedRatings,
            'totalRated' => count($userRatings),
            'avgScore' => $avgScore,
            'topGenre' => $topGenre,
            'historyVersion' => $historyVersion,
            'demoUsers' => MovieDataService::getDemoUsers(),
        ]);
    }

    public function deleteRating($movieId)
    {
        $user = session('current_user', MovieDataService::getDemoUsers()[0]);
        $ratings = MovieDataService::getUserRatings($user['id']);

        $ratings = array_values(array_filter($ratings, function ($r) use ($movieId) {
            return $r['movieId'] != $movieId;
        }));

        session(["user_ratings_{$user['id']}" => $ratings]);

        $version = session("history_version_{$user['id']}", 1);
        session(["history_version_{$user['id']}" => $version + 1]);

        return redirect()->back()->with('success', 'Đã hủy đánh giá bộ phim.');
    }
}
