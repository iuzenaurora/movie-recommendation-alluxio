<?php

namespace App\Http\Controllers;

use App\Services\ApiService;

class AdminController extends Controller
{
    private function checkAdminAccess(): mixed
    {
        abort_unless(auth()->check() && auth()->user()->role === 'admin', 403);

        return null;
    }

    private function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        $value = json_decode(file_get_contents($path), true);

        return is_array($value) ? $value : null;
    }

    private function model(): ?array
    {
        return $this->readJson(config('recommender.model_dir').'/manifest.json');
    }

    public function eda(): mixed
    {
        if ($guard = $this->checkAdminAccess()) {
            return $guard;
        }
        $manifest = $this->model();

        return view('admin.eda', compact('manifest'));
    }

    public function benchmark(): mixed
    {
        if ($guard = $this->checkAdminAccess()) {
            return $guard;
        }
        $root = config('recommender.experiment_dir');
        $relative = config('recommender.benchmark_report');
        $active = $this->readJson(config('recommender.runtime_dir', '/runtime').'/active-benchmark.json');
        if (isset($active['path']) && preg_match('#^als-benchmark/[a-zA-Z0-9_-]+/metrics-summary-v1/metrics-summary\.json$#', $active['path'])) {
            $relative = $active['path'];
        }
        $report = $this->readJson($root.'/'.$relative);
        $manifest = $this->model();

        return view('admin.benchmark', compact('report', 'manifest', 'relative'));
    }

    public function versions(): mixed
    {
        if ($guard = $this->checkAdminAccess()) {
            return $guard;
        }
        $manifest = $this->model();
        $health = ApiService::getDashboard();
        $models = [];
        $root = dirname(config('recommender.model_dir'));
        foreach (glob($root.'/*/manifest.json') ?: [] as $path) {
            $meta = $this->readJson($path);
            if ($meta) {
                $models[] = ['folder' => basename(dirname($path)), 'meta' => $meta];
            }
        }

        return view('admin.versions', compact('manifest', 'health', 'models'));
    }
}
