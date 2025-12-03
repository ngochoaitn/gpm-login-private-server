<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupRole;
use App\Models\User;

class GroupController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $groups = Group::where('id', '!=', 0)->orderBy('sort')->get(); // 23.7.2024 0 is trash
        return $this->getJsonResponse(true, 'Thành công', $groups);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = new Group();
        $group->name = $request->name;
        $group->sort = $request->sort;
        $group->created_by = $user->id;
        $group->save();

        return $this->getJsonResponse(true, 'Thành công', $group);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = Group::find($id);

        if ($group == null)
            return $this->getJsonResponse(false, 'Group không tồn tại', null);

        $group->name = $request->name;
        $group->sort = $request->sort;
        $group->save();

        return $this->getJsonResponse(true, 'Cập nhật thành công', null);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        if ($user->role < 2)
            return $this->getJsonResponse(false, 'Không đủ quyền. Bạn cần có quyền admin để sử dụng tính năng này!', null);

        $group = Group::find($id);
        if ($group == null)
            return $this->getJsonResponse(false, 'Group không tồn tại!', null);

        if ($group->profiles->count() > 0)
            return $this->getJsonResponse(false, 'Không thể xóa Group có liên kết với Profiles!', null);

        $group->delete();

        return $this->getJsonResponse(true, 'Xóa thành công', null);
    }

    /**
     * Get total profile
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotal()
    {
        $total = Group::count();
        return $this->getJsonResponse(true, 'OK', ['total' => $total]);
    }

    /**
     * Get list of users role
     */
    public function getGroupRoles($id)
    {
        $groupRoles = GroupRole::where('group_id', $id)
                            ->with(['group', 'user'])->get();
        return $this->getJsonResponse(true, 'OK', $groupRoles);
    }

    public function share($id, Request $request)
    {
        // Validate input
        $user = $request->user();

        $sharedUser = User::find($request->user_id);
        if ($sharedUser == null)
            return $this->getJsonResponse(false, 'User ID không tồn tại', null);

        if ($sharedUser->role == 2)
            return $this->getJsonResponse(false, 'Không cần set quyền cho Admin', null);

        $group = Group::find($id);
        if ($group == null)
            return $this->getJsonResponse(false, 'Profile không tồn tại', null);

        if ($user->role != 2 && $group->created_by != $user->id)
            return $this->getJsonResponse(false, 'Bạn phải là người tạo group', null);

        // Handing data
        $groupRole = GroupRole::where('group_id', $id)->where('user_id', $request->user_id)->first();

        // If role = 0, remove in GroupRole
        if ($request->role == 0){
            if ($groupRole != null)
                $groupRole->delete();

            return $this->getJsonResponse(true, 'OK', null);
        }

        if ($groupRole == null)
            $groupRole = new GroupRole();

        // Share
        $groupRole->group_id = $id;
        $groupRole->user_id = $request->user_id;
        $groupRole->role = $request->role;
        $groupRole->save();

        return $this->getJsonResponse(true, 'OK', null);
    }
}
