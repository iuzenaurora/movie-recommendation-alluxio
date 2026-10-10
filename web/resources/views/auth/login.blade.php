@extends('layouts.app')

@section('title', 'Đăng nhập - BigData Cinema AI')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">

    <!-- Header -->
    <div class="text-center space-y-2">
        <h1 class="text-3xl font-extrabold text-white">Đăng nhập Hệ thống Gợi ý Phim</h1>
        <p class="text-sm text-slate-400">Chọn đăng nhập nhanh bằng 1 trong 10 Tài khoản Demo (để test thuật toán) hoặc nhập thông tin.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- Standard Login Form Mockup -->
        <div class="p-6 sm:p-8 rounded-3xl glass-panel border border-slate-700/80 space-y-6">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i data-lucide="log-in" class="w-5 h-5 text-brand-500"></i> Đăng nhập tài khoản
            </h2>

            <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-300">Email đăng nhập</label>
                    <input type="email" name="email" value="action_fan@bigdata.vn" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-brand-500 focus:outline-none">
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-300">Mật khẩu</label>
                    <input type="password" name="password" value="123456" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-brand-500 focus:outline-none">
                </div>

                <button type="submit" class="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm shadow-lg shadow-brand-500/30 transition">
                    Đăng nhập ngay
                </button>
            </form>

            <div class="text-center text-xs text-slate-400">
                Chưa có tài khoản? <a href="{{ route('register') }}" class="text-brand-400 font-semibold hover:underline">Đăng ký mới</a>
            </div>
        </div>

        <!-- 1-Click Demo Login Personas Grid -->
        <div class="p-6 sm:p-8 rounded-3xl glass-panel border border-brand-500/30 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i data-lucide="zap" class="w-5 h-5 text-amber-400"></i> Đăng nhập Nhanh 1-Click
                </h2>
                <span class="text-[11px] text-emerald-400 font-bold">10 Demo Personas</span>
            </div>
            <p class="text-xs text-slate-400">Bấm vào bất kỳ tài khoản nào để vào hệ thống ngay mà không cần gõ mật khẩu:</p>

            <div class="max-h-80 overflow-y-auto space-y-2 pr-1">
                @foreach($demoUsers as $du)
                <a href="{{ route('auth.quick-login', $du['id']) }}" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 hover:bg-brand-500/15 border border-slate-800 hover:border-brand-500/40 transition group">
                    <div class="flex items-center gap-3">
                        <img src="{{ $du['avatar'] }}" alt="{{ $du['name'] }}" class="w-9 h-9 rounded-lg object-cover">
                        <div>
                            <div class="text-xs font-bold text-white group-hover:text-brand-400 transition">{{ $du['name'] }}</div>
                            <div class="text-[10px] text-slate-400">{{ $du['persona'] }}</div>
                        </div>
                    </div>
                    <i data-lucide="arrow-right" class="w-4 h-4 text-slate-500 group-hover:text-brand-400 transition-transform group-hover:translate-x-1"></i>
                </a>
                @endforeach
            </div>
        </div>

    </div>

</div>
@endsection
