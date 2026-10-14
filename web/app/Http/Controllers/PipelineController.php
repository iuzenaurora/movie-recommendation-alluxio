<?php

namespace App\Http\Controllers;

use App\Services\ApiService;
use App\Services\JobQueue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PipelineController extends Controller
{
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    private function runtime(): string
    {
        return config('recommender.runtime_dir', '/runtime');
    }

    public function index(): mixed
    {
        $this->authorizeAdmin();
        $jobs = JobQueue::all();
        $worker = is_file(JobQueue::root().'/heartbeat') && time() - filemtime(JobQueue::root().'/heartbeat') < 90;
        $path = $this->runtime().'/automation.json';
        $automation = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        $pipeline = $this->pipelineState();
        $models = [];
        foreach (glob(dirname(config('recommender.model_dir')).'/als-*/manifest.json') ?: [] as $manifest) {
            $models[] = basename(dirname($manifest));
        }

        return view('admin.jobs', compact('jobs', 'worker', 'automation', 'pipeline', 'models'));
    }

    private function pipelineState(): array
    {
        $load = function (string $path): array {
            return is_file($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        };
        $heartbeat = JobQueue::root().'/heartbeat';
        clearstatcache(true, $heartbeat);
        $manifest = $load(config('recommender.model_dir').'/manifest.json');

        return [
            'worker' => is_file($heartbeat) && time() - filemtime($heartbeat) < 90,
            'heartbeatAt' => is_file($heartbeat) ? filemtime($heartbeat) : null,
            'automation' => $load($this->runtime().'/automation.json'),
            'scheduler' => $load($this->runtime().'/automation-state.json'),
            'model' => array_intersect_key($manifest, array_flip(['modelVersion', 'datasetVersion', 'sampleRows', 'users'])),
            'jobs' => JobQueue::all(),
        ];
    }

    public function status(): mixed
    {
        $this->authorizeAdmin();

        return response()->json($this->pipelineState())->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): mixed
    {
        $this->authorizeAdmin();
        $data = $request->validate(['kind' => ['required', Rule::in(['benchmark', 'snapshot', 'retrain', 'activate', 'rollback'])], 'model' => ['nullable', 'regex:/^als-[a-zA-Z0-9_-]+$/', 'required_if:kind,activate']]);
        $job = JobQueue::enqueue($data['kind'], $request->user()->id, $data['model'] ?? null);

        return redirect()->route('admin.jobs')->with('success', 'Đã xếp job '.$job['id'].' vào hàng đợi.');
    }

    public function show(Request $request, string $id): mixed
    {
        $this->authorizeAdmin();
        $path = JobQueue::root().'/'.$id.'.json';
        abort_unless(is_file($path), 404);
        $job = json_decode(file_get_contents($path), true);
        $log = JobQueue::root().'/'.$id.'.log';
        $job['log'] = '';
        if (is_file($log)) {
            $stream = fopen($log, 'rb');
            fseek($stream, max(0, filesize($log) - 50000));
            $job['log'] = mb_convert_encoding(stream_get_contents($stream), 'UTF-8', 'UTF-8');
            fclose($stream);
        }
        if (! $request->expectsJson()) {
            return view('admin.job', compact('job'));
        }

        return response()->json($job);
    }

    public function automation(Request $request): mixed
    {
        $this->authorizeAdmin();
        $data = $request->validate(['benchmarkHours' => ['required', 'integer', 'min:0', 'max:168'], 'retrainRatings' => ['required', 'integer', 'min:0', 'max:10000'], 'autoActivate' => ['sometimes', 'boolean']]);
        $data['autoActivate'] = $request->boolean('autoActivate');
        $path = $this->runtime().'/automation.json';
        file_put_contents($path.'.tmp', json_encode($data, JSON_PRETTY_PRINT));
        rename($path.'.tmp', $path);

        return back()->with('success', 'Đã lưu lịch tự động. Giá trị 0 nghĩa là tắt.');
    }

    public function trailer(Request $request): mixed
    {
        $this->authorizeAdmin();
        $data = $request->validate(['movieId' => ['required', 'integer', 'min:1'], 'youtubeId' => ['required', 'regex:/^[A-Za-z0-9_-]{11}$/']]);
        abort_unless(ApiService::getMovie((int) $data['movieId']), 422, 'Không tìm thấy phim trong catalog.');
        $path = $this->runtime().'/trailers.json';
        $lock = fopen($this->runtime().'/trailers.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $trailers = is_file($path) ? json_decode(file_get_contents($path), true) : [];
            $trailers[(string) $data['movieId']] = $data['youtubeId'];
            file_put_contents($path.'.tmp', json_encode($trailers, JSON_PRETTY_PRINT));
            rename($path.'.tmp', $path);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return back()->with('success', 'Đã lưu trailer đã xác minh cho phim.');
    }
}
