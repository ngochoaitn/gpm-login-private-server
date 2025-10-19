<?php

namespace App\Services;

use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WebAuthService
{
    /**
     * Authenticate admin user for web interface
     *
     * @param string $email
     * @param string $password
     * @return array
     */
    public function login(string $email, string $password)
    {
        $this->createDefaultAdmin();
        $this->createDefaultGroup();

        // Find active user by email
        $user = User::where('email', $email)
            ->where('is_active', true)
            ->first();

        // Check password has not been hashed (Old version)
        if ($user != null && !$this->isHashed($user->password) && $user->password == $password) {
            $user->password = Hash::make($password);
            $user->save();
        }
        // Check if user exists and password is correct
        else if ($user == null || !Hash::check($password, $user->password)) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }

        // Only allow admin and moderator access to web interface
        if (!$user->hasModeratorAccess()) {
            return ['success' => false, 'message' => 'Access denied. Admin or Moderator privileges required.'];
        }

        Auth::login($user);
        return ['success' => true, 'message' => 'ok'];
    }

    function isHashed($password)
    {
        return Str::startsWith($password, '$2y$') && strlen($password) === 60;
    }

    public function createDefaultAdmin()
    {
        try {
            if (User::count() > 0) {
                return true;
            }

            User::create([
                'display_name' => 'Admin',
                'email' => 'Administrator',
                'is_active' => true,
                'system_role' => 'ADMIN',
                'password' => Hash::make('Administrator')
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createDefaultGroup()
    {
        try {
            if (Group::count() > 0) {
                return true;
            }

            Group::create([
                'id' => '00000000-0000-0000-0000-000000000000',
                'name' => 'Default group',
                'sort_order' => 0,
                'created_by' => User::first()->id
            ]);

            return true;
        } catch (\Exception $e) {
            die($e->getMessage());
            return false;
        }
    }

    /**
     * Logout user
     *
     * @return void
     */
    public function logout()
    {
        Auth::logout();
    }
}