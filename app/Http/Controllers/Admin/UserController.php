<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->input('tab'), ['buyers', 'sellers', 'logistics', 'admins'], true)
            ? $request->input('tab')
            : 'all';
        $logisticsType = in_array($request->input('type'), ['riders', 'admin'], true)
            ? $request->input('type')
            : 'riders';
        $roleFilter = $tab === 'logistics'
            ? ($logisticsType === 'riders' ? 'rider' : 'logistics')
            : $request->input('role');

        $baseQuery = $this->filteredUsersQuery($request, $roleFilter);
        $countBaseQuery = $this->filteredUsersQuery($request, $request->input('role'));
        $query = $this->forTab(clone $baseQuery, $tab, $logisticsType)->with('roles');

        $users = $query->distinct()->latest()->paginate(12)->withQueryString();
        $roles = Role::orderBy('name')->get();
        $tabCounts = collect(['all', 'buyers', 'sellers', 'logistics', 'admins'])->mapWithKeys(fn ($key) => [
            $key => $this->forTab(clone $countBaseQuery, $key, $key === 'logistics' ? 'all' : 'riders')->distinct()->count('users.id'),
        ])->all();
        $logisticsCounts = collect(['riders', 'admin'])->mapWithKeys(fn ($key) => [
            $key => $this->forTab(clone $countBaseQuery, 'logistics', $key)->distinct()->count('users.id'),
        ])->all();
        $filterRoles = $tab === 'logistics'
            ? $roles->where('slug', $logisticsType === 'riders' ? 'rider' : 'logistics')
            : $roles;

        return view('admin.users.index', compact('users', 'roles', 'filterRoles', 'roleFilter', 'tab', 'tabCounts', 'logisticsType', 'logisticsCounts'));
    }

    public function create()
    {
        $roles = Role::whereNotIn('slug', ['admin', 'super_admin'])->orderBy('name')->get();

        return view('admin.users.create', compact('roles'));
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

        $this->guardRoleAssignment($data['roles']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => ! empty($data['is_active']),
        ]);

        $user->roles()->sync($data['roles']);

        AdminActivityLog::record('user.created', 'user', $user->id, ['email' => $user->email, 'roles' => $data['roles']]);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function show(User $user)
    {
        $user->load('roles', 'orders');
        $activity = AdminActivityLog::where('user_id', $user->id)->latest()->take(20)->get();

        return view('admin.users.show', compact('user', 'activity'));
    }

    public function edit(User $user)
    {
        $user->load('roles');
        abort_if($user->hasRole('admin'), 403, 'The sole Admin account cannot be reassigned.');
        $roles = Role::whereNotIn('slug', ['admin', 'super_admin'])->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array'],
            'roles.*' => ['exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $isSelf = $user->id === auth()->id();
        abort_if($user->hasRole('admin'), 403, 'The sole Admin account cannot be reassigned.');

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

        $this->guardRoleAssignment($data['roles']);
        $user->roles()->sync($data['roles']);

        AdminActivityLog::record('user.updated', 'user', $user->id, ['roles' => $data['roles']]);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->hasRole('admin')) {
            return back()->with('error', 'The sole Admin account cannot be deleted.');
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
        if ($user->hasRole('admin')) {
            return back()->with('error', 'The sole Admin account cannot be deactivated.');
        }

        $user->update(['is_active' => ! $user->is_active]);
        AdminActivityLog::record('user.status', 'user', $user->id, ['is_active' => $user->is_active]);

        return back()->with('success', 'User status updated.');
    }

    public function resetPasswordForm(User $user)
    {
        return view('admin.users.reset-password', compact('user'));
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => Hash::make($request->input('password'))]);
        AdminActivityLog::record('user.password_reset', 'user', $user->id);

        return redirect()->route('admin.users.index')->with('success', 'Password reset.');
    }

    /** Read-only view of the sole system Admin. */
    public function admins(Request $request)
    {
        $query = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
            ->with('roles');

        if ($request->filled('q')) {
            $query->where('name', 'like', "%{$request->input('q')}%")
                ->orWhere('email', 'like', "%{$request->input('q')}%");
        }

        $admins = $query->latest()->paginate(12)->withQueryString();

        return view('admin.admins.index', compact('admins'));
    }

    protected function guardRoleAssignment(array $roleIds): void
    {
        abort_if(Role::whereIn('id', $roleIds)->whereIn('slug', ['admin', 'super_admin'])->exists(), 422,
            'Admin authority cannot be assigned through user management.');
    }

    protected function filteredUsersQuery(Request $request, ?string $roleFilter = null): Builder
    {
        return User::query()
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $search = trim((string) $request->input('q'));
                $query->where(fn ($match) => $match->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->when(filled($roleFilter), fn (Builder $query) => $query->whereHas('roles', fn ($role) => $role->where('slug', $roleFilter)))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_active', $request->input('status') === 'active'));
    }

    protected function forTab(Builder $query, string $tab, string $logisticsType = 'all'): Builder
    {
        return match ($tab) {
            'buyers' => $query->whereHas('roles', fn ($role) => $role->where('slug', 'buyer')),
            'sellers' => $query->whereHas('roles', fn ($role) => $role->where('slug', 'seller'))
                ->whereHas('sellerProfile', fn ($profile) => $profile->where('status', 'approved'))
                ->whereHas('store'),
            'logistics' => $this->forLogisticsType($query, $logisticsType),
            'admins' => $query->whereHas('roles', fn ($role) => $role->where('slug', 'admin')),
            default => $query,
        };
    }

    protected function forLogisticsType(Builder $query, string $type): Builder
    {
        $staff = fn (Builder $builder) => $builder->whereHas('roles', fn ($role) => $role->where('slug', 'logistics'));
        $riders = fn (Builder $builder) => $builder->whereHas('roles', fn ($role) => $role->where('slug', 'rider'))
            ->whereHas('riderProfile')
            ->where(fn ($user) => $user->where('registration_status', 'approved')->orWhereNull('registration_status'));

        return match ($type) {
            'admin' => $staff($query),
            'riders' => $riders($query),
            default => $query->where(fn (Builder $users) => $staff($users)->orWhere(fn (Builder $users) => $riders($users))),
        };
    }
}
