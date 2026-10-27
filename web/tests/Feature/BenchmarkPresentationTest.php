<?php

namespace Tests\Feature;

use Tests\TestCase;

class BenchmarkPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withViewErrors([]);
    }

    private function report(float $warmSeconds): array
    {
        return ['datasetVersion' => 'synthetic-test', 'trainCheck' => ['rows' => 100],
            'timingSummary' => [
                ['phase' => 'readPlusFitSeconds', 'label' => 'H0', 'runs' => 5, 'medianSeconds' => 100, 'iqrSeconds' => 2],
                ['phase' => 'readPlusFitSeconds', 'label' => 'H2', 'runs' => 5, 'medianSeconds' => $warmSeconds, 'iqrSeconds' => 1],
            ]];
    }

    public function test_faster_cache_has_correct_speed_ratio_and_time_reduction(): void
    {
        $this->view('admin.benchmark', ['report' => $this->report(80), 'manifest' => null, 'relative' => 'test.json'])
            ->assertSee('tốc độ gấp 1.25 lần')->assertSee('giảm 20.0%')
            ->assertSee('Alluxio · chưa nạp cache')->assertSee('Chưa có chỉ số chất lượng mô hình');
    }

    public function test_slower_and_equal_cache_do_not_claim_a_speedup(): void
    {
        $this->view('admin.benchmark', ['report' => $this->report(125), 'manifest' => null, 'relative' => 'test.json'])
            ->assertSee('Alluxio đã nạp cache chậm hơn')->assertSee('tăng 25.0%')->assertDontSee('tốc độ gấp');
        $this->view('admin.benchmark', ['report' => $this->report(100), 'manifest' => null, 'relative' => 'test.json'])
            ->assertSee('cùng thời gian tổng trung vị')->assertDontSee('tốc độ gấp');
    }

    public function test_missing_or_zero_baseline_does_not_invent_results(): void
    {
        $this->view('admin.benchmark', ['report' => null, 'manifest' => null, 'relative' => 'test.json'])
            ->assertSee('Chưa có kết quả benchmark')->assertDontSee('tốc độ gấp');
        $report = $this->report(80);
        $report['timingSummary'][0]['medianSeconds'] = 0;
        $this->view('admin.benchmark', ['report' => $report, 'manifest' => null, 'relative' => 'test.json'])
            ->assertSee('Chưa đủ thời gian tổng H0 và H2')->assertDontSee('tốc độ gấp');
        array_shift($report['timingSummary']);
        $this->view('admin.benchmark', ['report' => $report, 'manifest' => null, 'relative' => 'test.json'])
            ->assertSee('Chưa đủ dữ liệu')->assertDontSee('tốc độ gấp');
    }

    public function test_quality_units_and_scope_are_distinct_from_storage_benchmark(): void
    {
        $manifest = ['modelVersion' => 'quality-test', 'datasetVersion' => 'held-out-test', 'metrics' => [
            'test' => ['rmse' => 0.9, 'mae' => 0.7, 'coverage' => 0.98],
            'rankingAt10' => ['ndcg' => 0.3], 'popularityAt10' => ['ndcg' => 0.2], 'rankingUsersEvaluated' => 100,
        ]];
        $this->view('admin.benchmark', ['report' => null, 'manifest' => $manifest, 'relative' => 'test.json'])
            ->assertSee('0.70 sao')->assertSee('98.00%')->assertSee('0.300 / 1')->assertSee('0.200 / 1')
            ->assertSee('Không phải phần trăm dự đoán đúng')->assertSee('chưa có kết quả kiểm thử riêng')
            ->assertSee('held-out-test')->assertDontSee('Chưa có chỉ số chất lượng mô hình');
    }

    public function test_report_warnings_are_escaped_and_absent_quality_metrics_are_not_zero(): void
    {
        $report = $this->report(80);
        $report['warnings'] = ['<script>alert(1)</script>'];
        $this->view('admin.benchmark', ['report' => $report, 'manifest' => ['metrics' => ['test' => ['mae' => 0.7]]], 'relative' => 'test.json'])
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Chưa đo')->assertDontSee('0.000');
    }
}
