<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Models\Profile;
use App\Models\Group;
use App\Models\GroupRole;
use App\Models\ProfileRole;
use App\Models\User;
use Carbon\Carbon;

class ProfileController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Default, show all profiles
        $tmp = Profile::with(['createdUser', 'lastRunUser', 'group']);

        // If user isn't admin, show by role
        if ($user->role < 2){
            $ids_group_share = DB::table('group_roles')->where('user_id', $user->id)->pluck('group_id');

            // $ids = DB::table('profiles')
            //     ->join('profile_roles', 'profiles.id', '=', 'profile_roles.profile_id')
            //     ->where('profile_roles.user_id', $user->id)
            //     ->select('profiles.id')->get();

            $ids = DB::table('profiles')
            ->join('profile_roles', 'profiles.id', '=', 'profile_roles.profile_id')
            ->where(function($query) use ($user, $ids_group_share) {
                $query->where('profile_roles.user_id', $user->id)
                      ->orWhereIn('profiles.group_id', $ids_group_share);
            })
            ->select('profiles.id')->get();
            
            $arrIds = [];
            foreach ($ids as $id){
                array_push($arrIds, $id->id);
            }

            $tmp = Profile::whereIntegerInRaw('id', $arrIds)->with(['createdUser', 'lastRunUser', 'group']);
        }

        // Order by group
        if (isset($request->group_id) && $request->group_id != Group::where('name', 'All')->first()->id)
            $tmp = $tmp->where('group_id', $request->group_id);
        else
            $tmp = $tmp->where('group_id', '!=', 0); // 23.7.2024 trash

        // Search
        if (isset($request->search)) {
            if (!str_contains($request->search, 'author:'))
                $tmp = $tmp->where('name', 'like', "%$request->search%");
            else {
                $authorName = str_replace('author:', '', $request->search);
                $createdUser = User::where('display_name', $authorName)->first();
                if ($createdUser != null) {
                    $tmp = $tmp->where('created_by', $createdUser->id);
                }
            }
        }

        // Filter
        $shareMode = 1;

        if (isset($request->share_mode)){
            $shareMode = $request->share_mode;
            if ($shareMode == 1) // No share
                $tmp = $tmp->where('created_by', $user->id);
            else
                $tmp = $tmp->where('created_by', '!=', $user->id);
        }

        // Filter by tag
        if (isset($request->tags)){
            $tags = explode(",", $request->tags);
            foreach ($tags as $tag) {
                if ($tag == $tags[0])
                    $tmp = $tmp->whereJsonContains('json_data->Tags', $tag);
                else
                    $tmp = $tmp->orWhereJsonContains('json_data->Tags', $tag);
            }
        }

        // Sort
        if (isset($request->sort)){
            if ($request->sort == 'created')
                $tmp = $tmp->orderBy('created_at');
            else if ($request->sort == 'created_at_desc')
                $tmp = $tmp->orderBy('created_at', 'desc');
            else if ($request->sort == 'name')
                $tmp = $tmp->orderBy('name');
            else if ($request->sort == 'name_desc')
                $tmp = $tmp->orderBy('name', 'desc');
        }

        // Pagination
        $perPage = 30;
        if (isset($request->per_page))
            $perPage = $request->per_page;

        $profiles = $tmp->paginate($perPage);
        return $this->getJsonResponse(true, 'OK', $profiles);
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

        $profile = new Profile();
        $profile->name = $request->name;
        $profile->s3_path = $request->s3_path;
        $profile->json_data = $request->json_data;
        $profile->cookie_data = '[]';
        if (isset($request->cookie_data))
            $profile->cookie_data = $request->cookie_data;
        $profile->group_id = $request->group_id;
        $profile->created_by = $user->id;
        $profile->status = 1;
        $profile->last_run_at = null;
        $profile->last_run_by = null;
        $profile->save();

        $profileRole = new ProfileRole();
        $profileRole->profile_id = $profile->id;
        $profileRole->user_id = $user->id;
        $profileRole->role = 2;
        $profileRole->save();

        $result = Profile::where('id', $profile->id)->with(['createdUser', 'lastRunUser', 'group'])->first();

        return $this->getJsonResponse(true, 'Thành công', $result);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        // Check condition
        $canAccess = $this->canAccessProfile($id, $user);

        if (!$canAccess)
            return $this->getJsonResponse(false, 'Không đủ quyền với profile', null);

        // Get profile
        $profile = Profile::find($id);
        if ($profile == null)
            return $this->getJsonResponse(false, 'Profile không tồn tại', null);

        return $this->getJsonResponse(true, "Thành công", $profile);
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

        // Check condition
        $canEdit = $this->canModifyProfile($id, $user);

        if (!$canEdit)
            return $this->getJsonResponse(false, 'Không đủ quyền sửa profile', null);

        // Edit on db
        $profile = Profile::find($id);
        if ($profile == null)
            return $this->getJsonResponse(false, 'Profile không tồn tại', null);

        $profile->name = $request->name;
        $profile->s3_path = $request->s3_path;
        $profile->json_data = $request->json_data;
        $profile->cookie_data = $request->cookie_data;
        $profile->group_id = $request->group_id;
        $profile->last_run_at = $request->last_run_at;
        $profile->last_run_by = $request->last_run_by;

        $profile->save();

        return $this->getJsonResponse(true, 'OK', null);
    }

    /**
     * Update status
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus($id, Request $request)
    {
        $user = $request->user();

        // Check condition
        $canAccess = $this->canAccessProfile($id, $user);

        if (!$canAccess)
            return $this->getJsonResponse(false, 'Không đủ quyền update trạng thái profile', null);

        // Edit on db
        $profile = Profile::find($id);
        if ($profile == null)
            return $this->getJsonResponse(false, 'Profile không tồn tại', null);

        $profile->status = $request->status;

        // If user run profile, update last run data
        if ($request->status == 2){
            $profile->last_run_at = Carbon::now();
            $profile->last_run_by = $user->id;
        }

        $profile->save();

        return $this->getJsonResponse(true, 'Thành công', null);
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

        // Check condition
        $canDelete = $this->canModifyProfile($id, $user);

        if (!$canDelete)
            return $this->getJsonResponse(false, 'Không đủ quyền xóa profile', null);

        // Delete on db
        $profile = Profile::find($id);
        if ($profile == null)
            return $this->getJsonResponse(false, 'Profile không tồn tại', null);

        $profileRoles = ProfileRole::where('profile_id', $id);
        $profileRoles->delete();
        $profile->delete();

        return $this->getJsonResponse(true, 'Xóa thành công', null);
    }

    /**
     * Get list of users role
     */
    public function getProfileRoles($id)
    {
        $profileRoles = ProfileRole::where('profile_id', $id)
                            ->with(['profile', 'user'])->get();
        return $this->getJsonResponse(true, 'OK', $profileRoles);
    }

    /**
     * Share profile
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function share($id, Request $request)
    {
        // Validate input
        $user = $request->user();

        $sharedUser = User::find($request->user_id);
        if ($sharedUser == null)
            return $this->getJsonResponse(false, 'User ID không tồn tại', null);

        if ($sharedUser->role == 2)
            return $this->getJsonResponse(false, 'Không cần set quyền cho Admin', null);

        $profile = Profile::find($id);
        if ($profile == null)
            return $this->getJsonResponse(false, 'Profile không tồn tại', null);

        if ($user->role != 2 && $profile->created_by != $user->id)
            return $this->getJsonResponse(false, 'Bạn phải là người tạo profile', null);

        // Handing data
        $profileRole = ProfileRole::where('profile_id', $id)->where('user_id', $request->user_id)->first();

        // If role = 0, remove in ProfileRole
        if ($request->role == 0){
            if ($profileRole != null)
                $profileRole->delete();

            return $this->getJsonResponse(true, 'OK', null);
        }

        if ($profileRole == null)
            $profileRole = new ProfileRole();

        // Share
        $profileRole->profile_id = $id;
        $profileRole->user_id = $request->user_id;
        $profileRole->role = $request->role;
        $profileRole->save();

        return $this->getJsonResponse(true, 'OK', null);
    }

    /**
     * Get total profile
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotal()
    {
        $total = Profile::count();
        return $this->getJsonResponse(true, 'OK', ['total' => $total]);
    }

    /**
     * Check profile permisson
     *
     * @return bool $canModify
     */
    private function canModifyProfile($profileId, $logonUser)
    {
        $canModify = true;

        if ($logonUser->role < 2) {
            $profileRole = ProfileRole::where('user_id', $logonUser->id)->where('profile_id', $profileId)->first();
            $canModify = $profileRole != null && $profileRole->role == 2;

            if ($canModify == false){
                $profile = Profile::find($profileId);
                if($profile != null) {
                    $groupRole = GroupRole::where('user_id', $logonUser->id)->where('group_id', $profile->group_id)->first();
                    $canModify = $groupRole != null && $groupRole->role == 2;
                }
            }
        }

        return $canModify;
    }

    /**
     * Check profile permisson
     *
     * @return bool $canModify
     */
    private function canAccessProfile($profileId, $logonUser)
    {
        $canAccess = true;

        if ($logonUser->role < 2){
            $profileRole = ProfileRole::where('user_id', $logonUser->id)->where('profile_id', $profileId)->first();
            $canAccess = ($profileRole != null);

            if ($canAccess == false){
                $profile = Profile::find($profileId);
                if($profile != null) {
                    $groupRole = GroupRole::where('user_id', $logonUser->id)->where('group_id', $profile->group_id)->first();
                    $canAccess = $groupRole != null;
                }
            }
        }

        return $canAccess;
    }
}
