@extends('layouts.app')
@section('title', 'Điều hành tự động · MovieLab')
@section('content')
<div id="operations" data-status-url="{{ route('admin.pipeline.status') }}" data-job-url="{{ url('/admin/jobs') }}" class="space-y-6">
<div class="flex flex-wrap items-center justify-between gap-5">
<div><p class="eyebrow mb-3">MovieLab / Control room</p><h1 class="text-3xl md:text-4xl font-bold tracking-tight">Dữ liệu chuyển động.<br class="md:hidden"> Gợi ý tốt hơn.</h1><p class="text-sm text-slate-400 mt-3">Theo dõi toàn bộ quá trình học từ gu điện ảnh của người dùng.</p></div>
<div class="flex flex-col items-end gap-2"><span id="worker-state" class="status-pill {{ $worker ? 'is-live' : 'is-warn' }}"><span class="status-dot"></span>{{ $worker ? 'Tự động hóa đang chạy' : 'Worker chưa kết nối' }}</span><span id="poll-state" class="text-[10px] text-slate-500" role="status">Cập nhật trực tiếp mỗi 5 giây</span></div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
<div class="metric-card glass-panel"><p class="metric-label">Đánh giá mới chờ học</p><p class="metric-value" id="pending-ratings">—</p><p class="metric-note" id="threshold-note">Đang đọc bộ đếm thay đổi</p></div>
<div class="metric-card glass-panel"><p class="metric-label">Benchmark tiếp theo</p><p class="metric-value text-xl" id="next-benchmark">—</p><p class="metric-note">H0 · H1 · H2 / 5 lượt mỗi kịch bản</p></div>
<div class="metric-card glass-panel"><p class="metric-label">Model đang phục vụ</p><p class="mt-4 mb-3 text-sm font-bold mono break-all" id="active-model">{{ $pipeline['model']['modelVersion'] ?? 'Chưa có model' }}</p><p class="metric-note">Được đọc từ manifest hiện tại</p></div>
<div class="metric-card glass-panel"><p class="metric-label">Xuất bản model</p><p class="metric-value text-xl" id="activation-mode">{{ ($automation['autoActivate'] ?? false) ? 'Tự động' : 'Admin duyệt' }}</p><p class="metric-note">Kiểm tra gói + sức khỏe API</p></div>
</div>

<section class="hero-cinema p-6 md:p-8">
<div class="flex flex-wrap items-start justify-between gap-4"><div><p class="eyebrow">Live data pipeline</p><h2 class="text-xl font-bold mt-2">Từ một đánh giá đến gợi ý tiếp theo</h2></div><span class="status-pill">MongoDB → HDFS → Alluxio → ALS</span></div>
<div class="pipeline-flow">
@foreach([['star','Đánh giá','MongoDB'],['camera','Snapshot','Lịch sử mới'],['layers','Ghép dữ liệu','MovieLens'],['database','HDFS','Máy A'],['zap','Alluxio','Đọc dữ liệu'],['brain-circuit','Spark ALS','Huấn luyện'],['sparkles','Model mới','Kiểm tra & phục vụ']] as $step)
<div class="flow-node"><div class="flow-icon"><i data-lucide="{{ $step[0] }}" class="w-4 h-4"></i></div><p class="flow-label">{{ $step[1] }}</p><p class="flow-sub">{{ $step[2] }}</p></div>
@endforeach
</div>
<div class="mt-6 pt-5 border-t border-white/10 grid md:grid-cols-[1fr_auto] gap-3 items-center">
<div><p id="pipeline-message" class="text-sm text-slate-200" aria-live="polite">Đang đồng bộ trạng thái tiến trình…</p><p class="text-xs text-slate-500 mt-2">Gợi ý cá nhân cập nhật ngay sau khi chấm điểm. Retrain định kỳ học lại model chung.</p></div>
<span id="last-check" class="text-[10px] text-slate-500"></span>
</div>
<p id="scheduler-error" class="mt-3 text-xs text-amber-300" role="alert"></p>
</section>

<div class="grid lg:grid-cols-[1.45fr_1fr] gap-6 items-start">
<section class="glass-panel p-6 md:p-7 rounded-3xl">
<div class="flex justify-between items-center gap-3"><div><p class="eyebrow">Activity</p><h2 class="text-xl font-bold mt-2">Nhật ký vận hành</h2></div><span class="text-[10px] text-slate-500">Tiến độ theo bước thực tế</span></div>
<div id="job-list" class="mt-5">
@forelse($jobs as $job)
<a href="{{ route('admin.jobs.show', $job['id']) }}" class="job-row"><p class="job-title font-semibold text-sm">{{ $job['kind'] }} · {{ $job['status'] }}</p><p class="text-xs text-slate-400 mt-2">{{ $job['stage'] ?? $job['createdAt'] }}</p><p class="small-code mt-2">{{ $job['id'] }}</p></a>
@empty<p class="py-8 text-sm text-slate-500">Chưa có tác vụ. Tiến trình sẽ tự xuất hiện khi đến lịch hoặc đủ đánh giá.</p>@endforelse
</div>
</section>

<div class="space-y-6">
<form method="POST" action="{{ route('admin.automation') }}" class="glass-panel p-6 rounded-3xl space-y-5">
@csrf<div><p class="eyebrow">Autopilot</p><h2 class="text-xl font-bold mt-2">Thiết lập tự động</h2></div>
<label class="block text-sm text-slate-300">Benchmark mỗi (giờ)<input class="field" type="number" min="0" max="168" name="benchmarkHours" value="{{ $automation['benchmarkHours'] ?? 24 }}" required><span class="text-[11px] text-slate-500">0 = tắt lịch đo</span></label>
<label class="block text-sm text-slate-300">Retrain sau số thay đổi đánh giá<input class="field" type="number" min="0" max="10000" name="retrainRatings" value="{{ $automation['retrainRatings'] ?? 20 }}" required><span class="text-[11px] text-slate-500">Gồm thêm, sửa và xóa điểm. 0 = tắt.</span></label>
<input type="hidden" name="autoActivate" value="0">
<label class="flex gap-3 items-start text-sm cursor-pointer"><input class="mt-1 accent-rose-400" type="checkbox" name="autoActivate" value="1" @checked($automation['autoActivate'] ?? false)><span>Tự cập nhật model sau retrain<span class="block text-[11px] text-slate-500 leading-5 mt-1">Xác minh gói model, chuyển phiên bản và kiểm tra API. Tự phục hồi model cũ nếu kiểm tra thất bại.</span></span></label>
<button class="primary w-full">Lưu thiết lập</button>
<p class="text-[11px] leading-5 text-slate-500">Máy A, máy B và Docker cần hoạt động. Các tác vụ chạy lần lượt; API gợi ý tạm bảo trì trong tác vụ nặng. Web điều hành vẫn mở.</p>
</form>
<div class="rounded-2xl border border-white/10 p-5 text-xs text-slate-400 leading-6">Lần sau, nhấp đúp <strong class="text-slate-200">MovieLab.pyw</strong> trong thư mục dự án để mở web và bật tự động hóa. Không cần nhập lệnh CMD.</div>
</div></div>

<details class="glass-panel rounded-3xl p-6">
<summary class="font-semibold cursor-pointer text-sm">Chạy ngay và quản lý phiên bản <span class="text-slate-500 font-normal ml-2">Tùy chọn của admin</span></summary>
<div class="grid sm:grid-cols-3 gap-4 mt-6">
@foreach(['retrain'=>['brain-circuit','Học lại từ dữ liệu mới'], 'benchmark'=>['chart-no-axes-combined','Đo hiệu năng H0 / H1 / H2'], 'snapshot'=>['camera','Chụp dữ liệu đánh giá']] as $kind => $item)
<form method="POST" action="{{ route('admin.jobs.store') }}" class="rounded-2xl bg-black/20 border border-white/5 p-5 space-y-4">@csrf<input type="hidden" name="kind" value="{{ $kind }}"><i data-lucide="{{ $item[0] }}" class="w-5 h-5 text-rose-300"></i><h3 class="text-sm font-semibold">{{ $item[1] }}</h3><button class="secondary w-full !py-2">Chạy ngay</button></form>
@endforeach
</div>
<div class="grid md:grid-cols-2 gap-5 mt-5">
<form method="POST" action="{{ route('admin.jobs.store') }}" class="space-y-3">@csrf<input type="hidden" name="kind" value="activate"><label class="block text-sm">Model đã huấn luyện<select class="field" name="model" required><option value="">Chọn phiên bản</option>@foreach($models as $model)<option value="{{ $model }}">{{ $model }}</option>@endforeach</select></label><button class="secondary">Kiểm tra và kích hoạt</button></form>
<form method="POST" action="{{ route('admin.jobs.store') }}" class="space-y-3">@csrf<input type="hidden" name="kind" value="rollback"><p class="text-sm">Khôi phục model trước đó</p><p class="text-xs text-slate-500 leading-6">Giữ nguyên tài khoản và lịch sử đánh giá khi chuyển phiên bản.</p><button class="secondary">Khôi phục phiên bản</button></form>
</div>
</details>
<details class="glass-panel rounded-3xl p-6">
<summary class="font-semibold cursor-pointer text-sm">Quản lý trailer chính thức</summary>
<form method="POST" action="{{ route('admin.trailers') }}" class="grid md:grid-cols-[1fr_1fr_auto] gap-4 items-end mt-5">@csrf<label class="text-sm">MovieLens ID<input class="field" type="number" min="1" name="movieId" required></label><label class="text-sm">YouTube video ID<input class="field" name="youtubeId" pattern="[A-Za-z0-9_-]{11}" maxlength="11" required></label><button class="secondary">Lưu trailer</button></form>
</details>
</div>
@endsection
@push('scripts')
<script>window.pipelineInitial=@json($pipeline);</script>
<script src="{{ asset('js/operations.js') }}?v=3" defer></script>
@endpush
