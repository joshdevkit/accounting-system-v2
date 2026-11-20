<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $permissions = Permission::withCount('roles')->orderBy('name')->get();

        return Inertia::render('permissions/index', [
            'permissions' => $permissions->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'label' => $permission->label,
                'role_count' => (int) $permission->roles_count,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('permissions/create');
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $permission = Permission::create([
            'name' => $request->validated('name'),
            'label' => $request->validated('label') ?: $request->validated('name'),
        ]);

        return to_route('permissions.index')->with(
            'success',
            "Permission \"{$permission->name}\" created. It only restricts something once a developer checks it in the code.",
        );
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render('permissions/edit', [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'label' => $permission->label,
            ],
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->fill([
            'name' => $request->validated('name'),
            'label' => $request->validated('label') ?: $request->validated('name'),
        ])->save();

        return to_route('permissions.index')->with('success', 'Permission updated.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($permission->roles()->exists()) {
            throw ValidationException::withMessages([
                'permission' => "\"{$permission->name}\" is still assigned to roles. Remove it from them first.",
            ]);
        }

        $permission->delete();

        return to_route('permissions.index')->with('success', 'Permission removed.');
    }
}