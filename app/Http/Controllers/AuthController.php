<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request) {
        $user = User::where('role', 2)->where('user_name', $request->username)->where('password', $request->password)
                ->where('active', '<>', 0)->first();

        if ($user == null)
            return redirect()->back()->with('error', 'Login failed');

        Auth::login($user);
        return redirect('/admin');
    }

    public function logout(){
        Auth::logout();
        return redirect('/admin/auth');
    }
}
