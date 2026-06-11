<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function isLastActiveSuperAdmin(User $user): bool
    {
        return $user->role === User::ROLE_SUPER_ADMIN
            && ! $user->disabled_at
            && User::where('role', User::ROLE_SUPER_ADMIN)
                ->whereNull('disabled_at')
                ->whereKeyNot($user->id)
                ->doesntExist();
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('users.view'), 403);

        return AdminUserResource::collection(
            User::with('creator:id,name,email')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'user' => new AdminUserResource($user->load('creator:id,name,email')),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.update'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        abort_if(
            $this->isLastActiveSuperAdmin($user) && $data['role'] !== User::ROLE_SUPER_ADMIN,
            422,
            'You cannot change the role of the last active super admin.'
        );

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return response()->json([
            'user' => new AdminUserResource($user->load('creator:id,name,email')),
        ]);
    }

    public function disable(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.disable'), 403);
        abort_if($request->user()->is($user), 422, 'You cannot disable your own account.');
        abort_if(
            $this->isLastActiveSuperAdmin($user),
            422,
            'You cannot disable the last active super admin.'
        );

        $user->forceFill(['disabled_at' => now()])->save();
        $user->tokens()->delete();

        return response()->json([
            'user' => new AdminUserResource($user->load('creator:id,name,email')),
        ]);
    }

    public function enable(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.disable'), 403);

        $user->forceFill(['disabled_at' => null])->save();

        return response()->json([
            'user' => new AdminUserResource($user->load('creator:id,name,email')),
        ]);
    }
}
