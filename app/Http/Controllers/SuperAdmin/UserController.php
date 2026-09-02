<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->input('tab'), ['buyers', 'sellers', 'other'], true)
            ? $request->input('tab')
            : 'all';

        $query = User::with('roles');

        match ($tab) {
            'buyers' => $query->whereHas('roles', fn ($q) => $q->where('slug', 'buyer')),
            'sellers' => $query->whereHas('roles', fn ($q) => $q->where('slug', 'seller')),
            'other' => $query->whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', ['buyer', 'seller'])),
            default => null,
        };

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->input('q')}%")
                    ->orWhere('email', 'like', "%{$request->input('q')}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('slug', $request->input('role')));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $users = $query->latest()->paginate(12)->withQueryString();
        $roles = Role::all();
        $tabCounts = [
            'all' => User::count(),
            'buyers' => User::whereHas('roles', fn ($q) => $q->where('slug', 'buyer'))->count(),
            'sellers' => User::whereHas('roles', fn ($q) => $q->where('slug', 'seller'))->count(),
            'other' => User::whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', ['buyer', 'seller']))->count(),
        ];

        return view('superadmin.users.index', compact('users', 'roles', 'tab', 'tabCounts'));
    }

    public function create()
    {
        $roles = Role::all();
        return view('superadmin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => ! empty($data['is_active']),
        ]);

        $user->roles()->sync($data['roles']);

        AdminActivityLog::record('user.created', 'user', $user->id, ['email' => $user->email, 'roles' => $data['roles']]);

        return redirect()->route('superadmin.users.index')->with('success', 'User created.');
    }

    public function show(User $user)
    {
        $user->load('roles', 'orders');
        $activity = \App\Models\AdminActivityLog::where('user_id', $user->id)->latest()->take(20)->get();
        return view('superadmin.users.show', compact('user', 'activity'));
    }

    public function edit(User $user)
    {
        $user->load('roles');
        $roles = Role::all();
        return view('superadmin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Prevent removing your own Super Admin role / deactivating yourself.
        $isSelf = $user->id === auth()->id();
        $removingOwnSuperAdmin = $isSelf && $user->hasRole('super_admin') && ! in_array(
            Role::where('slug', 'super_admin')->value('id'),
            $data['roles']
        );

        if ($removingOwnSuperAdmin) {
            return back()->withErrors(['roles' => 'You cannot remove your own Super Admin role.']);
        }

        if ($isSelf && isset($data['is_active']) && ! $data['is_active']) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => ! empty($data['is_active']),
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        $user->roles()->sync($data['roles']);

        AdminActivityLog::record('user.updated', 'user', $user->id, ['roles' => $data['roles']]);

        return redirect()->route('superadmin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->hasRole('super_admin')) {
            return back()->with('error', 'Super Admin accounts cannot be deleted.');
        }

        $user->delete();
        AdminActivityLog::record('user.deleted', 'user', $user->id);

        return back()->with('success', 'User deleted.');
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot change your own status.');
        }

        $user->update(['is_active' => ! $user->is_active]);
        AdminActivityLog::record('user.status', 'user', $user->id, ['is_active' => $user->is_active]);

        return back()->with('success', 'User status updated.');
    }

    public function resetPasswordForm(User $user)
    {
        return view('superadmin.users.reset-password', compact('user'));
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => Hash::make($request->input('password'))]);
        AdminActivityLog::record('user.password_reset', 'user', $user->id);

        return redirect()->route('superadmin.users.index')->with('success', 'Password reset.');
    }

    /** Admin management view (subset of users with admin/superadmin roles). */
    public function admins(Request $request)
    {
        $query = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->with('roles');

        if ($request->filled('q')) {
            $query->where('name', 'like', "%{$request->input('q')}%")
                ->orWhere('email', 'like', "%{$request->input('q')}%");
        }

        $admins = $query->latest()->paginate(12)->withQueryString();

        return view('superadmin.admins.index', compact('admins'));
    }
}
