<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserAccessController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'users' => User::with('services')->orderBy('name')->get(),
            'ordersService' => Service::where('slug', 'orders')->firstOrFail(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'orders_enabled' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => false,
            'is_active' => true,
        ]);

        $this->syncOrdersAccess($user, $request->boolean('orders_enabled'));

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
            'orders_enabled' => ['nullable', 'boolean'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => $user->is_admin ? true : $request->boolean('is_active'),
        ]);

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if (!$user->is_admin) {
            $this->syncOrdersAccess($user, $request->boolean('orders_enabled'));
        }

        return back()->with('success', 'User access updated successfully.');
    }

    private function syncOrdersAccess(User $user, bool $enabled): void
    {
        $service = Service::where('slug', 'orders')->firstOrFail();

        $enabled
            ? $user->services()->syncWithoutDetaching([$service->id])
            : $user->services()->detach($service->id);
    }
}
