<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', ['users' => User::with('roles')->orderBy('name')->paginate(30)]);
    }

    public function create()
    {
        return $this->form(new User);
    }

    public function edit(User $user)
    {
        return $this->form($user);
    }

    public function store(Request $r, AuditService $audit)
    {
        $data = $this->validated($r);
        $role = $data['role'];
        unset($data['role']);
        $user = User::create($data);
        $user->assignRole($role);
        $audit->log('user_created', $user, [], ['email' => $user->email, 'role' => $role]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'User created.');
    }

    public function update(Request $r, User $user, AuditService $audit)
    {
        if ($user->hasRole('SUPER ADMIN') && $user->id !== auth()->id()) {
            abort(403, 'Another Super Admin account cannot be modified.');
        }$old = ['name' => $user->name, 'email' => $user->email, 'roles' => $user->getRoleNames()];
        $data = $this->validated($r, $user);
        $role = $data['role'];
        unset($data['role']);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }$user->update($data);
        $user->syncRoles([$role]);
        $audit->log('user_updated', $user, $old, ['name' => $user->name, 'email' => $user->email, 'role' => $role, 'is_active' => $user->is_active]);

        return back()->with('success', 'User updated.');
    }

    private function form(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'roles' => Role::orderBy('name')->get()]);
    }

    private function validated(Request $r, ?User $user = null): array
    {
        return $r->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', Rule::unique('users')->ignore($user)], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'], 'phone' => 'nullable|string|max:50', 'job_title' => 'nullable|string|max:100', 'role' => 'required|exists:roles,name', 'is_active' => 'required|boolean']);
    }
}
