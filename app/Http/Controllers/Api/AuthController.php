<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CreateStaffRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'name' => $validated['business_name'],
                'slug' => Str::slug($validated['business_name']).'-'.Str::random(6),
                'is_active' => true,
            ]);

            $user = User::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::OWNER,
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => $user,
                'tenant' => $tenant,
                'token' => $token,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => new UserResource($result['user']),
                'tenant' => [
                    'id' => $result['tenant']->id,
                    'name' => $result['tenant']->name,
                    'slug' => $result['tenant']->slug,
                ],
                'token' => $result['token'],
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $request->header('X-Tenant-ID');

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'X-Tenant-ID header is required',
            ], 400);
        }

        $tenant = Tenant::where('id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive tenant',
            ], 403);
        }

        $user = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => new UserResource($request->user()),
            ],
        ]);
    }

    public function createStaff(CreateStaffRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::STAFF,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff user created successfully',
            'data' => [
                'user' => new UserResource($user),
            ],
        ], 201);
    }

    public function listStaff(): JsonResponse
    {
        $staff = User::where('role', UserRole::STAFF)->paginate(15);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($staff),
            'meta' => [
                'current_page' => $staff->currentPage(),
                'last_page' => $staff->lastPage(),
                'per_page' => $staff->perPage(),
                'total' => $staff->total(),
            ],
        ]);
    }

    public function deleteStaff(int $id): JsonResponse
    {
        $staff = User::where('role', UserRole::STAFF)->findOrFail($id);
        $staff->tokens()->delete();
        $staff->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff user deleted successfully',
        ]);
    }
}
