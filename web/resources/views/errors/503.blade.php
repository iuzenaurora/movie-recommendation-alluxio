@extends('layouts.app')
@section('title', 'Đang cập nhật · MovieLab')
@section('content')
<section class="hero-cinema max-w-2xl mx-auto text-center p-10 md:p-16 my-10">
<div class="flow-icon !w-16 !h-16 !rounded-2xl !mb-7"><i data-lucide="refresh-cw" class="w-7 h-7"></i></div>
<p class="eyebrow">MovieLab / Đang đồng bộ</p>
<h1 class="text-3xl font-bold mt-4 leading-tight">Gợi ý đang được cập nhật.</h1>
<p class="text-sm text-slate-400 leading-7 mt-5">Dịch vụ phim đang tạm bận hoặc bảo trì. Tài khoản và đánh giá của bạn được giữ nguyên. Trang sẽ tự thử lại sau 30 giây.</p>
<button class="primary mt-7" onclick="location.reload()">Thử lại ngay</button>
@if(auth()->user()?->role === 'admin')<a class="secondary mt-4" href="{{ route('admin.jobs') }}">Theo dõi quá trình</a>@endif
</section>
@endsection
@push('scripts')
<script>setTimeout(()=>location.reload(),30000);</script>
@endpush
