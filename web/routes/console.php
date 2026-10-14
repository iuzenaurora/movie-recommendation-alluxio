<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

Artisan::command('app:bootstrap-admin', function () {
    if (User::where('role', 'admin')->exists()) {
        return;
    }
    $email = 'admin@movielab.local';
    if (User::where('email', $email)->exists()) {
        $this->error('Bootstrap email already exists. Promote an account using app:promote-admin.');

        return;
    }
    $password = Str::random(24);
    $user = User::create(['name' => 'Movie Lab Admin', 'email' => $email, 'password' => Hash::make($password)]);
    $user->forceFill(['role' => 'admin'])->save();
    $path = env('ADMIN_CREDENTIALS_FILE', '/runtime/admin-credentials.json');
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    file_put_contents($path, json_encode(['email' => $email, 'password' => $password], JSON_PRETTY_PRINT));
    chmod($path, 0600);
    $this->info('Admin created. Credentials saved to configured credentials file.');
})->purpose('Create the initial local admin once without resetting existing accounts');

Artisan::command('app:promote-admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->firstOrFail();
    $user->forceFill(['role' => 'admin'])->save();
    $this->info('Administrator role assigned.');
})->purpose('Explicitly grant administrator access to an existing account');
