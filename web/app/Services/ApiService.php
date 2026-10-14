<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ApiService — wrapper gọi FastAPI backend tại RECOMMENDER_URL.
 */
class ApiService
{
    public static function sessionUser(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }
        if (! $user->app_user_id) {
            User::whereKey($user->id)->whereNull('app_user_id')->update(['app_user_id' => (string) Str::uuid()]);
            $user->refresh();
        }
        $result = static::createUser(null, $user->app_user_id);
        abort_unless($result && isset($result['appUserId']), 503, 'Dịch vụ gợi ý đang bảo trì. Tài khoản và lịch sử của bạn vẫn được giữ.');
        session(['appUserId' => $user->app_user_id]);

        return $user->app_user_id;
    }

    public static function getMovie(int $movieId): ?array
    {
        try {
            $response = static::http()->get("/movies/{$movieId}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning('[ApiService] getMovie: '.$e->getMessage());

            return null;
        }
    }

    public static function deleteRating(string $appUserId, int $movieId): ?array
    {
        try {
            $response = static::http()->delete("/users/{$appUserId}/ratings/{$movieId}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning('[ApiService] deleteRating: '.$e->getMessage());

            return null;
        }
    }

    private static function http()
    {
        return Http::baseUrl(env('RECOMMENDER_URL', 'http://api:8000'))
            ->withHeaders(['X-Service-Token' => is_file(env('SERVICE_TOKEN_FILE', '/runtime/service-token')) ? trim(file_get_contents(env('SERVICE_TOKEN_FILE', '/runtime/service-token'))) : ''])
            ->timeout(30)
            ->acceptJson();
    }

    // ─── Profiles ─────────────────────────────────────────────────────────────

    /**
     * Lấy danh sách ID các profile mẫu từ model.
     *
     * @return array|null ['profiles' => [1, 2, ...]]
     */
    public static function getProfiles(): ?array
    {
        try {
            $res = static::http()->get('/profiles');
            if ($res->successful()) {
                return $res->json();
            }
        } catch (\Throwable $e) {
            Log::warning('[ApiService] getProfiles: '.$e->getMessage());
        }

        return null;
    }

    // ─── Users ────────────────────────────────────────────────────────────────

    /**
     * Tạo phiên user mới trên API, trả về ['appUserId' => '<uuid>'].
     *
     * @param  int|null  $sourceProfileId  Seed lịch sử từ demo profile ML-32M
     */
    public static function createUser(?int $sourceProfileId = null, ?string $appUserId = null): ?array
    {
        try {
            $payload = ['sourceProfileId' => $sourceProfileId, 'appUserId' => $appUserId];
            $res = static::http()->post('/users', $payload);
            if ($res->successful()) {
                return $res->json();
            }
            Log::warning('[ApiService] createUser failed', ['status' => $res->status(), 'body' => $res->body()]);
        } catch (ConnectionException $e) {
            Log::error('[ApiService] createUser connection error: '.$e->getMessage());
        } catch (\Throwable $e) {
            Log::error('[ApiService] createUser error: '.$e->getMessage());
        }

        return null;
    }

    // ─── Movies ───────────────────────────────────────────────────────────────

    /**
     * Lấy danh sách phim với filter & phân trang.
     *
     * @return array|null ['items' => [...], 'total' => int, 'page' => int]
     */
    public static function getMovies(string $q = '', string $genre = '', int $page = 1): ?array
    {
        try {
            $params = array_filter([
                'q' => $q,
                'genre' => $genre,
                'page' => $page,
            ], fn ($v) => $v !== '' && $v !== null);

            $res = static::http()->get('/movies', $params);
            if ($res->successful()) {
                return $res->json();
            }
            Log::warning('[ApiService] getMovies failed', ['status' => $res->status()]);
        } catch (ConnectionException $e) {
            Log::error('[ApiService] getMovies connection error: '.$e->getMessage());
        } catch (\Throwable $e) {
            Log::error('[ApiService] getMovies error: '.$e->getMessage());
        }

        return null;
    }

    // ─── Ratings ──────────────────────────────────────────────────────────────

    /**
     * Lưu (hoặc cập nhật) rating cho một bộ phim.
     *
     * @return array|null ['ok' => true, 'historyVersion' => str] hoặc null nếu lỗi
     */
    public static function rateMovie(string $appUserId, int $movieId, float $rating): ?array
    {
        try {
            $res = static::http()->put("/users/{$appUserId}/ratings", [
                'movieId' => (int) $movieId,
                'rating' => (float) $rating,
            ]);
            if ($res->successful()) {
                return $res->json();
            }
            Log::warning('[ApiService] rateMovie failed', ['status' => $res->status(), 'body' => $res->body()]);
        } catch (ConnectionException $e) {
            Log::error('[ApiService] rateMovie connection error: '.$e->getMessage());
        } catch (\Throwable $e) {
            Log::error('[ApiService] rateMovie error: '.$e->getMessage());
        }

        return null;
    }

    // ─── Recommendations ──────────────────────────────────────────────────────

    /**
     * Lấy danh sách gợi ý fold-in ALS cho user.
     *
     * @return array|null ['items' => [...], 'fallback' => bool, 'method' => str, 'modelVersion' => str, 'historyVersion' => str, 'milliseconds' => float]
     */
    public static function getRecommendations(string $appUserId, int $k = 10, string $genre = '', string $method = 'als'): ?array
    {
        try {
            $params = array_filter([
                'k' => $k,
                'method' => $method,
                'genre' => $genre,
            ], fn ($v) => $v !== '' && $v !== null);

            $res = static::http()->get("/users/{$appUserId}/recommendations", $params);
            if ($res->successful()) {
                return $res->json();
            }
            Log::warning('[ApiService] getRecommendations failed', ['status' => $res->status()]);
        } catch (ConnectionException $e) {
            Log::error('[ApiService] getRecommendations connection error: '.$e->getMessage());
        } catch (\Throwable $e) {
            Log::error('[ApiService] getRecommendations error: '.$e->getMessage());
        }

        return null;
    }

    // ─── History ──────────────────────────────────────────────────────────────

    /**
     * Lấy lịch sử ratings của user.
     *
     * @return array|null ['items' => [...], 'historyVersion' => str]
     */
    public static function getHistory(string $appUserId): ?array
    {
        try {
            $res = static::http()->get("/users/{$appUserId}/history");
            if ($res->successful()) {
                return $res->json();
            }
            Log::warning('[ApiService] getHistory failed', ['status' => $res->status()]);
        } catch (ConnectionException $e) {
            Log::error('[ApiService] getHistory connection error: '.$e->getMessage());
        } catch (\Throwable $e) {
            Log::error('[ApiService] getHistory error: '.$e->getMessage());
        }

        return null;
    }

    // ─── Dashboard / Model Info ────────────────────────────────────────────────

    /**
     * Lấy metadata mô hình và kết quả experiment từ API.
     *
     * @return array|null ['model' => ..., 'experiments' => ..., 'notice' => ...]
     */
    public static function getDashboard(): ?array
    {
        try {
            $res = static::http()->get('/dashboard');
            if ($res->successful()) {
                return $res->json();
            }
            Log::warning('[ApiService] getDashboard failed', ['status' => $res->status()]);
        } catch (ConnectionException $e) {
            Log::error('[ApiService] getDashboard connection error: '.$e->getMessage());
        } catch (\Throwable $e) {
            Log::error('[ApiService] getDashboard error: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Health check — kiểm tra API còn sống không.
     */
    public static function isAlive(): bool
    {
        try {
            $res = static::http()->timeout(5)->get('/health');

            return $res->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
