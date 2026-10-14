<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Movie Lab')</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{brand:{400:'#fda4af',500:'#fb7185',600:'#db476e',950:'#4c0519'}}}}}</script>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
<link rel="stylesheet" href="{{ asset('css/cinema.css') }}?v=3">
<script src="{{ asset('js/cinema.js') }}?v=3" defer></script>
</head>
<body class="min-h-screen">
<header class="site-header sticky top-0 z-50">
<nav class="max-w-7xl mx-auto flex flex-wrap items-center gap-2 px-5 py-4" aria-label="Điều hướng chính">
<a href="{{ route('movies.index') }}" class="text-lg font-black tracking-widest text-white mr-5 flex items-center"><span class="brand-mark"><i data-lucide="play" class="w-4 h-4"></i></span>MOVIE<span class="text-rose-300">LAB</span></a>
<a href="{{ route('movies.index') }}" class="nav-link {{ request()->routeIs('movies.*') ? 'active' : '' }}">Khám phá</a>
@auth
<a href="{{ route('recommendations.index') }}" class="nav-link {{ request()->routeIs('recommendations.*') ? 'active' : '' }}">Dành cho bạn</a>
<a href="{{ route('profile.index') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">Đánh giá của tôi</a>
@if(auth()->user()->role === 'admin')
<a href="{{ route('admin.jobs') }}" class="nav-link {{ request()->routeIs('admin.jobs*') ? 'active' : '' }}"><span class="text-rose-300">◉</span> Điều hành</a>
<a href="{{ route('admin.benchmark') }}" class="nav-link">Benchmark</a>
<a href="{{ route('admin.eda') }}" class="nav-link">Dữ liệu</a>
<a href="{{ route('admin.versions') }}" class="nav-link">Models</a>
@endif
<div class="ml-auto flex items-center gap-4 pl-2"><span class="text-xs text-slate-300">{{ auth()->user()->name }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="text-xs text-slate-500">Đăng xuất</button></form></div>
@else
<div class="ml-auto flex gap-2 items-center"><a href="{{ route('login') }}" class="nav-link">Đăng nhập</a><a href="{{ route('register') }}" class="secondary !py-2 !text-xs">Tham gia MovieLab ↗</a></div>
@endauth
</nav></header>
<main class="max-w-7xl mx-auto px-5 py-8 md:py-10">
@foreach(['success','error'] as $flash)
@if(session($flash))<div role="status" class="mb-6 p-4 rounded-xl border {{ $flash === 'error' ? 'border-amber-700 bg-amber-950' : 'border-emerald-800 bg-emerald-950' }}">{{ session($flash) }}</div>@endif
@endforeach
@if($errors->any())<div role="alert" class="mb-6 p-4 border border-rose-800 rounded-xl bg-rose-950">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@yield('content')
</main>
<footer class="border-t border-white/5 max-w-7xl mx-auto px-5 py-8 flex flex-wrap justify-between gap-5 text-xs text-slate-500"><div><span class="font-bold text-slate-300">MOVIELAB</span><p class="mt-2">Tìm câu chuyện tiếp theo. Theo gu của riêng bạn.</p></div><div class="text-right">MovieLens · Spark ALS<p class="mt-2">HDFS / Alluxio</p></div></footer>
@stack('scripts')
</body></html>
