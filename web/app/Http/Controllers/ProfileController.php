<?php

namespace App\Http\Controllers;

use App\Services\ApiService;

class ProfileController extends Controller
{
    public function index(): mixed
    {
        $uid = ApiService::sessionUser();
        $data = ApiService::getHistory($uid);
        abort_unless($data !== null, 503, 'Dịch vụ lịch sử đang bảo trì.');
        $ratings = [];
        $genres = [];
        foreach ($data['items'] as $item) {
            $movie = MovieController::normalizeMovie($item);
            $movie['userRating'] = (float) $item['rating'];
            $ratings[] = $movie;
            foreach ($movie['genres'] as $genre) {
                $genres[$genre] = ($genres[$genre] ?? 0) + 1;
            }
        }
        arsort($genres);

        return view('profile.index', [
            'currentUser' => session('current_user'), 'ratings' => $ratings, 'totalRated' => count($ratings),
            'avgScore' => count($ratings) ? round(array_sum(array_column($ratings, 'userRating')) / count($ratings), 2) : 0,
            'topGenre' => array_key_first($genres) ?? 'Chưa xác định', 'historyVersion' => $data['historyVersion'],
        ]);
    }

    public function deleteRating(int $movieId): mixed
    {
        $result = ApiService::deleteRating(ApiService::sessionUser(), $movieId);
        abort_unless($result && ! empty($result['ok']), 503, 'Chưa xóa được đánh giá. Vui lòng thử lại.');

        return back()->with('success', 'Đã xóa đánh giá và cập nhật lịch sử.');
    }
}
