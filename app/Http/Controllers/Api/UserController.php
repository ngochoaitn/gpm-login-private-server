<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $users = User::orderBy('role', 'desc')->orderBy('user_name')->get();
        return $this->getJsonResponse(true, 'Thành công', $users);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (User::where('user_name', strtolower($request->user_name))->count() > 0){
            return $this->getJsonResponse(false, 'Tên người dùng đã tồn tại', null);
        }

        $user = new User();
        $user->user_name = strtolower($request->user_name);
        $user->display_name = $request->display_name;
        $user->password = $request->password;
        $user->role = 1;
        $user->active = 0;
        $user->save();


        return $this->getJsonResponse(true, 'Đăng kí thành công', $user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $user = User::find($request->user()->id);
        $user->display_name = $request->display_name;

        if (isset($request->role))
            $user->role = $request->role;

        if (isset($request->new_password))
            $user->password = $request->new_password;

        $user->save();

        return $this->getJsonResponse(true, 'Đổi thông tin thành công', $user);
    }

    /**
     * Get current user
     */
    public function getCurrentUser(Request $request){
        $user = $request->user();
        return $this->getJsonResponse(true, 'OK', $user);
    }
}
