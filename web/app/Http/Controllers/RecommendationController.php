<?php

namespace App\Http\Controllers;

use App\Services\ApiService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    private function data(string $mode): array
    {
        $uid = ApiService::sessionUser();
        $history = ApiService::getHistory($uid);
        abort_unless($history !== null, 503, 'Dịch vụ lịch sử đang bảo trì.');
        $weights = [];
        foreach ($history['items'] as $item) {
            $genres = is_array($item['genres'] ?? '') ? $item['genres'] : explode('|', $item['genres'] ?? '');
            if ((float) $item['rating'] <= 3) {
                continue;
            }
            foreach ($genres as $genre) {
                if ($genre !== '' && $genre !== '(no genres listed)') {
                    $weights[$genre] = ($weights[$genre] ?? 0) + ((float) $item['rating'] - 3) / sqrt(max(1, count($genres)));
                }
            }
        }
        arsort($weights);
        $favorite = array_key_first($weights);
        $method = match ($mode) {
            'hybrid' => 'hybrid',
            'trending' => 'popularity',
            default => 'als',
        };
        $result = ApiService::getRecommendations($uid, 10, $mode === 'genre' ? ($favorite ?? '') : '', $method);
        abort_unless($result !== null && isset($result['items']), 503, 'Dịch vụ gợi ý đang bảo trì.');
        $recommendations = [];
        foreach ($result['items'] as $item) {
            $movie = MovieController::normalizeMovie($item);
            $movie['predictedScore'] = number_format((float) $item['score'], 3);
            $recommendations[] = $movie;
        }

        return ['currentUser' => session('current_user'), 'userRatingsCount' => count($history['items']), 'currentMode' => $mode, 'apiOnline' => true,
            'recData' => ['recommendations' => $recommendations, 'latencyMs' => round($result['milliseconds'], 1),
                'modelVersion' => $result['modelVersion'], 'historyVersion' => $result['historyVersion'],
                'favoriteGenre' => $favorite ?? 'Chưa xác định', 'isFallback' => $result['fallback'], 'method' => $result['method'],
                'supportedRatings' => $result['supportedRatings'] ?? count($history['items'])]];
    }

    public function index(Request $request): mixed
    {
        $mode = $request->string('mode', 'hybrid')->toString();
        abort_unless(in_array($mode, ['hybrid', 'als_fold_in', 'genre', 'trending']), 422);

        return view('recommendations.index', $this->data($mode));
    }

    public function recompute(Request $request): mixed
    {
        $mode = $request->string('mode', 'hybrid')->toString();
        abort_unless(in_array($mode, ['hybrid', 'als_fold_in', 'genre', 'trending']), 422);

        return response()->json(['status' => 'success', 'data' => $this->data($mode)['recData']]);
    }
}
