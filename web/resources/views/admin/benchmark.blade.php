@extends('layouts.app')
@section('title', 'Benchmark · Hiệu năng & chất lượng gợi ý')
@section('content')
@php
    $scenarios = [
        'H0' => ['Đọc trực tiếp HDFS', 'Mốc so sánh, không đọc qua Alluxio.'],
        'H1' => ['Alluxio · chưa nạp cache', 'Cache dữ liệu đo rỗng khi bắt đầu; Alluxio cần lấy dữ liệu từ HDFS.'],
        'H2' => ['Alluxio · đã nạp cache', 'Dữ liệu đo đã nằm trong cache trước khi bắt đầu.'],
    ];
    $phases = [
        'readPlusFitSeconds' => ['Tổng thời gian đọc + huấn luyện', 'Tổng hai giai đoạn bên dưới, không phải toàn bộ quy trình cập nhật model.'],
        'readMaterializeSeconds' => ['Đọc & chuẩn bị dữ liệu', 'Đọc, lưu DataFrame trong Spark và chạy tổng hợp để thực sự xử lý dữ liệu; không phải I/O thuần.'],
        'fitMaterializeSeconds' => ['Huấn luyện ALS', 'Chạy ALS và tính đầy đủ các bảng vector người dùng, vector phim.'],
    ];
    $timings = collect($report['timingSummary'] ?? []);
    $totals = $timings->where('phase', 'readPlusFitSeconds')->keyBy('label');
    $direct = $totals->get('H0');
    $warm = $totals->get('H2');
    $comparable = ($direct['medianSeconds'] ?? 0) > 0 && ($warm['medianSeconds'] ?? 0) > 0;
    $ratio = $comparable ? $direct['medianSeconds'] / $warm['medianSeconds'] : null;
    $change = $comparable ? 100 * (1 - $warm['medianSeconds'] / $direct['medianSeconds']) : null;
    $metrics = $manifest['metrics'] ?? [];
    $metricNames = [
        'BytesReadCache' => ['Đọc từ cache Alluxio', 'Dữ liệu lấy từ cache trong thời gian đo.'],
        'BytesReadRemote' => ['Truyền giữa client và worker', 'Lưu lượng RPC, không đồng nghĩa với lượng đọc HDFS.'],
        'BytesReadUfsAll' => ['Alluxio đọc từ HDFS nền', 'Không đo lượt đọc H0 trực tiếp.'],
    ];
@endphp
<div class="benchmark-page space-y-8">
    <section class="hero-cinema p-6 md:p-9">
        <p class="eyebrow">Phòng đo MovieLab</p>
        <h1 class="text-3xl md:text-4xl font-bold mt-3">Hệ thống nhanh đến đâu?<br>Gợi ý tốt đến mức nào?</h1>
        <p class="text-slate-300 mt-4 max-w-2xl leading-7">Hai nhóm kết quả riêng: tốc độ xử lý dữ liệu với HDFS / Alluxio và chất lượng dự đoán của mô hình ALS.</p>
        <div class="flex flex-wrap gap-3 mt-6"><a class="secondary" href="#performance">01 · Hiệu năng xử lý</a><a class="secondary" href="#quality">02 · Chất lượng gợi ý</a><a class="secondary" href="{{ route('admin.jobs') }}">Xem lịch chạy tự động →</a></div>
        <p class="text-xs text-slate-400 mt-5">Kết quả của lần chạy đã lưu. Mở trang này không tự khởi chạy benchmark.</p>
    </section>

    <section id="performance" class="space-y-5 benchmark-section" aria-labelledby="performance-title">
        <div><p class="eyebrow">01 / Hiệu năng xử lý</p><h2 id="performance-title" class="text-2xl font-bold mt-2">Alluxio có giúp xử lý nhanh hơn?</h2><p class="text-slate-400 mt-2">So sánh cùng dữ liệu và cấu hình ALS giữa ba đường đọc dữ liệu.</p></div>
        @if(!$report)
            <div class="glass-panel rounded-2xl p-6"><h3 class="font-bold">Chưa có kết quả benchmark</h3><p class="text-slate-400 mt-2">Chưa tìm thấy báo cáo tại đường dẫn cấu hình. Mở trang điều hành để kiểm tra lịch hoặc chạy benchmark ngay.</p><a class="secondary mt-4" href="{{ route('admin.jobs') }}">Mở trang điều hành</a></div>
        @else
            <div class="grid md:grid-cols-3 gap-4">
                <div class="metric-card glass-panel"><p class="metric-label">Tổng thời gian · HDFS trực tiếp</p><p class="metric-value">{{ isset($direct['medianSeconds']) ? number_format($direct['medianSeconds'], 2).' s' : 'Chưa có' }}</p><p class="metric-note">Trung vị đọc + huấn luyện · H0</p></div>
                <div class="metric-card glass-panel"><p class="metric-label">Tổng thời gian · Alluxio đã nạp cache</p><p class="metric-value">{{ isset($warm['medianSeconds']) ? number_format($warm['medianSeconds'], 2).' s' : 'Chưa có' }}</p><p class="metric-note">Trung vị đọc + huấn luyện · H2</p></div>
                <div class="metric-card glass-panel"><p class="metric-label">Thay đổi so với HDFS</p><p class="metric-value {{ $comparable && $change >= 0 ? 'text-emerald-300' : 'text-amber-200' }}">{{ $comparable ? number_format(abs($change), 1).'%' : 'Chưa đủ dữ liệu' }}</p><p class="metric-note">{{ $comparable ? ($change > 0 ? 'Giảm thời gian xử lý' : ($change < 0 ? 'Tăng thời gian xử lý' : 'Thời gian bằng nhau')) : 'Cần thời gian tổng của cả H0 và H2' }}</p></div>
            </div>
            <div class="benchmark-verdict" role="status">
                @if($comparable)
                    @if($change > 0)
                        <p><strong>Ở lần đo này, Alluxio đã nạp cache nhanh hơn:</strong> tốc độ gấp {{ number_format($ratio, 2) }} lần HDFS trực tiếp, giảm {{ number_format($change, 1) }}% thời gian đọc + huấn luyện.</p>
                    @elseif($change < 0)
                        <p><strong>Ở lần đo này, Alluxio đã nạp cache chậm hơn:</strong> thời gian đọc + huấn luyện tăng {{ number_format(abs($change), 1) }}% so với HDFS trực tiếp.</p>
                    @else
                        <p><strong>Ở lần đo này, hai kịch bản có cùng thời gian tổng trung vị.</strong></p>
                    @endif
                    <p class="text-xs text-slate-400 mt-2">So sánh mô tả các lượt chạy đã lưu; không chứng minh khác biệt có ý nghĩa thống kê hay Alluxio luôn nhanh hơn.</p>
                @else
                    <p>Chưa đủ thời gian tổng H0 và H2 để kết luận tăng tốc. Các giai đoạn có dữ liệu vẫn được hiển thị bên dưới.</p>
                @endif
            </div>
            <div class="grid md:grid-cols-3 gap-4">
                @foreach($scenarios as $label => [$name, $description])
                    <div class="glass-panel rounded-2xl p-5"><span class="benchmark-tag" data-scenario="{{ $label }}">{{ $label }}</span><h3 class="font-bold mt-3">{{ $name }}</h3><p class="text-sm text-slate-400 mt-2 leading-6">{{ $description }}</p></div>
                @endforeach
            </div>
            <div class="grid xl:grid-cols-3 gap-5">
                @foreach($phases as $phase => [$title, $description])
                    @php
                        $rows = $timings->where('phase', $phase)->keyBy('label');
                        $maximum = max(0.001, (float) $rows->max('medianSeconds'));
                    @endphp
                    <figure class="glass-panel rounded-2xl p-5">
                        <figcaption class="font-bold">{{ $title }}</figcaption><p class="text-xs text-slate-400 mt-2 leading-5">{{ $description }}</p>
                        <p class="benchmark-chart-hint">Thời gian trung vị · thanh ngắn hơn là nhanh hơn</p>
                        @foreach($scenarios as $label => [$name])
                            @php($row = $rows->get($label))
                            <div class="benchmark-bar-row"><div class="flex justify-between gap-3 text-sm"><span>{{ $label }}</span><strong>{{ isset($row['medianSeconds']) ? number_format($row['medianSeconds'], 2).' s' : 'Chưa có' }}</strong></div>
                                <div class="benchmark-bar-track" aria-hidden="true"><div class="benchmark-bar" data-scenario="{{ $label }}" style="width: {{ isset($row['medianSeconds']) ? max(0, min(100, 100 * $row['medianSeconds'] / $maximum)) : 0 }}%"></div></div>
                                @if($row)<p class="text-xs text-slate-400 mt-1">{{ $row['runs'] }} lượt · Dao động IQR {{ number_format($row['iqrSeconds'], 2) }} s</p>@endif
                            </div>
                        @endforeach
                    </figure>
                @endforeach
            </div>
            <details class="glass-panel rounded-2xl p-5" open>
                <summary class="font-bold cursor-pointer">Phép đo này bao gồm những gì?</summary>
                <dl class="benchmark-facts mt-4">
                    <div><dt>Bộ dữ liệu</dt><dd>{{ $report['datasetVersion'] ?? 'Chưa có' }}</dd></div>
                    <div><dt>Số đánh giá huấn luyện</dt><dd>{{ isset($report['trainCheck']['rows']) ? number_format($report['trainCheck']['rows']) : 'Chưa có' }}</dd></div>
                    <div><dt>Cấu hình Spark</dt><dd>{{ $report['environment']['master'] ?? 'Chưa ghi nhận' }} · Spark {{ $report['environment']['sparkVersion'] ?? 'Chưa ghi nhận' }}</dd></div>
                    <div><dt>Cấu hình ALS</dt><dd>Rank {{ $report['alsParams']['rank'] ?? '—' }} · {{ $report['alsParams']['maxIter'] ?? '—' }} vòng lặp · Reg {{ $report['alsParams']['regParam'] ?? '—' }}</dd></div>
                </dl>
                <ul class="list-disc pl-5 text-sm text-slate-300 space-y-2 mt-4">
                    <li>Không tính khởi động Spark, nạp trước cache, kiểm thử chất lượng, xuất model hay gợi ý trên web.</li>
                    <li>Spark lưu DataFrame bằng MEMORY_AND_DISK trước khi huấn luyện. Phần đọc và phần tính toán có đặc điểm khác nhau.</li>
                    <li>OS page cache không được kiểm soát. “Chưa nạp cache” chỉ nói đến cache Alluxio, không phải toàn bộ bộ nhớ hệ điều hành.</li>
                    <li>Trung vị (Median) là giá trị giữa các lần chạy. IQR là khoảng trải của 50% kết quả ở giữa; thấp hơn nghĩa là ít dao động hơn.</li>
                    <li>HDFS ở máy A; Spark và Alluxio ở máy B. Lustre chưa được thực nghiệm.</li>
                </ul>
                <p class="small-code mt-4">Nguồn báo cáo: {{ $relative }}</p>
                @if(!empty($report['warnings']))<div class="mt-4 p-4 rounded-xl border border-amber-400/20 text-amber-200 text-sm"><p class="font-bold">Lưu ý từ lần đo</p>@foreach($report['warnings'] as $warning)<p class="mt-2">{{ $warning }}</p>@endforeach</div>@endif
            </details>
            <details class="glass-panel rounded-2xl p-5">
                <summary class="font-bold cursor-pointer">Cache có thực sự được sử dụng? · Xem lượng đọc</summary>
                <p class="text-sm text-slate-400 mt-3">Chênh lệch bộ đếm trước / sau mỗi lượt, đơn vị MiB xấp xỉ (1 MiB = 1.048.576 byte).</p>
                @if(!empty($report['metricSummary']))
                    <div class="overflow-x-auto mt-4"><table class="w-full text-sm text-left"><caption class="text-left text-slate-400 pb-3">Trung vị và mức dao động lượng dữ liệu đọc qua Alluxio</caption><thead><tr><th>Kịch bản</th><th>Bộ đếm đo gì?</th><th>Trung vị (MiB)</th><th>IQR (MiB)</th></tr></thead><tbody>
                        @foreach($report['metricSummary'] as $row)
                            @php($metric = $metricNames[$row['metric']] ?? [$row['metric'], 'Bộ đếm kỹ thuật từ báo cáo.'])
                            <tr class="border-t border-white/10"><td>{{ $row['label'] }}</td><td>{{ $metric[0] }}<p class="text-xs text-slate-400 mt-1">{{ $metric[1] }}</p></td><td>{{ number_format($row['medianMiBApprox'], 2) }}</td><td>{{ number_format($row['iqrMiBApprox'], 2) }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                @else<p class="text-slate-400 mt-4">Báo cáo chưa có số đo lượng đọc cache.</p>@endif
                <p class="text-sm text-amber-200 mt-4 leading-6">Cache và RPC là các bộ đếm chồng lấp, không cộng chúng với nhau. Bộ đếm có làm tròn và độ trễ cập nhật, có thể gồm hoạt động khác trong cụm; UFS bằng 0 trên báo cáo không chứng minh cache hit chính xác 100%.</p>
            </details>
        @endif
    </section>

    <section id="quality" class="space-y-5 benchmark-section" aria-labelledby="quality-title">
        <div><p class="eyebrow">02 / Chất lượng gợi ý</p><h2 id="quality-title" class="text-2xl font-bold mt-2">ALS dự đoán sở thích tốt đến mức nào?</h2><p class="text-slate-400 mt-2">Đánh giá trên tập kiểm thử được giữ riêng khi huấn luyện, không phải điểm đo tốc độ Alluxio.</p></div>
        @if(!$metrics)
            <div class="glass-panel rounded-2xl p-6"><h3 class="font-bold">Chưa có chỉ số chất lượng mô hình</h3><p class="text-slate-400 mt-2">Manifest hiện tại chưa chứa kết quả kiểm thử. Không hiển thị số 0 thay cho dữ liệu chưa đo.</p></div>
        @else
            <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach([
                    ['RMSE', $metrics['test']['rmse'] ?? null, 'Thấp hơn tốt hơn', 'Sai lệch dự đoán điểm sao, phạt mạnh những lần dự đoán sai lớn.'],
                    ['MAE', $metrics['test']['mae'] ?? null, 'Thấp hơn tốt hơn', 'Trung bình dự đoán lệch bao nhiêu sao trên các đánh giá kiểm thử tính được điểm.'],
                    ['NDCG@10', $metrics['rankingAt10']['ndcg'] ?? null, 'Cao hơn tốt hơn · tối đa 1', 'Đo khả năng đưa phim phù hợp lên đầu danh sách 10 phim. Không phải phần trăm dự đoán đúng.'],
                    ['Coverage kiểm thử', isset($metrics['test']['coverage']) ? $metrics['test']['coverage'] * 100 : null, 'Cao hơn tốt hơn', 'Tỷ lệ đánh giá kiểm thử tính được dự đoán; không phải tỷ lệ phim được gợi ý.'],
                ] as [$name, $value, $direction, $description])
                    <article class="metric-card glass-panel"><h3 class="metric-label">{{ $name }}</h3><p class="metric-value">{{ $value === null ? 'Chưa đo' : number_format($value, $name === 'Coverage kiểm thử' ? 2 : 3).($name === 'Coverage kiểm thử' ? '%' : '') }}</p><p class="text-xs text-rose-200">{{ $direction }}</p><p class="text-sm text-slate-400 mt-3 leading-6">{{ $description }}</p></article>
                @endforeach
            </div>
            @if(isset($metrics['test']['mae']))<p class="benchmark-verdict">Dễ hiểu hơn: mô hình dự đoán lệch trung bình khoảng <strong>{{ number_format($metrics['test']['mae'], 2) }} sao</strong> trên các đánh giá kiểm thử tính được điểm.</p>@endif
            <div class="glass-panel rounded-2xl p-6">
                <h3 class="font-bold">So với cách gợi ý phổ biến</h3><p class="text-sm text-slate-400 mt-2">NDCG@10 · cùng điều kiện kiểm thử · cao hơn tốt hơn</p>
                <div class="grid sm:grid-cols-2 gap-4 mt-4"><div class="benchmark-comparison"><span>ALS thuần</span><strong>{{ isset($metrics['rankingAt10']['ndcg']) ? number_format($metrics['rankingAt10']['ndcg'], 3).' / 1' : 'Chưa đo' }}</strong></div><div class="benchmark-comparison"><span>Gợi ý phổ biến</span><strong>{{ isset($metrics['popularityAt10']['ndcg']) ? number_format($metrics['popularityAt10']['ndcg'], 3).' / 1' : 'Chưa đo' }}</strong></div></div>
                <p class="text-xs text-slate-400 mt-4">Số người dùng được đánh giá xếp hạng: {{ isset($metrics['rankingUsersEvaluated']) ? number_format($metrics['rankingUsersEvaluated']) : 'Chưa ghi nhận' }}.</p>
            </div>
        @endif
        <div class="rounded-2xl border border-white/10 p-5 text-sm text-slate-400 leading-6">
            <p><strong class="text-slate-200">Phạm vi kết quả:</strong> model {{ $manifest['modelVersion'] ?? 'Chưa có' }} · dataset {{ $manifest['datasetVersion'] ?? 'Chưa có' }}.</p>
            <p class="mt-2">{{ $manifest['metricsScope'] ?? $metrics['ratingMetricsScope'] ?? 'Chưa ghi nhận mô tả phạm vi kiểm thử trong manifest.' }}</p>
            <p class="mt-2">Các chỉ số trên đánh giá ALS thuần. Hybrid trên web có thêm điều chỉnh theo thể loại; chưa có kết quả kiểm thử riêng ở đây để kết luận Hybrid tốt hơn. Benchmark tốc độ và kiểm thử chất lượng có thể thuộc hai phiên bản dữ liệu khác nhau; hãy đối chiếu phiên bản trước khi báo cáo.</p>
        </div>
    </section>
</div>
@endsection
