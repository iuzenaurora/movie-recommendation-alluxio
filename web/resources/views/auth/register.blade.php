@extends('layouts.app')

@section('title', 'Đăng ký - BigData Cinema AI')

@section('content')
<div class="max-w-md mx-auto space-y-6">

    <div class="text-center space-y-2">
        <h1 class="text-2xl font-extrabold text-white">Đăng ký Tài khoản Mới</h1>
        <p class="text-xs text-slate-400">Tạo tài khoản để kiểm thử cơ chế Cold-Start (Người dùng mới chưa có rating).</p>
    </div>

    <div class="p-6 sm:p-8 rounded-3xl glass-panel border border-slate-700/80 space-y-6">
        <form method="POST" action="{{ route('register.post') }}" class="space-y-4">
            @csrf
            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-slate-300">Họ và Tên</label>
                <input type="text" name="name" placeholder="Ví dụ: Hoàng Anh Quân" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-brand-500 focus:outline-none">
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-slate-300">Email</label>
                <input type="email" name="email" placeholder="user@example.com" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-brand-500 focus:outline-none">
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-slate-300">Mật khẩu</label>
                <input type="password" name="password" value="123456" required class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-700 text-white text-sm focus:border-brand-500 focus:outline-none">
            </div>

            <button type="submit" class="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm shadow-lg shadow-brand-500/30 transition">
                Tạo tài khoản & Bắt đầu
            </button>
        </form>

        <div class="text-center text-xs text-slate-400">
            Đã có tài khoản? <a href="{{ route('login') }}" class="text-brand-400 font-semibold hover:underline">Đăng nhập</a>
        </div>
    </div>

</div>
@endsection
