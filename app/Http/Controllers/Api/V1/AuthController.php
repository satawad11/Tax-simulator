<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EmptyMemberActionRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\AuthTokenResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, AuthService $service): JsonResponse
    {
        return (new AuthTokenResource($service->register($request->validated())))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request, AuthService $service): AuthTokenResource
    {
        return new AuthTokenResource($service->login($request->validated()));
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(UpdateProfileRequest $request, AuthService $service): UserResource
    {
        return new UserResource($service->update($request->user(), $request->validated()));
    }

    public function logout(EmptyMemberActionRequest $request, AuthService $service): Response
    {
        $service->logout($request->user(), false);

        return response()->noContent();
    }

    public function logoutAll(EmptyMemberActionRequest $request, AuthService $service): Response
    {
        $service->logout($request->user(), true);

        return response()->noContent();
    }
}
