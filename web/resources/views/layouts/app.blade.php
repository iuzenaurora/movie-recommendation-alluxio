<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'BigData Cinema - Hệ thống Gợi ý Phim')</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fff1f2',
                            500: '#e11d48',
                            600: '#be123c',
                            700: '#9f1239',
                            900: '#881337',
                        },
                        dark: {
                            950: '#070a11',
                            900: '#0b1120',
                            800: '#151f32',
                            700: '#1e293b',
                            600: '#334155',
                        }
                    },
                    fontFamily: {
                        sans: ['Outfit', 'Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts & Lucide Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js for Dashboards -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            font-family: 'Outfit', 'Inter', sans-serif;
            background-color: #070a11;
            color: #f1f5f9;
        }
        .glass-panel {
            background: rgba(11, 17, 32, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glass-card {
            background: rgba(21, 31, 50, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.07);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            transform: translateY(-4px);
            border-color: rgba(225, 29, 72, 0.5);
            box-shadow: 0 16px 32px -10px rgba(225, 29, 72, 0.3);
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #070a11;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #e11d48;
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen flex flex-col antialiased selection:bg-brand-500 selection:text-white">

    <!-- Header / Navbar -->
    <header class="sticky top-0 z-50 glass-panel border-b border-slate-800 shadow-xl">
        @php
            $user = session('current_user', \App\Services\MovieDataService::getDemoUsers()[0]);
            $demoUsers = \App\Services\MovieDataService::getDemoUsers();
            $isAdmin = ($user['role'] ?? 'user') === 'admin';
        @endphp

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20 gap-4">
                
                <!-- Logo -->
                <a href="{{ route('movies.index') }}" class="flex items-center gap-3 shrink-0 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-600 to-rose-500 flex items-center justify-center shadow-lg shadow-brand-500/30 group-hover:scale-105 transition">
                        <i data-lucide="clapperboard" class="w-5 h-5 text-white"></i>
                    </div>
                    <span class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-white via-slate-100 to-slate-300 bg-clip-text text-transparent">
                        BigData Cinema
                    </span>
                </a>

                <!-- Navigation Links: Phân quyền theo Role (User chỉ thấy User, Admin chỉ thấy Admin) -->
                <nav class="hidden lg:flex items-center gap-3">
                    
                    <!-- 1. PHÂN HỆ DÙNG CHUNG (PUBLIC) -->
                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-inner">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 px-1">🎬 Chung:</span>
                        <a href="{{ route('movies.index') }}" class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition flex items-center gap-1.5 {{ request()->routeIs('movies.*') ? 'bg-brand-600 text-white shadow-md shadow-brand-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            <i data-lucide="film" class="w-3.5 h-3.5"></i> Kho 62k Phim
                        </a>
                    </div>

                    <!-- 2. PHÂN HỆ NGƯỜI DÙNG (CHỈ HIỂN THỊ CHO USER) -->
                    @if(!$isAdmin)
                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-2xl bg-slate-900/80 border border-brand-500/30 shadow-inner">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-brand-400 px-1">👤 User:</span>
                        <a href="{{ route('recommendations.index') }}" class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition flex items-center gap-1.5 {{ request()->routeIs('recommendations.*') ? 'bg-brand-600 text-white shadow-md shadow-brand-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-300"></i> Gợi ý Top 10
                        </a>
                        <a href="{{ route('profile.index') }}" class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition flex items-center gap-1.5 {{ request()->routeIs('profile.*') ? 'bg-brand-600 text-white shadow-md shadow-brand-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            <i data-lucide="star" class="w-3.5 h-3.5 text-amber-400"></i> Đánh giá của tôi
                        </a>
                    </div>
                    @endif

                    <!-- 3. PHÂN HỆ QUẢN TRỊ (CHỈ HIỂN THỊ CHO ADMIN) -->
                    @if($isAdmin)
                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-2xl bg-slate-900/80 border border-indigo-500/30 shadow-inner">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-400 px-1">⚙️ Admin:</span>
                        <a href="{{ route('admin.eda') }}" class="px-2.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition flex items-center gap-1 {{ request()->routeIs('admin.eda') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            <i data-lucide="bar-chart-3" class="w-3.5 h-3.5"></i> EDA Data
                        </a>
                        <a href="{{ route('admin.benchmark') }}" class="px-2.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition flex items-center gap-1 {{ request()->routeIs('admin.benchmark') ? 'bg-emerald-600 text-white shadow-md shadow-emerald-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            <i data-lucide="gauge" class="w-3.5 h-3.5"></i> Benchmark
                        </a>
                        <a href="{{ route('admin.versions') }}" class="px-2.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition flex items-center gap-1 {{ request()->routeIs('admin.versions') ? 'bg-cyan-600 text-white shadow-md shadow-cyan-500/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' }}">
                            <i data-lucide="git-branch" class="w-3.5 h-3.5"></i> Model v1/v2
                        </a>
                    </div>
                    @endif

                </nav>

                <!-- Right Side: 1-Click Demo Persona Switcher & User Avatar -->
                <div class="flex items-center gap-3 shrink-0">
                    <!-- Persona Switcher Dropdown -->
                    <div class="relative group">
                        <button class="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-800/90 border border-slate-700 text-xs font-medium text-slate-200 hover:border-brand-500/60 transition shadow">
                            <span class="w-2 h-2 rounded-full {{ $isAdmin ? 'bg-indigo-400 animate-pulse' : 'bg-emerald-500' }} shrink-0"></span>
                            <span class="hidden sm:inline text-slate-400 text-[11px]">{{ $isAdmin ? 'Admin:' : 'User:' }}</span>
                            <span class="font-bold text-white max-w-[130px] truncate text-xs">{{ $user['name'] }}</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 group-hover:rotate-180 transition-transform"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 mt-2 w-80 rounded-2xl glass-panel shadow-2xl p-2 border border-slate-700 hidden group-hover:block z-50 animate-in fade-in slide-in-from-top-2 duration-200">
                            <div class="px-3 py-2 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center justify-between">
                                <span>Chuyển đổi Tài khoản Persona</span>
                                <span class="text-emerald-400 text-[10px]">1-Click Switch</span>
                            </div>
                            <div class="max-h-72 overflow-y-auto py-1 space-y-1">
                                @foreach($demoUsers as $du)
                                <a href="{{ route('auth.quick-login', $du['id']) }}" class="flex items-center gap-3 px-3 py-2 rounded-xl text-left hover:bg-slate-800/90 transition {{ $du['id'] == $user['id'] ? 'bg-brand-500/15 border border-brand-500/30' : '' }}">
                                    <img src="{{ $du['avatar'] }}" alt="{{ $du['name'] }}" class="w-8 h-8 rounded-lg object-cover">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-semibold text-slate-200 truncate">{{ $du['name'] }}</div>
                                        <div class="text-[10px] text-slate-400 truncate">{{ $du['persona'] }}</div>
                                    </div>
                                    @if($du['role'] === 'admin')
                                        <span class="px-1.5 py-0.5 text-[9px] font-bold uppercase rounded bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">Admin</span>
                                    @else
                                        <span class="px-1.5 py-0.5 text-[9px] font-bold uppercase rounded bg-slate-800 text-slate-400">User</span>
                                    @endif
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- User Avatar -->
                    <a href="{{ route('profile.index') }}" title="Hồ sơ của tôi" class="shrink-0">
                        <img src="{{ $user['avatar'] }}" alt="{{ $user['name'] }}" class="w-9 h-9 rounded-xl object-cover ring-2 {{ $isAdmin ? 'ring-indigo-500/50 hover:ring-indigo-400' : 'ring-brand-500/50 hover:ring-brand-400' }} transition">
                    </a>

                    <!-- Auth Button -->
                    <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold border border-slate-700 transition">
                        Đăng nhập
                    </a>
                </div>

            </div>
        </div>

        <!-- Subnav cho Màn hình nhỏ (Tự động lọc theo Role) -->
        <div class="lg:hidden px-4 py-2 bg-slate-950/80 border-t border-slate-800/80 flex items-center gap-2 overflow-x-auto scrollbar-none text-xs">
            <span class="text-[10px] font-bold text-slate-500 uppercase shrink-0">🎬 Chung:</span>
            <a href="{{ route('movies.index') }}" class="px-2.5 py-1 rounded-lg shrink-0 {{ request()->routeIs('movies.*') ? 'bg-brand-600 text-white font-bold' : 'text-slate-300 bg-slate-800/60' }}">Kho 62k Phim</a>
            
            @if(!$isAdmin)
            <div class="h-3.5 w-[1px] bg-slate-800 shrink-0"></div>
            <span class="text-[10px] font-bold text-brand-400 uppercase shrink-0">👤 User:</span>
            <a href="{{ route('recommendations.index') }}" class="px-2.5 py-1 rounded-lg shrink-0 {{ request()->routeIs('recommendations.*') ? 'bg-brand-600 text-white font-bold' : 'text-slate-300 bg-slate-800/60' }}">Gợi ý Top 10</a>
            <a href="{{ route('profile.index') }}" class="px-2.5 py-1 rounded-lg shrink-0 {{ request()->routeIs('profile.*') ? 'bg-brand-600 text-white font-bold' : 'text-slate-300 bg-slate-800/60' }}">Đánh giá</a>
            @endif

            @if($isAdmin)
            <div class="h-3.5 w-[1px] bg-slate-800 shrink-0"></div>
            <span class="text-[10px] font-bold text-indigo-400 uppercase shrink-0">⚙️ Admin:</span>
            <a href="{{ route('admin.eda') }}" class="px-2.5 py-1 rounded-lg shrink-0 {{ request()->routeIs('admin.eda') ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 bg-slate-800/60' }}">EDA</a>
            <a href="{{ route('admin.benchmark') }}" class="px-2.5 py-1 rounded-lg shrink-0 {{ request()->routeIs('admin.benchmark') ? 'bg-emerald-600 text-white font-bold' : 'text-slate-400 bg-slate-800/60' }}">Benchmark</a>
            <a href="{{ route('admin.versions') }}" class="px-2.5 py-1 rounded-lg shrink-0 {{ request()->routeIs('admin.versions') ? 'bg-cyan-600 text-white font-bold' : 'text-slate-400 bg-slate-800/60' }}">Model v1/v2</a>
            @endif
        </div>
    </header>

    <!-- Alert Notifications -->
    @if(session('success'))
    <div class="max-w-7xl mx-auto px-4 mt-4 w-full">
        <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 flex items-center gap-3 text-xs sm:text-sm shadow-lg animate-in fade-in slide-in-from-top-2">
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="max-w-7xl mx-auto px-4 mt-4 w-full">
        <div class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 flex items-center gap-3 text-xs sm:text-sm shadow-lg animate-in fade-in slide-in-from-top-2">
            <i data-lucide="alert-circle" class="w-4 h-4 text-rose-400 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    </div>
    @endif

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @yield('content')
    </main>

    <!-- Professional Cinema Footer -->
    <footer class="mt-auto border-t border-slate-800/90 glass-panel pt-12 pb-8 text-slate-400 text-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                
                <!-- Col 1: Brand & Project Description -->
                <div class="space-y-3 md:col-span-1">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-brand-600 flex items-center justify-center">
                            <i data-lucide="clapperboard" class="w-4 h-4 text-white"></i>
                        </div>
                        <span class="text-base font-extrabold text-white">BigData Cinema</span>
                    </div>
                    <p class="text-slate-400 text-xs leading-relaxed">
                        Hệ thống gợi ý phim thông minh ứng dụng thuật toán <strong>Spark ALS Collaborative Filtering</strong>, tăng tốc truy cập qua bộ nhớ đệm phân tán <strong>Alluxio</strong> trên <strong>HDFS & Lustre</strong>.
                    </p>
                </div>

                <!-- Col 2: Trang Dùng Chung & User -->
                <div class="space-y-2.5">
                    <div class="text-xs font-bold uppercase tracking-wider text-white">Phân hệ Người dùng</div>
                    <ul class="space-y-1.5">
                        <li><a href="{{ route('movies.index') }}" class="hover:text-brand-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-slate-600"></i> Khám phá phim (MovieLens)</a></li>
                        <li><a href="{{ route('recommendations.index') }}" class="hover:text-brand-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-slate-600"></i> Gợi ý Top 10 Cá nhân hóa</a></li>
                        <li><a href="{{ route('profile.index') }}" class="hover:text-brand-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-slate-600"></i> Lịch sử đánh giá & Hồ sơ</a></li>
                    </ul>
                </div>

                <!-- Col 3: Phân hệ Admin & Big Data -->
                <div class="space-y-2.5">
                    <div class="text-xs font-bold uppercase tracking-wider text-white">Phân hệ Quản trị Big Data</div>
                    <ul class="space-y-1.5">
                        <li><a href="{{ route('admin.eda') }}" class="hover:text-indigo-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-slate-600"></i> Dashboard Khảo sát Dữ liệu (EDA)</a></li>
                        <li><a href="{{ route('admin.benchmark') }}" class="hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-slate-600"></i> Dashboard Benchmark Alluxio</a></li>
                        <li><a href="{{ route('admin.versions') }}" class="hover:text-cyan-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-slate-600"></i> Quản lý Phiên bản Model v1 / v2</a></li>
                    </ul>
                </div>

                <!-- Col 4: Công nghệ sử dụng -->
                <div class="space-y-2.5">
                    <div class="text-xs font-bold uppercase tracking-wider text-white">Công nghệ Cốt lõi</div>
                    <div class="flex flex-wrap gap-1.5">
                        <span class="px-2 py-1 rounded bg-slate-800 text-[10px] font-semibold text-slate-300 border border-slate-700">PHP 8.3 & Laravel</span>
                        <span class="px-2 py-1 rounded bg-slate-800 text-[10px] font-semibold text-slate-300 border border-slate-700">MongoDB Persistence</span>
                        <span class="px-2 py-1 rounded bg-slate-800 text-[10px] font-semibold text-slate-300 border border-slate-700">Apache Spark 3.5.6</span>
                        <span class="px-2 py-1 rounded bg-slate-800 text-[10px] font-semibold text-slate-300 border border-slate-700">Alluxio Data Cache</span>
                        <span class="px-2 py-1 rounded bg-slate-800 text-[10px] font-semibold text-slate-300 border border-slate-700">HDFS & Lustre Storage</span>
                        <span class="px-2 py-1 rounded bg-slate-800 text-[10px] font-semibold text-slate-300 border border-slate-700">MovieLens 25M Dataset</span>
                    </div>
                </div>

            </div>

            <!-- Copyright & Note -->
            <div class="pt-6 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] text-slate-500">
                <div>
                    Đồ án môn học Nhập môn Dữ liệu lớn • Đại học Quốc gia TP.HCM
                </div>
                <div>
                    Hệ thống chạy trên nền tảng <strong>PHP/Laravel</strong> • Môi trường thực nghiệm <strong>Spark-Alluxio</strong>
                </div>
            </div>

        </div>
    </footer>

    <!-- Initialize Lucide Icons -->
    <script>
        lucide.createIcons();
    </script>
    @stack('scripts')
</body>
</html>
