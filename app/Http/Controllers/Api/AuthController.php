<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends BaseController
{
    public function login(Request $request){
        $user = User::where('user_name', strtolower($request->user_name))
            ->where('password', $request->password)->where('active', '<>', 0)->first();

        if ($user == null){
            return $this->getJsonResponse(false, 'Đăng nhập thất bại', null);
        }

        // Remove all tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('token');
        $resp = ['token' => $token->plainTextToken];

        return $this->getJsonResponse(true, 'Đăng nhập thành công', $resp);
    }
}
