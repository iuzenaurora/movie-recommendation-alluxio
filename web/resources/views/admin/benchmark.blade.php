@extends('layouts.app')
@section('title', 'Thực nghiệm Alluxio')
@section('content')
<div class="space-y-6"><h1 class="text-3xl font-bold">Thực nghiệm Alluxio / HDFS</h1>
<p>Nguồn: {{ $relative }}. Spark local[2]; HDFS ở máy A, Spark và Alluxio ở máy B.</p>
@if(!$report)<p>Chưa có báo cáo tại đường dẫn cấu hình.</p>@else
<p>Dataset {{ $report['datasetVersion'] }} · {{ number_format($report['trainCheck']['rows']) }} train ratings.</p>
<div class="overflow-x-auto"><table class="w-full text-left"><thead><tr><th>Giai đoạn</th><th>Nhãn</th><th>Số lượt</th><th>Median (s)</th><th>IQR (s)</th><th>Speedup / H0</th></tr></thead><tbody>
@foreach($report['timingSummary'] as $row)
<tr class="border-b border-slate-700"><td class="py-3">{{ $row['phase'] }}</td><td>{{ $row['label'] }}</td><td>{{ $row['runs'] }}</td><td>{{ number_format($row['medianSeconds'], 2) }}</td><td>{{ number_format($row['iqrSeconds'], 2) }}</td><td>{{ number_format($row['speedupVsH0'], 3) }}×</td></tr>
@endforeach
</tbody></table></div>
<h2 class="text-xl font-bold">Lượng đọc Alluxio (MiB xấp xỉ)</h2>
<table class="w-full text-left"><thead><tr><th>Nhãn</th><th>Bộ đếm</th><th>Median</th><th>IQR</th></tr></thead><tbody>
@foreach($report['metricSummary'] ?? [] as $row)
<tr><td>{{ $row['label'] }}</td><td>{{ $row['metric'] }}</td><td>{{ number_format($row['medianMiBApprox'], 2) }}</td><td>{{ number_format($row['iqrMiBApprox'], 2) }}</td></tr>
@endforeach
</tbody></table>
<p>Cache và RPC là các bộ đếm chồng lấp, không cộng chúng như các lượng đọc độc lập. UFS không đo lượng đọc H0 trực tiếp.</p>
<p>H0: HDFS trực tiếp; H1: cache rỗng; H2: cache đã nạp. Lustre: chưa thực nghiệm.</p>
<p class="text-amber-300">OS page cache không được kiểm soát. Preload và khởi động Spark bị loại khỏi phép đo. Bộ đếm làm tròn không chứng minh cache hit chính xác 100%.</p>
@foreach($report['warnings'] ?? [] as $warning)<p>{{ $warning }}</p>@endforeach
@endif
@if(isset($manifest['metrics']))
@php($metrics = $manifest['metrics'])
<h2 class="text-xl font-bold">Chất lượng mô hình {{ $manifest['modelVersion'] }}</h2>
<p>RMSE test {{ number_format($metrics['test']['rmse'], 4) }} · MAE {{ number_format($metrics['test']['mae'], 4) }} · Coverage {{ number_format($metrics['test']['coverage'] * 100, 2) }}%</p>
<p>NDCG@10: ALS {{ number_format($metrics['rankingAt10']['ndcg'], 4) }} / popularity {{ number_format($metrics['popularityAt10']['ndcg'], 4) }} · {{ $metrics['rankingUsersEvaluated'] }} người được đánh giá.</p>
<p>{{ $manifest['metricsScope'] ?? $metrics['ratingMetricsScope'] ?? '' }}</p>
@endif</div>
@endsection
