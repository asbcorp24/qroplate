<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => 'required|string|max:80',
            'password' => 'required|string|max:255',
        ]);

        $superLogin = (string) config('qroplate.super_admin_login');
        $superPassword = (string) config('qroplate.super_admin_password');

        if ($superPassword !== '' && hash_equals($superLogin, $data['login']) && hash_equals($superPassword, $data['password'])) {
            $request->session()->regenerate();
            $request->session()->put([
                'admin_authenticated' => true,
                'admin_role' => 'super',
                'admin_id' => null,
                'admin_name' => 'Супер администратор',
            ]);
            return redirect()->route('admin.dashboard');
        }

        $admin = Admin::where('login', $data['login'])->where('enabled', true)->first();
        if (!$admin || !Hash::check($data['password'], $admin->password)) {
            return back()->withErrors(['login' => 'Неверный логин или пароль'])->onlyInput('login');
        }

        $admin->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();
        $request->session()->put([
            'admin_authenticated' => true,
            'admin_role' => 'admin',
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
        ]);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
