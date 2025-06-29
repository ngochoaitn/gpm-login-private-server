<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Services\UserService;

class UserController extends BaseController
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        $exact = filter_var($request->query('exact', false), FILTER_VALIDATE_BOOLEAN);
        $filters = [
            'search' => $request->search ?? null,
            'per_page' => $request->per_page ?? 10,
            'page' => $request->page ?? 1,
            'exact' => $exact
        ];

        $users = $this->userService->getUsers($filters);
        return $this->getJsonResponse(true, 'success', $users);
    }

    public function store(Request $request)
    {
        $result = $this->userService->createUser(
            $request->email ?? $request->user_name,
            $request->display_name,
            $request->password
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }


    public function update(Request $request)
    {
        $result = $this->userService->updateUser(
            $request->user()->id,
            $request->display_name,
            $request->system_role ?? null,
            $request->new_password ?? null
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    /**
     * Get current user
     */
    public function getCurrentUser(Request $request)
    {
        $user = $request->user();
        return $this->getJsonResponse(true, 'OK', $user);
    }
}
