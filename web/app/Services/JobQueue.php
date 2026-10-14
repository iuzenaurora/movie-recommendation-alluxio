<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JobQueue
{
    public static function root(): string
    {
        $root = config('recommender.runtime_dir', '/runtime').'/jobs';
        if (! is_dir($root)) {
            mkdir($root, 0770, true);
        }

        return $root;
    }

    public static function all(): array
    {
        $jobs = [];
        foreach (glob(self::root().'/*.json') ?: [] as $path) {
            $job = json_decode(file_get_contents($path), true);
            if (is_array($job) && isset($job['id'], $job['kind'], $job['status'])) {
                $jobs[] = $job;
            }
        }
        usort($jobs, fn ($a, $b) => strcmp($b['createdAt'], $a['createdAt']));

        return array_slice($jobs, 0, 50);
    }

    public static function enqueue(string $kind, int $userId, ?string $model): array
    {
        $lock = fopen(self::root().'/enqueue.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            foreach (self::all() as $job) {
                if (in_array($job['status'], ['pending', 'running'])) {
                    throw ValidationException::withMessages(['job' => 'Đang có job chờ hoặc chạy. Hãy chờ job đó kết thúc.']);
                }
            }
            $job = ['id' => (string) Str::uuid(), 'kind' => $kind, 'status' => 'pending', 'createdAt' => now()->toIso8601String(), 'requestedBy' => $userId, 'model' => $model];
            $path = self::root().'/'.$job['id'].'.json';
            file_put_contents($path.'.tmp', json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            rename($path.'.tmp', $path);

            return $job;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
