<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin(): mixed
    {
        return view('auth.login');
    }

    public function showRegister(): mixed
    {
        return view('auth.register');
    }

    public function register(Request $request): mixed
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user = User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => Hash::make($data['password'])]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('recommendations.index')->with('success', 'Tài khoản đã được tạo. Hãy đánh giá vài phim để nhận gợi ý.');
    }

    public function login(Request $request): mixed
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email hoặc mật khẩu chưa đúng.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('movies.index'));
    }

    public function logout(Request $request): mixed
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
