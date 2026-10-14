<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $runtime;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->runtime = sys_get_temp_dir().'/movielab-test-'.Str::uuid();
        mkdir($this->runtime);
        config(['recommender.runtime_dir' => $this->runtime]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->runtime);
        parent::tearDown();
    }

    public function test_registration_hashes_password_and_ignores_admin_role_input(): void
    {
        $this->post('/register', ['name' => 'Cinema Fan', 'email' => 'fan@example.com', 'password' => 'strong-password', 'password_confirmation' => 'strong-password', 'role' => 'admin'])
            ->assertRedirect('/recommendations');
        $user = User::where('email', 'fan@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('strong-password', $user->password));
        $this->assertSame('user', $user->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_wrong_password_and_accepts_correct_password(): void
    {
        $user = User::factory()->create(['email' => 'fan@example.com']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/movies');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_duplicate_email_and_unconfirmed_password_are_rejected(): void
    {
        User::factory()->create(['email' => 'fan@example.com']);
        $this->post('/register', ['name' => 'Fan', 'email' => 'fan@example.com', 'password' => 'password1', 'password_confirmation' => 'password2'])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_user_cannot_enqueue_jobs_or_set_automation(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/jobs', ['kind' => 'benchmark'])->assertForbidden();
        $this->post('/admin/automation', ['benchmarkHours' => 24, 'retrainRatings' => 20])->assertForbidden();
        $this->getJson('/admin/pipeline/status')->assertForbidden();
    }

    public function test_admin_enqueues_fixed_job_and_duplicate_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/jobs', ['kind' => 'snapshot'])->assertRedirect('/admin/jobs');
        $jobs = glob($this->runtime.'/jobs/*.json');
        $this->assertCount(1, $jobs);
        $row = json_decode(file_get_contents($jobs[0]), true);
        $this->assertSame('pending', $row['status']);
        $this->post('/admin/jobs', ['kind' => 'benchmark'])->assertSessionHasErrors('job');
        $this->getJson('/admin/jobs/'.$row['id'])->assertOk()->assertJsonPath('kind', 'snapshot');
        $this->get('/admin/jobs/'.$row['id'])->assertOk()->assertSee($row['id']);
        $this->get('/admin/jobs')->assertOk();
    }

    public function test_unknown_job_and_model_path_traversal_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/jobs', ['kind' => 'shell'])->assertSessionHasErrors('kind');
        $this->post('/admin/jobs', ['kind' => 'activate', 'model' => '../current'])->assertSessionHasErrors('model');
    }

    public function test_admin_saves_schedule_and_rejects_unsafe_trailer_id(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/automation', ['benchmarkHours' => 24, 'retrainRatings' => 20])->assertRedirect();
        $this->assertSame(24, json_decode(file_get_contents($this->runtime.'/automation.json'), true)['benchmarkHours']);
        $this->post('/admin/trailers', ['movieId' => 1, 'youtubeId' => '<script>'])->assertSessionHasErrors('youtubeId');
    }

    public function test_admin_status_exposes_real_worker_progress_and_schedule(): void
    {
        mkdir($this->runtime.'/jobs');
        touch($this->runtime.'/jobs/heartbeat');
        file_put_contents($this->runtime.'/automation-state.json', json_encode(['currentRevision' => 30, 'trainedRevision' => 10]));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/admin/automation', ['benchmarkHours' => 12, 'retrainRatings' => 20, 'autoActivate' => 1])->assertRedirect();
        $this->getJson('/admin/pipeline/status')->assertOk()
            ->assertJsonPath('worker', true)
            ->assertJsonPath('automation.autoActivate', true)
            ->assertJsonPath('scheduler.currentRevision', 30)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/admin/jobs')->assertOk()->assertSee('Live data pipeline')->assertSee('MovieLab.pyw');
    }
}
