<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupShare;
use App\Models\User;
use phpDocumentor\Reflection\Types\Null_;

class GroupService
{
    /**
     * Get all groups (excluding trash group with id = 0)
     */
    public function getAllGroups(array $filters = [])
    {
        $user = auth()->user();

        $query = Group::query()->with(['creator:id,email,display_name']);;

        if (!$user->isAdmin()) {
            $query->where('created_by', $user->id)
            ->orWhereHas('shares', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orWhere('id', '00000000-0000-0000-0000-000000000000')
            ->distinct();
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $query->orderBy('sort_order');

        $perPage = $filters['per_page'] ?? 30;
        $page = $filters['page'] ?? null;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getGroupById($id, $includeShareUsers = false)
    {
        $user = auth()->user();
        $group = Group::find($id);
        if($group->id != '00000000-0000-0000-0000-000000000000') {
            if(!$this->canAccessGroup($id, $user, [GroupShare::ROLE_FULL, GroupShare::ROLE_EDIT, GroupShare::ROLE_VIEW])) {
                return null;
            }
        }

        $group = Group::find($id);

        if ($includeShareUsers) {
            $group->share_users = $this->getGroupShareUsers($id);
        }
        return $group;
    }

    /**
     * Create a new group
     *
     * @param string $name
     * @param int $order
     * @param string $userId
     * @return Group
     */
    public function createGroup(string $name, int $order, string $userId)
    {
        $group = new Group();
        $group->name = $name;
        $group->sort_order = $order;
        $group->created_by = $userId;
        $group->save();

        return $group;
    }

    /**
     * Update a group
     *
     * @param int $id
     * @param string $name
     * @param int $order
     * @param int $updatedBy
     * @return Group|null
     */
    public function updateGroup(string $id, string $name, int $order, string $updatedBy)
    {
        if($id == '00000000-0000-0000-0000-000000000000') {
            return null;
        }

        $user = auth()->user();

        $group = Group::find($id);

        if(!$this->canAccessGroup($id, $user, [GroupShare::ROLE_FULL, GroupShare::ROLE_EDIT])) {
            return null;
        }

        if ($group == null) {
            return null;
        }

        $group->name = $name;
        $group->sort_order = $order;
        $group->updated_by = $updatedBy;
        $group->save();

        return $group;
    }

    /**
     * Delete a group
     *
     * @param int $id
     * @return array
     */
    public function deleteGroup(string $id)
    {
        if($id == '00000000-0000-0000-0000-000000000000') {
            return ['success' => false, 'message' => 'cannot_delete_all_group'];
        }

        $user = auth()->user();

        $group = Group::find($id);

        if ($group == null) {
            return ['success' => false, 'message' => 'group_not_found'];
        }

        if(!$this->canAccessGroup($id, $user, [GroupShare::ROLE_FULL])) {
            return ['success' => false, 'message' => 'not_have_permission'];
        }

        if ($group->profiles->count() > 0) {
            return ['success' => false, 'message' => 'cannot_delete_group_with_profiles'];
        }

        $group->delete();

        return ['success' => true, 'message' => 'group_deleted'];
    }

    /**
     * Get total count of groups
     *
     * @return int
     */
    public function getTotalGroups()
    {
        // return Group::count();
        $user = auth()->user();
        $userId = $user->id;
        if($user->isAdmin()) {
            return Group::count();
        }
        $createdGroups = Group::where('created_by', $userId)->orWhere('id', '00000000-0000-0000-0000-000000000000')->get();
        $sharedGroups = Group::whereHas('shares', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get();

        $allGroups = $createdGroups->merge($sharedGroups)->unique('id');
        return $allGroups->count();
    }

    /**
     * Get group shares for a specific group
     *
     * @param int $groupId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getGroupShareUsers(string $groupId, $paginate = false)
    {
        $currentUser = auth()->user();
        if ($groupId == '00000000-0000-0000-0000-000000000000' || !$this->canAccessGroup($groupId, $currentUser, [GroupShare::ROLE_FULL, GroupShare::ROLE_EDIT, GroupShare::ROLE_VIEW])) {
            if($paginate) {
                return [];
            }
            return null;
        }

        $query = GroupShare::join('users', 'group_shares.user_id', '=', 'users.id')
        ->where('group_shares.group_id', $groupId)
        ->select('users.id', 'users.display_name', 'users.email', 'group_shares.role');

        if ($paginate) {
            return $query->paginate(20);
        }

        return $query->get();
    }

    /**
     * Share group with user
     *
     * @param int $groupId
     * @param int $userId
     * @param string $role
     * @param User $currentUser
     * @return array
     */
    public function shareGroup(string $groupId, string $userId, string $role, User $currentUser)
    {
        if($groupId == '00000000-0000-0000-0000-000000000000') {
            return ['success' => false, 'message' => 'cannot_share_all_group'];
        }

        // Validate shared user
        $sharedUser = User::find($userId);
        if ($sharedUser == null) {
            return ['success' => false, 'message' => 'user_not_found'];
        }

        if ($sharedUser->isAdmin()) {
            return ['success' => false, 'message' => 'no_need_set_admin_permission'];
        }

        // Validate group
        $group = Group::find($groupId);
        if ($group == null) {
            return ['success' => false, 'message' => 'group_not_found'];
        }

        // Check permission
        if (!$this->canAccessGroup($groupId, $currentUser, [GroupShare::ROLE_FULL])) {
            return ['success' => false, 'message' => 'not_have_permission'];
        }

        // Handle group share
        $groupShare = GroupShare::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->first();

        // If role is empty or invalid, remove the share
        if (empty($role) || !in_array($role, [GroupShare::ROLE_FULL, GroupShare::ROLE_EDIT, GroupShare::ROLE_VIEW])) {
            if ($groupShare != null) {
                $groupShare->delete();
            }
            return ['success' => true, 'message' => 'ok'];
        }

        // Create or update share
        if ($groupShare == null) {
            $groupShare = new GroupShare();
        }

        $groupShare->group_id = $groupId;
        $groupShare->user_id = $userId;
        $groupShare->role = $role;
        $groupShare->save();

        return ['success' => true, 'message' => 'ok'];
    }

    public function removeShareGroup(string $groupId, string $userId)
    {
        $currentUser = auth()->user();

        $groupShare = GroupShare::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->first();

        if ($groupShare == null) {
            return ['success' => false, 'message' => 'share_not_found'];
        }

        // Check permission
        if (!$this->canAccessGroup($groupId, $currentUser, [GroupShare::ROLE_FULL])) {
            return ['success' => false, 'message' => 'not_have_permission'];
        }

        $groupShare->delete();

        return ['success' => true, 'message' => 'ok'];
    }

    /**
     * Check if user has admin permission
     *
     * @param User $user
     * @return bool
     */
    public function hasAdminPermission(User $user)
    {
        return $user->isAdmin();
    }

    /**
     * Check if user can access group
     *
     * @param int $groupId
     * @param User $user
     * @return bool
     */
    public function canAccessGroup(string $groupId, User $user, array $allowRoles)
    {
        if ($user->isAdmin()) {
            return true;
        }

        $group = Group::find($groupId);
        if (!$group) {
            return false;
        }

        // Check if user is the creator
        if ($group->created_by == $user->id) {
            return true;
        }

        // Check group shares
        $groupShare = GroupShare::where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->first();

        return $groupShare !== null && in_array($groupShare->role, $allowRoles);
    }

    /**
     * Check if user can modify group
     *
     * @param int $groupId
     * @param User $user
     * @return bool
     */
    public function canModifyGroup(string $groupId, User $user)
    {
        if ($user->isAdmin()) {
            return true;
        }

        $group = Group::find($groupId);
        if (!$group) {
            return false;
        }

        // Check if user is the creator
        if ($group->created_by == $user->id) {
            return true;
        }

        // Check group shares with FULL access
        $groupShare = GroupShare::where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->where('role', GroupShare::ROLE_FULL)
            ->first();

        return $groupShare !== null;
    }

    /**
     * Find group by ID
     *
     * @param int $id
     * @return Group|null
     */
    public function findGroup(string $id)
    {
        return Group::find($id);
    }
}
