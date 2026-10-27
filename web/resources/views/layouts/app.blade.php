<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'MovieLab')</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{brand:{400:'#b4a4ff',500:'#9682ff',600:'#7660df',950:'#201b3d'}}}}}</script>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script>
<link rel="stylesheet" href="{{ asset('css/cinema.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('css/aurora.css') }}?v=1">
<script src="{{ asset('js/cinema.js') }}?v=5" defer></script>
</head>
<body class="min-h-screen">
<a href="#main-content" class="skip-link">Đến nội dung chính</a>
<header class="flix-header">
    <a href="{{ route('movies.index') }}" class="flix-brand" aria-label="MovieLab · Trang chủ">MOVIE<span>LAB</span><b>.</b></a>
    <form method="GET" action="{{ route('movies.index') }}" class="header-search" role="search">
        <i data-lucide="search" aria-hidden="true"></i><input name="q" value="{{ request('q', '') }}" aria-label="Tìm phim" placeholder="Tìm bộ phim bạn yêu thích…"><button aria-label="Tìm kiếm" type="submit">↵</button>
    </form>
    @auth
        <div class="header-account"><a class="account-avatar" href="{{ route('profile.index') }}" aria-label="Tài khoản của {{ auth()->user()->name }}">{{ mb_substr(auth()->user()->name, 0, 1) }}</a><span>{{ auth()->user()->name }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button" title="Đăng xuất" aria-label="Đăng xuất"><i data-lucide="log-out"></i></button></form></div>
    @else
        <a href="{{ route('login') }}" class="secondary header-login"><i data-lucide="log-in"></i><span>Đăng nhập</span></a>
    @endauth
</header>
<nav class="flix-sidebar" aria-label="Điều hướng chính">
    @foreach([
        ['movies.index', 'movies.*', 'house', 'Khám phá'],
        ['recommendations.index', 'recommendations.*', 'sparkles', 'Dành cho bạn'],
        ['profile.index', 'profile.*', 'library', 'Đã đánh giá'],
    ] as [$destination, $pattern, $icon, $label])
        <a href="{{ route($destination) }}" class="sidebar-link {{ request()->routeIs($pattern) ? 'active' : '' }}" @if(request()->routeIs($pattern)) aria-current="page" @endif><i data-lucide="{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a>
    @endforeach
    @auth
        @if(auth()->user()->role === 'admin')
            <span class="sidebar-divider" aria-hidden="true"></span>
            @foreach([
                ['admin.jobs', 'admin.jobs*', 'workflow', 'Điều hành'],
                ['admin.benchmark', 'admin.benchmark', 'chart-no-axes-combined', 'Benchmark'],
                ['admin.eda', 'admin.eda', 'database', 'Dữ liệu'],
                ['admin.versions', 'admin.versions', 'brain-circuit', 'Models'],
            ] as [$destination, $pattern, $icon, $label])
                <a href="{{ route($destination) }}" class="sidebar-link {{ request()->routeIs($pattern) ? 'active' : '' }}" @if(request()->routeIs($pattern)) aria-current="page" @endif><i data-lucide="{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a>
            @endforeach
        @endif
    @endauth
</nav>
<main id="main-content" class="flix-main" tabindex="-1">
    @foreach(['success','error'] as $flash)
        @if(session($flash))<div role="status" class="mb-6 p-4 rounded-xl border {{ $flash === 'error' ? 'border-amber-700 bg-amber-950' : 'border-emerald-800 bg-emerald-950' }}">{{ session($flash) }}</div>@endif
    @endforeach
    @if($errors->any())<div role="alert" class="mb-6 p-4 border border-rose-800 rounded-xl bg-rose-950">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @yield('content')
</main>
<footer class="flix-footer"><div><a class="flix-brand" href="{{ route('movies.index') }}">MOVIE<span>LAB</span><b>.</b></a><p>Những câu chuyện hay. Một thế giới để khám phá.</p><p>Danh mục, trailer & gợi ý theo gu của bạn.</p></div><div><a href="{{ route('movies.index') }}">Khám phá phim</a><a href="{{ route('recommendations.index') }}">Dành cho bạn</a>@guest<a href="{{ route('register') }}">Tạo tài khoản</a>@endguest<p>MovieLens · Spark ALS · HDFS / Alluxio</p></div></footer>
@stack('scripts')
</body>
</html>
