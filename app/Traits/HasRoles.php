<?php

namespace App\Traits;

trait HasRoles
{
    public function roles()
    {
        return $this->belongsToMany(\App\Models\Role::class);
    }

    public function permissions()
    {
        return $this->roles()->with('permissions')->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->unique('id');
    }

    public function assignRole(...$roles)
    {
        $roles = collect($roles)->flatten()->map(function ($role) {
            if (is_string($role)) {
                return \App\Models\Role::firstOrCreate(['slug' => $role], [
                    'name' => ucwords(str_replace('_', ' ', $role)),
                    'guard_name' => 'web',
                ])->id;
            }
            return $role;
        });

        $this->roles()->syncWithoutDetaching($roles);

        return $this;
    }

    public function removeRole(...$roles)
    {
        $roles = collect($roles)->flatten()->map(fn ($r) => is_string($r) ? $r : $r->slug);
        $this->roles()->whereIn('slug', $roles->toArray())->detach();

        return $this;
    }

    public function syncRoles(...$roles)
    {
        $roles = collect($roles)->flatten()->map(function ($role) {
            if (is_string($role)) {
                return \App\Models\Role::firstOrCreate(['slug' => $role], [
                    'name' => ucwords(str_replace('_', ' ', $role)),
                    'guard_name' => 'web',
                ])->id;
            }
            return $role;
        });

        $this->roles()->sync($roles);

        return $this;
    }

    public function hasRole($roles): bool
    {
        $roles = collect((array) $roles);
        return $roles->contains(fn ($role) => $this->roles()->where('slug', $role)->exists());
    }

    public function hasAnyRole($roles): bool
    {
        return $this->hasRole($roles);
    }

    public function hasPermissionTo($permission): bool
    {
        return $this->hasExplicitPermissionTo($permission);
    }

    public function hasExplicitPermissionTo($permission): bool
    {
        if (is_string($permission)) {
            $permission = [$permission];
        }

        foreach ($this->roles as $role) {
            foreach ((array) $permission as $perm) {
                if ($role->hasPermission($perm)) {
                    return true;
                }
            }
        }

        return false;
    }
}
