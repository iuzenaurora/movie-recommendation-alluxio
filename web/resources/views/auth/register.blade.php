@extends('layouts.app')
@section('title', 'Đăng ký · Movie Lab')
@section('content')
<div class="mx-auto max-w-md rounded-3xl glass-panel p-8 md:p-10 space-y-6 my-8">
<p class="text-xs uppercase tracking-widest text-rose-400">MOVIE LAB / YOUR TASTE</p>
<h1 class="text-3xl font-bold">Tìm bộ phim tiếp theo.</h1>
<p class="text-slate-400">Một tài khoản để lưu đánh giá và khám phá gợi ý cá nhân.</p>
<form method="POST" action="{{ route('register.post') }}" class="space-y-4">@csrf
<label class="block">Tên hiển thị<input class="field" name="name" value="{{ old('name') }}" maxlength="80" required autocomplete="name"></label>
<label class="block">Email<input class="field" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
<label class="block">Mật khẩu<input class="field" type="password" name="password" minlength="8" required autocomplete="new-password"></label>
<label class="block">Nhập lại mật khẩu<input class="field" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password"></label>
<button class="primary w-full">Tạo tài khoản</button></form>
<p class="text-sm"><a class="text-rose-400" href="{{ route('login') }}">Đã có tài khoản? Đăng nhập</a></p>
</div>
@endsection
