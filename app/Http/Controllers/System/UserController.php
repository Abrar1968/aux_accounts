<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\StoreUserRequest;
use App\Http\Requests\System\UpdateRoleRequest;
use App\Http\Requests\System\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\System\UserService;
use App\Support\PermissionCatalog;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(): Response
    {
        return Inertia::render('System/Users', ['users' => UserResource::collection($this->users->list()), 'roles' => $this->users->roles()]);
    }

    public function store(StoreUserRequest $request)
    {
        $u = $this->users->create($request->validated());

        return $this->toast("{$u->name} can now sign in.");
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->users->update($user, $request->validated(), $request->user());

        return $this->toast("{$user->name} updated.");
    }

    public function roles(): Response
    {
        return Inertia::render('System/Roles', ['roles' => $this->users->roles(), 'catalog' => PermissionCatalog::forUi()]);
    }

    public function updateRole(UpdateRoleRequest $request, Role $role)
    {
        $this->users->updateRole($role, $request->validated('permissions'));

        return $this->toast("Role {$role->label} updated.");
    }
}
