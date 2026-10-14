<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectFlowTest extends TestCase
{
    use RefreshDatabase;

    private bool $apiOffline = false;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if ($this->apiOffline) {
                return Http::response([], 503);
            }
            $url = $request->url();
            if ($request->method() === 'POST' && str_ends_with($url, '/users')) {
                return Http::response(['appUserId' => $request['appUserId']]);
            }
            if (str_ends_with($url, '/ratings') || str_contains($url, '/ratings/')) {
                return Http::response(['ok' => true, 'historyVersion' => 'abcdef1234567890']);
            }
            if (str_ends_with($url, '/history')) {
                return Http::response(['items' => [], 'historyVersion' => 'empty']);
            }
            if (str_contains($url, '/recommendations')) {
                return Http::response(['items' => [], 'historyVersion' => 'empty', 'modelVersion' => 'test-model', 'milliseconds' => 1, 'fallback' => true, 'method' => 'popularity']);
            }
            if (str_contains($url, '/movies/')) {
                return Http::response(['movieId' => 10, 'title' => 'Test Movie (1995)', 'genres' => 'Drama']);
            }

            return Http::response([], 503);
        });
    }

    public function test_guest_cannot_write_rating_or_read_private_history(): void
    {
        $this->postJson('/movies/10/rate', ['rating' => 4])->assertUnauthorized();
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/quick-login/101')->assertNotFound();
    }

    public function test_detail_renders_without_optional_fields_and_rating_redirect_works(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/movies/10')->assertOk()->assertSee('Test Movie (1995)');
        $this->from('/movies/10')->post('/movies/10/rate', ['rating' => 4.5])->assertRedirect('/movies/10');
        $this->get('/movies/10')->assertOk();
    }

    public function test_rating_uses_account_identity_and_returns_history_hash(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/movies/10/rate', ['rating' => 4.5])->assertOk()
            ->assertJsonPath('savedViaApi', true)->assertJsonPath('historyVersion', 'abcdef1234567890');
        $user->refresh();
        $this->assertNotNull($user->app_user_id);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_contains($r->url(), $user->app_user_id));
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/movies/10/rate', ['rating' => 2])->assertOk();
        $this->assertNotSame($user->app_user_id, $other->fresh()->app_user_id);
    }

    public function test_delete_calls_api_for_authenticated_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->delete('/profile/ratings/10')->assertRedirect();
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), $user->fresh()->app_user_id));
    }

    public function test_private_pages_render_for_authenticated_account(): void
    {
        $this->actingAs(User::factory()->create())->get('/profile')->assertOk();
        $this->get('/recommendations')->assertOk()->assertSee('test-model');
    }

    public function test_default_hybrid_and_raw_als_are_distinct_modes(): void
    {
        $this->actingAs(User::factory()->create())->get('/recommendations')->assertOk()
            ->assertViewHas('currentMode', 'hybrid')->assertSee('ALS + sở thích thể loại');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/recommendations') && $r['method'] === 'hybrid');
        $this->get('/recommendations?mode=als_fold_in')->assertOk()
            ->assertViewHas('currentMode', 'als_fold_in')->assertSee('ALS thuần');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/recommendations') && $r['method'] === 'als');
    }

    public function test_api_failure_never_reports_rating_saved(): void
    {
        $this->apiOffline = true;
        $this->actingAs(User::factory()->create())->postJson('/movies/10/rate', ['rating' => 4])->assertStatus(503);
    }

    public function test_half_star_validation(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/movies/10/rate', ['rating' => 1.2])->assertUnprocessable();
    }

    public function test_admin_dashboard_reads_fixture_and_rejects_regular_user(): void
    {
        config(['recommender.model_dir' => base_path('tests/Fixtures/model'), 'recommender.experiment_dir' => base_path('tests/Fixtures'), 'recommender.benchmark_report' => 'benchmark.json']);
        $this->actingAs(User::factory()->create())->get('/admin/benchmark')->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/benchmark')->assertOk()->assertSee('synthetic-test');
        $this->get('/admin/eda')->assertOk()->assertSee('123');
        $this->get('/admin/versions')->assertOk()->assertSee('test-model');
    }
}
