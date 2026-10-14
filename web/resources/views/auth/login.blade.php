@extends('layouts.app')
@section('title', 'Đăng nhập · Movie Lab')
@section('content')
<div class="mx-auto max-w-md rounded-3xl glass-panel p-8 md:p-10 space-y-6 my-8">
<p class="text-xs uppercase tracking-widest text-rose-400">MOVIE LAB / WELCOME BACK</p>
<h1 class="text-3xl font-bold">Tiếp tục khám phá.</h1>
<p class="text-slate-400">Đăng nhập để giữ lịch sử và nhận gợi ý theo gu của bạn.</p>
<form method="POST" action="{{ route('login.post') }}" class="space-y-4">@csrf
<label class="block">Email<input class="field" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
<label class="block">Mật khẩu<input class="field" type="password" name="password" required autocomplete="current-password"></label>
<label class="flex gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập</label>
<button class="primary w-full">Đăng nhập</button>
</form>
<p class="text-sm text-slate-400">Chưa có tài khoản? <a class="text-rose-400" href="{{ route('register') }}">Đăng ký</a></p>
</div>
@endsection
