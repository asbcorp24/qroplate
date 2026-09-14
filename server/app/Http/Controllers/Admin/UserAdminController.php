<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserAdminController extends Controller
{
    private function onlySuper(Request $request): void
    {
        abort_unless($request->session()->get('admin_role') === 'super', 403);
    }

    public function index(Request $request)
    {
        $this->onlySuper($request);
        return view('admin.admins.index', ['admins' => Admin::withCount('devices')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->onlySuper($request);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'login' => 'required|string|max:80|unique:admins,login',
            'password' => 'required|string|min:6|max:255',
        ]);
        $data['password'] = Hash::make($data['password']);
        $data['enabled'] = true;
        Admin::create($data);
        return back()->with('success', 'Администратор добавлен');
    }

    public function update(Request $request, Admin $admin)
    {
        $this->onlySuper($request);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'login' => 'required|string|max:80|unique:admins,login,' . $admin->id,
            'password' => 'nullable|string|min:6|max:255',
            'enabled' => 'nullable|boolean',
        ]);
        $admin->name = $data['name'];
        $admin->login = $data['login'];
        $admin->enabled = $request->boolean('enabled');
        if (!empty($data['password'])) $admin->password = Hash::make($data['password']);
        $admin->save();
        return back()->with('success', 'Администратор сохранён');
    }
}
