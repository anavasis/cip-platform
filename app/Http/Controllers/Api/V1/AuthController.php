<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'create_personal_org' => ['sometimes', 'boolean'],
            'organization_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $result = $this->auth->register(
            $validated['name'],
            $validated['email'],
            $validated['password'],
            $validated['create_personal_org'] ?? true,
            $validated['organization_name'] ?? null,
        );

        return response()->json([
            'data' => [
                'user' => $result['user'],
                'token' => $result['token'],
                'organization' => $result['organization'],
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->auth->login($validated['email'], $validated['password']);

        return response()->json([
            'data' => [
                'user' => $result['user'],
                'token' => $result['token'],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user(), $request->bearerToken());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }
}
