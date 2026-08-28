<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users', 'permissions')->with('permissions')->orderBy('name')->get();
        return view('superadmin.roles.index', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'guard_name' => 'web',
            'description' => $data['description'] ?? null,
        ]);

        if (! empty($data['permissions'])) {
            $role->permissions()->sync($data['permissions']);
        }

        AdminActivityLog::record('role.created', 'role', $role->id, ['name' => $role->name]);

        return back()->with('success', 'Role created.');
    }

    public function edit(Role $role)
    {
        $role->load('permissions');
        $permissions = Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group');

        return view('superadmin.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role)
    {
        if ($role->slug === 'super_admin') {
            return back()->with('error', 'The Super Admin role cannot be edited.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        AdminActivityLog::record('role.updated', 'role', $role->id, ['name' => $role->name]);

        return redirect()->route('superadmin.roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        if (in_array($role->slug, ['super_admin', 'admin', 'buyer'])) {
            return back()->with('error', 'This core role cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'This role still has users assigned. Reassign them first.');
        }

        AdminActivityLog::record('role.deleted', 'role', $role->id, ['name' => $role->name]);
        $role->delete();

        return back()->with('success', 'Role deleted.');
    }
}
