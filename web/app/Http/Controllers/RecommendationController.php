<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MovieDataService;

class RecommendationController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->input('mode', 'als_fold_in');
        $user = session('current_user', MovieDataService::getDemoUsers()[0]);
        $recommendationData = MovieDataService::computeRecommendations($user['id'], 10, $mode);
        $userRatings = MovieDataService::getUserRatings($user['id']);

        return view('recommendations.index', [
            'currentUser' => $user,
            'recData' => $recommendationData,
            'userRatingsCount' => count($userRatings),
            'currentMode' => $mode,
        ]);
    }

    public function recompute(Request $request)
    {
        $mode = $request->input('mode', 'als_fold_in');
        $user = session('current_user', MovieDataService::getDemoUsers()[0]);
        $recData = MovieDataService::computeRecommendations($user['id'], 10, $mode);

        return response()->json([
            'status' => 'success',
            'data' => $recData,
            'message' => 'Đã tính toán lại gợi ý thành công!',
        ]);
    }
}
