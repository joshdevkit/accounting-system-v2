<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::withCount('users')->orderBy('name')->get();

        return Inertia::render('roles/index', [
            'roles' => $roles->map(fn(Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'permission_count' => $role->permissions()->count(),
                'user_count' => (int) $role->users_count,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/create', [
            'permissions' => $this->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions') ?? []);

        return to_route('roles.show', $role)->with('success', "Role \"{$role->name}\" created.");
    }

    public function show(Role $role): Response
    {
        $role->load('permissions:id,name');
        $role->loadCount('users');

        return Inertia::render('roles/show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'user_count' => (int) $role->users_count,
            ],
            'permissions' => $this->permissionGroups(),
        ]);
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions:id,name');

        return Inertia::render('roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissions' => $this->permissionGroups(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->fill(['name' => $request->validated('name')])->save();
        $role->syncPermissions($request->validated('permissions') ?? []);

        return to_route('roles.show', $role)->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => "\"{$role->name}\" is still assigned to users. Remove it from them first.",
            ]);
        }

        $role->delete();

        return to_route('roles.index')->with('success', 'Role removed.');
    }

    /**
     * The permission list grouped for display, in the config's own order.
     *
     * @return list<array{name: string, label: string}>
     */
    protected function permissionGroups(): array
    {
        return \Spatie\Permission\Models\Permission::orderBy('name')
            ->get(['name', 'label'])
            ->map(fn($permission) => ['name' => $permission->name, 'label' => $permission->label ?: $permission->name])
            ->all();
    }
}
