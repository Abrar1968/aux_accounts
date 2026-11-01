<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\UpdateProfileRequest;
use App\Services\System\UserService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', ['user' => $request->user()->only('id', 'name', 'email')]);
    }

    public function update(UpdateProfileRequest $request, UserService $users)
    {
        $users->update($request->user(), array_filter($request->only('name', 'password')), $request->user());

        return $this->toast('Profile updated.');
    }
}
