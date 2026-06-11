<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->isActiveAdmin()) {
            throw ValidationException::withMessages(['email' => ['Invalid admin credentials.']]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $user->createToken('admin-api')->plainTextToken,
            'user' => [
                ...$user->only(['id', 'name', 'email', 'role', 'last_login_at']),
                'permissions' => $user->permissions(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => [
                ...$request->user()->only(['id', 'name', 'email', 'role', 'last_login_at']),
                'permissions' => $request->user()->permissions(),
            ],
        ]);
    }
}
