<?php

namespace App\Http\Controllers\IT;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles.application')->latest('id');
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return view('it.users.index', ['users' => $query->paginate(25)->withQueryString()]);
    }

    public function create()
    {
        return view('it.users.create', ['roles' => $this->roles()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => (bool) $data['is_active'],
        ]);
        $this->syncRoles($user, $data['role_ids']);

        return redirect()->route('it.users.index')->with('success', 'Akun berhasil dibuat.');
    }

    public function edit(User $user)
    {
        return view('it.users.edit', ['managedUser' => $user->load('roles'), 'roles' => $this->roles()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user->id, false);
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'is_active' => (bool) $data['is_active'],
        ];
        if (!empty($data['password'])) $payload['password'] = Hash::make($data['password']);

        if ($request->user()->is($user) && !$payload['is_active']) {
            return back()->withErrors(['is_active' => 'Akun IT yang sedang digunakan tidak dapat dinonaktifkan sendiri.']);
        }

        $user->update($payload);
        $this->syncRoles($user, $data['role_ids']);

        return redirect()->route('it.users.index')->with('success', 'Akun berhasil diperbarui.');
    }

    private function validated(Request $request, ?int $id = null, bool $passwordRequired = true): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'min:8'],
            'is_active' => ['required', 'boolean'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);
    }

    private function roles()
    {
        $app = Application::where('code', config('accounting.application_code', 'ACCOUNTING'))->firstOrFail();
        return Role::where('application_id', $app->id)->orderBy('name')->get();
    }

    private function syncRoles(User $user, array $roleIds): void
    {
        $before = $user->roles()->orderBy('roles.id')->get(['roles.id','roles.code','roles.name'])->toArray();
        $user->roles()->sync($roleIds);
        $after = $user->roles()->orderBy('roles.id')->get(['roles.id','roles.code','roles.name'])->toArray();
        if ($before !== $after) app(\App\Services\ActivityLogger::class)->record($user, 'updated', ['roles'=>$before], ['roles'=>$after], ['roles']);
    }
}
