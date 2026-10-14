@extends('layouts.app')
@section('title', 'Phiên bản model')
@section('content')
<div class="space-y-6">
<div><p class="eyebrow">Model registry</p><h1 class="text-3xl font-bold mt-3">Phiên bản model và pipeline</h1></div>
<p class="text-sm text-slate-400">API đang nạp: {{ $health['model']['modelVersion'] ?? 'Chưa sẵn sàng' }} · Model trên đĩa: {{ $manifest['modelVersion'] ?? 'Chưa có' }}</p>
<div class="glass-panel rounded-2xl overflow-x-auto p-4">
<table class="w-full text-left text-sm"><thead><tr><th>Thư mục</th><th>Model</th><th>Dataset</th><th>Phạm vi metrics</th></tr></thead><tbody>
@foreach($models as $model)<tr class="border-b border-white/10"><td>{{ $model['folder'] }}</td><td>{{ $model['meta']['modelVersion'] }}</td><td>{{ $model['meta']['datasetVersion'] }}</td><td>{{ $model['meta']['metricsScope'] ?? 'Held-out ban đầu' }}</td></tr>@endforeach
</tbody></table></div>
<section class="glass-panel rounded-2xl p-6 space-y-4">
<h2 class="text-xl font-bold">Cập nhật ngay trên web</h2>
<p class="text-sm text-slate-400 leading-7">Snapshot, retrain, kiểm tra, kích hoạt và khôi phục phiên bản đều được điều phối tại trung tâm điều hành. Có thể bật tự cập nhật model sau retrain; hệ thống kiểm tra sức khỏe API và phục hồi model cũ nếu kích hoạt thất bại.</p>
<a class="primary" href="{{ route('admin.jobs') }}">Mở trung tâm điều hành</a>
</section>
</div>
@endsection
