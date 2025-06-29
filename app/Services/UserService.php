<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Get all users ordered by system_role and email
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllUsers()
    {
        return User::orderByRaw("
            CASE system_role
                WHEN 'ADMIN' THEN 3
                WHEN 'MOD' THEN 2
                WHEN 'USER' THEN 1
                ELSE 0
            END DESC
        ")->orderBy('email')->get();
    }

    /**
     * Get users with pagination and search
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUsers(array $filters = [])
    {
        $query = User::select('id', 'email', 'display_name');

        // Apply search filter
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $exact = $filters['exact'] ?? false;
            if ($exact==true) {
                $query->where('email', $search);
            }
            else {
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%");
                });
            }
        }

        // Apply active status filter
        $query->where('is_active', true);

        $query->orderBy('email');

        $perPage = $filters['per_page'] ?? 30;
        $page = $filters['page'] ?? null;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Create a new user
     *
     * @param string $email
     * @param string $displayName
     * @param string $password
     * @param string $systemRole
     * @return array
     */
    public function createUser(string $email, string $displayName, string $password, string $systemRole = User::ROLE_USER)
    {
        // Check if email already exists
        if (User::where('email', strtolower($email))->count() > 0) {
            return ['success' => false, 'message' => 'AccountExisted', 'data' => null];
        }

        $user = new User();
        $user->email = strtolower($email);
        $user->display_name = $displayName;
        $user->password = Hash::make($password);
        $user->system_role = $systemRole;
        $user->is_active = false; // New users are inactive until activated
        $user->save();

        return ['success' => true, 'message' => 'RegisterSuccessfully', 'data' => $user];
    }

    /**
     * Update user information
     *
     * @param int $userId
     * @param string $displayName
     * @param string|null $systemRole
     * @param string|null $newPassword
     * @param bool|null $isActive
     * @return array
     */
    public function updateUser(string $userId, string $displayName, ?string $systemRole = null, ?string $newPassword = null, ?bool $isActive = null)
    {
        $user = User::find($userId);
        $loginUser = auth()->user();

        if (!$user) {
            return ['success' => false, 'message' => 'user_not_found', 'data' => null];
        }

        $user->display_name = $displayName;

        if ($loginUser->isAdmin() && $systemRole !== null && in_array($systemRole, [User::ROLE_ADMIN, User::ROLE_MOD, User::ROLE_USER])) {
            $user->system_role = $systemRole;
        }

        if ($newPassword !== null) {
            $user->password = Hash::make($newPassword);
        }

        if ($isActive !== null) {
            $user->is_active = $isActive;
        }

        $user->save();

        return ['success' => true, 'message' => 'ok', 'data' => $user];
    }

    /**
     * Get user by ID
     *
     * @param int $userId
     * @return User|null
     */
    public function getUserById(string $userId)
    {
        return User::find($userId);
    }
}
