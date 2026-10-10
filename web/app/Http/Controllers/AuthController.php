<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MovieDataService;

class AuthController extends Controller
{
    public function showLogin()
    {
        $demoUsers = MovieDataService::getDemoUsers();
        return view('auth.login', compact('demoUsers'));
    }

    public function login(Request $request)
    {
        $email = $request->input('email');
        $demoUsers = MovieDataService::getDemoUsers();
        
        $user = collect($demoUsers)->firstWhere('email', $email);

        if (!$user) {
            // Đăng nhập mặc định cho user tự nhập
            $user = [
                'id' => rand(200, 999),
                'name' => $request->input('name', 'Khách xem phim'),
                'email' => $email,
                'role' => 'user',
                'persona' => 'Custom User',
                'avatar' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=120&auto=format&fit=crop&q=80',
                'description' => 'Tài khoản người dùng mới tạo.',
                'historyVersion' => 0,
            ];
        }

        session(['current_user' => $user]);
        return redirect()->route('movies.index')->with('success', 'Đăng nhập thành công với vai trò: ' . $user['name']);
    }

    public function quickLogin($id)
    {
        $demoUsers = MovieDataService::getDemoUsers();
        $user = collect($demoUsers)->firstWhere('id', intval($id));

        if ($user) {
            session(['current_user' => $user]);
            return redirect()->back()->with('success', 'Đã chuyển sang tài khoản: ' . $user['name']);
        }

        return redirect()->route('login')->with('error', 'Không tìm thấy tài khoản demo.');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $name = $request->input('name', 'User Mới');
        $email = $request->input('email');

        $newUser = [
            'id' => rand(500, 999),
            'name' => $name,
            'email' => $email,
            'role' => 'user',
            'persona' => 'Newly Registered User',
            'avatar' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=120&auto=format&fit=crop&q=80',
            'description' => 'Tài khoản vừa đăng ký để kiểm thử Cold-Start Recommender.',
            'historyVersion' => 0,
        ];

        session(['current_user' => $newUser]);
        return redirect()->route('movies.index')->with('success', 'Đăng ký tài khoản thành công!');
    }

    public function logout()
    {
        session()->forget('current_user');
        return redirect()->route('login')->with('success', 'Đã đăng xuất thành công.');
    }
}
