<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Users\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\OperatorResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Operator, UserRole::Supervisor])
            ->orderBy('name')
            ->get();

        return OperatorResource::collection($users)->response();
    }
}
