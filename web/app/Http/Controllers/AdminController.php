<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MovieDataService;

class AdminController extends Controller
{
    private function checkAdminAccess()
    {
        $user = session('current_user', MovieDataService::getDemoUsers()[0]);
        if (($user['role'] ?? 'user') !== 'admin') {
            return redirect()->route('movies.index')->with('error', '⚠️ Bạn đang đăng nhập bằng tài khoản Người dùng (' . ($user['name'] ?? 'User') . '). Trang Quản trị Big Data chỉ dành cho Admin. Vui lòng chuyển sang tài khoản Admin ở menu Persona góc phải trên để truy cập!');
        }
        return null;
    }

    /**
     * Dashboard 1: Khảo sát & Phân tích Dữ liệu Lớn (Big Data EDA)
     */
    public function eda()
    {
        if ($guard = $this->checkAdminAccess()) return $guard;
        $edaStats = [
            'rawRatingsCount' => '32,000,204',
            'cleanedRatingsCount' => '31,892,150',
            'removedRecords' => '108,054 (0.34%)',
            'totalUsers' => '200,948',
            'totalMovies' => '62,034',
            'matrixSparsity' => '99.74%',
            'parquetSize' => '784.6 MiB',
            'ratingScale' => '0.5 - 5.0 (Bước nhảy 0.5)',
            'ratingDist' => [
                '0.5' => 1.2, '1.0' => 3.1, '1.5' => 2.4, '2.0' => 6.8, '2.5' => 8.5,
                '3.0' => 19.8, '3.5' => 18.2, '4.0' => 23.6, '4.5' => 9.7, '5.0' => 6.7
            ],
            'topGenres' => [
                'Drama' => 14200000,
                'Comedy' => 11500000,
                'Action' => 8900000,
                'Thriller' => 7800000,
                'Sci-Fi' => 5400000,
                'Romance' => 5100000,
                'Adventure' => 4800000,
                'Crime' => 4200000,
                'Animation' => 2900000,
                'Horror' => 2600000,
            ],
            'cleaningRules' => [
                ['rule' => 'Loại bỏ userId hoặc movieId null/không hợp lệ', 'status' => 'Đạt chuẩn', 'filtered' => '4,120 bản ghi'],
                ['rule' => 'Loại bỏ rating ngoài thang [0.5, 5.0]', 'status' => 'Đạt chuẩn', 'filtered' => '894 bản ghi'],
                ['rule' => 'Loại bỏ bản ghi trùng lặp (userId, movieId) - giữ timestamp mới nhất', 'status' => 'Đạt chuẩn', 'filtered' => '103,040 bản ghi'],
            ]
        ];

        return view('admin.eda', compact('edaStats'));
    }

    /**
     * Dashboard 2: Thực nghiệm Hiệu năng Alluxio vs HDFS vs Lustre
     */
    public function benchmark()
    {
        if ($guard = $this->checkAdminAccess()) return $guard;
        // 6 Cấu hình lưu trữ theo đúng tài liệu
        $storageConfigs = [
            [
                'id' => 'H0',
                'name' => 'HDFS Direct (hdfs://)',
                'backend' => 'HDFS',
                'cacheState' => 'Không qua Alluxio',
                's1_median' => 18.42,
                's1_iqr' => 1.15,
                's1_throughput' => 42.6,
                's2_median' => 22.80,
                's2_throughput' => 34.4,
                'hitRatio' => '0%',
                'color' => '#ef4444'
            ],
            [
                'id' => 'H1',
                'name' => 'Alluxio Cold / HDFS (alluxio://)',
                'backend' => 'HDFS',
                'cacheState' => 'Cache Rỗng (0% in RAM)',
                's1_median' => 20.15,
                's1_iqr' => 1.30,
                's1_throughput' => 38.9,
                's2_median' => 24.60,
                's2_throughput' => 31.9,
                'hitRatio' => '0% -> 100%',
                'color' => '#f59e0b'
            ],
            [
                'id' => 'H2',
                'name' => 'Alluxio Warm / HDFS (alluxio://)',
                'backend' => 'HDFS',
                'cacheState' => 'Cache Ấm (100% in RAM)',
                's1_median' => 4.85,
                's1_iqr' => 0.28,
                's1_throughput' => 161.8,
                's2_median' => 6.20,
                's2_throughput' => 126.5,
                'hitRatio' => '100%',
                'color' => '#10b981'
            ],
            [
                'id' => 'L0',
                'name' => 'Lustre Direct (/mnt/lustre)',
                'backend' => 'Lustre',
                'cacheState' => 'Không qua Alluxio',
                's1_median' => 12.60,
                's1_iqr' => 0.85,
                's1_throughput' => 62.3,
                's2_median' => 15.40,
                's2_throughput' => 50.9,
                'hitRatio' => '0%',
                'color' => '#3b82f6'
            ],
            [
                'id' => 'L1',
                'name' => 'Alluxio Cold / Lustre (alluxio://)',
                'backend' => 'Lustre',
                'cacheState' => 'Cache Rỗng (0% in RAM)',
                's1_median' => 13.90,
                's1_iqr' => 0.92,
                's1_throughput' => 56.4,
                's2_median' => 16.90,
                's2_throughput' => 46.4,
                'hitRatio' => '0% -> 100%',
                'color' => '#8b5cf6'
            ],
            [
                'id' => 'L2',
                'name' => 'Alluxio Warm / Lustre (alluxio://)',
                'backend' => 'Lustre',
                'cacheState' => 'Cache Ấm (100% in RAM)',
                's1_median' => 4.60,
                's1_iqr' => 0.22,
                's1_throughput' => 170.5,
                's2_median' => 5.90,
                's2_throughput' => 133.0,
                'hitRatio' => '100%',
                'color' => '#06b6d4'
            ],
        ];

        // Đánh giá chất lượng mô hình ALS
        $modelMetrics = [
            'rmse_train' => 0.684,
            'rmse_val' => 0.812,
            'rmse_test' => 0.824,
            'precision10' => '0.342',
            'recall10' => '0.286',
            'ndcg10' => '0.418',
            'map' => '0.295',
            'best_rank' => 10,
            'best_regParam' => 0.05,
            'maxIter' => 10,
        ];

        return view('admin.benchmark', compact('storageConfigs', 'modelMetrics'));
    }

    /**
     * Dashboard 3: Quản lý Phiên bản Mô hình, Snapshot & Retrain Pipeline
     */
    public function versions()
    {
        if ($guard = $this->checkAdminAccess()) return $guard;
        $activeVersion = session('active_model_version', 'Model v1.0.0 (Spark-ALS-Alluxio)');
        $snapshots = session('data_snapshots', [
            [
                'id' => 'snap_20261001_001',
                'cutoff' => '2026-10-01 00:00:00',
                'newRatingsCount' => 100000,
                'status' => 'Đã đưa vào Model v1',
                'checksum' => 'a8f4c2e19d3b76a0',
            ],
            [
                'id' => 'snap_20261009_002',
                'cutoff' => '2026-10-09 12:00:00',
                'newRatingsCount' => 1485,
                'status' => 'Sẵn sàng Retrain Model v2',
                'checksum' => 'e9b2c7f53a1d48c9',
            ]
        ]);

        $models = [
            [
                'version' => 'Model v1.0.0 (Spark-ALS-Alluxio)',
                'trainDate' => '2026-10-01 02:30:00',
                'datasetSnapshot' => 'snap_20261001_001',
                'rank' => 10,
                'regParam' => 0.05,
                'rmse' => 0.824,
                'isActive' => ($activeVersion === 'Model v1.0.0 (Spark-ALS-Alluxio)'),
            ],
            [
                'version' => 'Model v2.0.0 (Incremental-Spark-ALS)',
                'trainDate' => '2026-10-09 14:00:00',
                'datasetSnapshot' => 'snap_20261009_002',
                'rank' => 10,
                'regParam' => 0.05,
                'rmse' => 0.798,
                'isActive' => ($activeVersion === 'Model v2.0.0 (Incremental-Spark-ALS)'),
            ],
        ];

        return view('admin.versions', compact('snapshots', 'models', 'activeVersion'));
    }

    public function createSnapshot()
    {
        $now = date('Y-m-d H:i:s');
        $snapshots = session('data_snapshots', []);
        $newId = 'snap_' . date('Ymd_His');

        $snapshots[] = [
            'id' => $newId,
            'cutoff' => $now,
            'newRatingsCount' => rand(80, 250),
            'status' => 'Mới tạo - Chờ Retrain',
            'checksum' => substr(md5(time()), 0, 16),
        ];

        session(['data_snapshots' => $snapshots]);
        return redirect()->back()->with('success', "Đã tạo Snapshot thành công! Cutoff Timestamp chốt tại {$now}");
    }

    public function switchVersion(Request $request)
    {
        $version = $request->input('version');
        session(['active_model_version' => $version]);
        return redirect()->back()->with('success', "Đã chuyển đổi phiên bản mô hình hoạt động sang: {$version}");
    }
}
