<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Group;
use App\Models\GroupShare;
use App\Models\ProfileShare;
use App\Models\User;
use App\Models\Tag;
use App\Services\TagService;
use App\Services\UploadService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProfileService
{
    protected TagService $tagService;
    protected UploadService $uploadService;

    public function __construct(TagService $tagService, UploadService $uploadService)
    {
        $this->tagService = $tagService;
        $this->uploadService = $uploadService;
    }

    /**
     * Get profiles with filters and pagination
     *
     * @param User $user
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProfiles(User $user, array $filters = [], array $extensiveFields = [])
    {
        $selectFields = ['id', 'name', 'storage_path', 'meta_data', 'group_id', 'created_by', 'status', 'using_by', 'last_run_at', 'last_run_by', 'created_at', 'updated_at', 'dynamic_data'];
        // Add extensive fields if provided, avoid duplicates
        if (count($extensiveFields) > 0) {
            foreach ($extensiveFields as $field) {
                if (!in_array($field, $selectFields)) {
                    $selectFields[] = $field;
                }
            }
        }

        // Default, show all active profiles (not soft deleted)
        if(isset($filters['is_deleted']) && $filters['is_deleted'] == 1)
            $query = Profile::intrashed();
        else
            $query = Profile::active();

        $query = $query
            ->select($selectFields)
            ->with([
                'creator:id,email,display_name',
                'lastRunUser:id,email,display_name',
                'currentUser:id,email,display_name',
                'group:id,name',
                // 'tags:id,name,color,category',
                'tags' => function ($q) {
                    $q->select('tags.id', 'name', 'color', 'category')
                    ->orderBy('profile_tags.created_at');
                }
        ]);
        // TODO: sắp xếp tags theo created_at

        // If user isn't admin, show by permissions
        if (!$user->isAdmin()) {
            $groupShareIds = DB::table('group_shares')->where('user_id', $user->id)->pluck('group_id');
            $profileShareIds = DB::table('profile_shares')->where('user_id', $user->id)->pluck('profile_id');

            if(isset($filters['is_deleted']) && $filters['is_deleted'] == 1)
                $query = Profile::intrashed();
            else
                $query = Profile::active();

            $query = $query->select($selectFields)
                ->where(function ($q) use ($user, $groupShareIds, $profileShareIds) {
                    $q->where('created_by', $user->id)
                        ->orWhereIn('group_id', $groupShareIds)
                        ->orWhereIn('id', $profileShareIds);
                })
                ->with(['creator:id,email,display_name',  'currentUser:id,email,display_name', 'lastRunUser:id,email,display_name', 'group:id,name', 'tags:id,name,color,category']);
        }

        // Apply filters
        $this->applyFilters($query, $user, $filters);

        // Pagination
        $perPage = $filters['per_page'] ?? 30;
        $page = $filters['page'] ?? null;
        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Apply filters to query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param User $user
     * @param array $filters
     */
    private function applyFilters($query, User $user, array $filters)
    {
        // Filter by ID
        if (isset($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        // Filter by group
        if (isset($filters['group_id']) && $filters['group_id'] != '00000000-0000-0000-0000-000000000000') {
            $query->where('group_id', $filters['group_id']);
        }

        // Search
        if (isset($filters['search'])) {
            if (str_contains($filters['search'], 'author:')) {
                $authorName = str_replace('author:', '', $filters['search']);
                $createdUser = User::where('display_name', $authorName)->first();
                if ($createdUser != null) {
                    $query->where('created_by', $createdUser->id);
                }
            } else {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', "%{$filters['search']}%");
                    $authorUser = User::where('display_name', $filters['search'])->first();
                    if ($authorUser) {
                        $q->orWhere('created_by', $authorUser->id);
                    }
                });
            }
        }

        // Share mode filter
        if (isset($filters['share_mode'])) {
            if ($filters['share_mode'] == 1) { // No share
                $query->where('created_by', $user->id);
            } else {
                $query->where('created_by', '!=', $user->id);
            }
        }

        // Filter by tags
        if (isset($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : explode(",", $filters['tags']);
            $tags = array_map('trim', $tags);
        
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->where(function ($sub) use ($tags) {
                    $sub->whereIn('tags.name', $tags)
                         ->orWhereIn('tags.id', $tags); // CHỈ RÕ BẢNG!
                });
            });
        }

        // Sort
        if (isset($filters['sort'])) {
            switch ($filters['sort']) {
                case 'created':
                case 'created_asc':
                    $query->orderBy('created_at');
                    break;
                case 'created_desc':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'name':
                case 'name_asc':
                    $query->orderBy('name');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
            }
        }
    }

    /**
     * Create a new profile
     *
     * @param string $name
     * @param string $storagePath
     * @param string| null $fingerprintData
     * @param string|null $dynamicData
     * @param array|null $metaData
     * @param int $groupId
     * @param int $userId
     * @param string $storageType
     * @return Profile
     */
    public function createProfile(string $name, string $storagePath, ?string $fingerprintData, ?string $dynamicData, ?array $metaData, ?string $groupId, string $userId, string $storageType = Profile::STORAGE_S3)
    {
        if ($groupId == null) {
            $groupId = Group::where('name', 'All')->first()->id;
        }
        $profile = new Profile();
        $profile->name = $name;
        $profile->storage_type = $storageType;
        $profile->storage_path = $storagePath;
        $profile->fingerprint_data = $fingerprintData ?? null;
        $profile->dynamic_data = $dynamicData ?? null;
        $profile->meta_data = $metaData ?? [];
        $profile->group_id = $groupId;
        $profile->created_by = $userId;
        $profile->status = Profile::STATUS_READY;
        $profile->usage_count = 0;
        $profile->save();

        // Create profile share for creator with FULL access
        $profileShare = new ProfileShare();
        $profileShare->profile_id = $profile->id;
        $profileShare->user_id = $userId;
        $profileShare->role = ProfileShare::ROLE_FULL;
        $profileShare->save();

        return Profile::where('id', $profile->id)->with(['creator', 'lastRunUser', 'group'])->first();
    }

    /**
     * Get profile by ID
     *
     * @param int $id
     * @param User $user
     * @return array
     */
    public function getProfile(string $id)
    {
        $user = auth()->user();
        if (!$this->canAccessProfile($id, $user)) {
            return ['success' => false, 'message' => 'insufficient_permission_profile', 'data' => null];
        }

        $profile = Profile::active()->with([
            'creator:id,email,display_name',
            'lastRunUser:id,email,display_name',
            'group:id,name',
            'tags' => function ($q) {
                $q->select('tags.id', 'name', 'color', 'category')
                ->orderBy('profile_tags.created_at');
            }
        ])->find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'profile_not_found', 'data' => null];
        }

        return ['success' => true, 'message' => 'ok', 'data' => $profile];
    }

    /**
     * Update profile
     *
     * @param int $id
     * @param string $name
     * @param string $storagePath
     * @param array $jsonData
     * @param array $metaData
     * @param int $groupId
     * @param string|null $lastRunAt
     * @param int|null $lastRunBy
     * @param User $user
     * @return array
     */
    public function updateProfile(string $id, ?string $name, ?string $storagePath, ?string $fingerprintData, ?string $dynamicData, ?string $metaData, ?string $groupId, ?string $lastRunAt, ?string $lastRunBy, User $user)
    {
        if (!$this->canModifyProfile($id, $user)) {
            return ['success' => false, 'message' => 'insufficient_permission_profile_edit', 'data' => null];
        }

        $profile = Profile::active()->find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'profile_not_found', 'data' => null];
        }

        $profile->name             = $name            ?? $profile->name;
        $profile->storage_path     = $storagePath     ?? $profile->storage_path;
        $profile->fingerprint_data = $fingerprintData ?? $profile->fingerprint_data;
        $profile->dynamic_data     = $dynamicData     ?? $profile->dynamic_data;
        $profile->meta_data        = $metaData        ?? $profile->meta_data;
        $profile->group_id         = $groupId         ?? $profile->group_id;
        $profile->last_run_at      = $lastRunAt       ?? $profile->last_run_at;
        $profile->last_run_by      = $lastRunBy       ?? $profile->last_run_by;
        $profile->save();

        return ['success' => true, 'message' => 'ok', 'data' => null];
    }

    /**
     * Update profile status and track usage
     *
     * @param int $id
     * @param int $status
     * @param User $user
     * @return array
     */
    public function updateProfileStatus(string $id, int $status, User $user)
    {
        if (!$this->canAccessProfile($id, $user)) {
            return ['success' => false, 'message' => 'insufficient_permission_profile_status', 'data' => null];
        }

        $profile = Profile::active()->find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'profile_not_found', 'data' => null];
        }

        // If user starts using profile
        if ($status == Profile::STATUS_IN_USE) {
            $profile->markAsInUse($user);
            $profile->recordUsage($user);
        } else if ($status == Profile::STATUS_READY) {
            $profile->markAsReady();
        } else {
            $profile->status = $status;
            $profile->save();
        }

        return ['success' => true, 'message' => 'ok', 'data' => null];
    }

    /**
     * Soft delete profile
     *
     * @param int $id
     * @param User $user
     * @return array
     */
    public function deleteProfile(string $id, $delete_mode = 'soft')
    {
        $user = auth()->user();
        if (!$this->checkAccessProfile($id, $user, [ProfileShare::ROLE_FULL])) {
            return ['success' => false, 'message' => 'insufficient_permission_profile_delete', 'data' => null];
        }

        $profile = Profile::find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'profile_not_found', 'data' => null];
        }

        if($delete_mode == 'hard') {
            $this->uploadService->deleteFile($profile->storage_path);
            $profile->delete();
        } else {
            $profile->softDelete($user);
        }

        return ['success' => true, 'message' => 'profile_deleted', 'data' => null];
    }

    public function bulkDeleteProfile(array $profile_ids, $delete_mode = 'soft')
    {
        $count = 0;
        $lastError = null;
        foreach ($profile_ids as $id) {
            $result = $this->deleteProfile($id, $delete_mode);
            if ($result['success']) {
                $count++;
            } else {
                $lastError = $result['message'];
            }
        }
        return ['success' => true, 'message' => 'profile_deleted', 'data' => [
            'deleted_count' => $count,
            'total_profiles' => count(value: $profile_ids),
            'last_error' => $lastError
        ]];
    }

    /**
     * Restore soft deleted profile
     *
     * @param int $id
     * @param User $user
     * @return array
     */
    public function restoreProfile(string $id, User $user)
    {
        if (!$user->isAdmin() && !$user->isModerator()) {
            return ['success' => false, 'message' => 'insufficient_permission_profile_restore', 'data' => null];
        }

        $profile = Profile::intrashed()->find($id);
        if ($profile == null) {
            return ['success' => false, 'message' => 'profile_not_found_in_trash', 'data' => null];
        }

        $profile->restore();

        return ['success' => true, 'message' => 'profile_restored', 'data' => null];
    }

    public function bulkRestoreProfile(array $profile_ids)
    {
        $user = auth()->user();
        $count = 0; 
        $lastError = null;
        foreach ($profile_ids as $id) {
            $result = $this->restoreProfile($id, $user);
            if ($result['success']) {
                $count++;
            } else {
                $lastError = $result['message'];
            }
        }
        return ['success' => true, 'message' => 'profile_restored', 'data' => [
            'restored_count' => $count,
            'total_profiles' => count(value: $profile_ids),
            'last_error' => $lastError
        ]];
    }

    /**
     * Get profile shares
     *
     * @param int $profileId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProfileShares(string $profileId)
    {
        return ProfileShare::where('profile_id', $profileId)
            ->with(['profile', 'user'])
            ->get();
    }

    /**
     * Share profile with user
     *
     * @param int $profileId
     * @param int $userId
     * @param string $role
     * @param User $currentUser
     * @return array
     */
    public function shareProfile(string $profileId, string $userId, string $role, User $currentUser)
    {
        // Validate shared user
        $sharedUser = User::find($userId);
        if ($sharedUser == null) {
            return ['success' => false, 'message' => 'user_not_found', 'data' => null];
        }

        if ($sharedUser->isAdmin()) {
            return ['success' => false, 'message' => 'no_need_set_admin_permission', 'data' => null];
        }

        // Validate profile
        $profile = Profile::active()->find($profileId);
        if ($profile == null) {
            return ['success' => false, 'message' => 'profile_not_found', 'data' => null];
        }

        // Check permission
        if (!$this->checkAccessProfile($profileId, $currentUser, [ProfileShare::ROLE_FULL])) {
            return ['success' => false, 'message' => 'insufficient_permission_profile_share', 'data' => null];
        }
        
        // Handle profile share
        $profileShare = ProfileShare::where('profile_id', $profileId)
            ->where('user_id', $userId)
            ->first();

        // If role is empty or invalid, remove the share
        if (empty($role) || !in_array($role, [ProfileShare::ROLE_FULL, ProfileShare::ROLE_EDIT, ProfileShare::ROLE_VIEW])) {
            if ($profileShare != null) {
                $profileShare->delete();
            }
            return ['success' => true, 'message' => 'ok', 'data' => null];
        }

        // Create or update share
        if ($profileShare == null) {
            $profileShare = new ProfileShare();
        }

        $profileShare->profile_id = $profileId;
        $profileShare->user_id = $userId;
        $profileShare->role = $role;
        $profileShare->save();

        return ['success' => true, 'message' => 'ok', 'data' => null];
    }

    public function bulkShareProfile(array $profileIds, string $userId, string $role, User $currentUser)
    {
        // Validate shared user
        $sharedUser = User::find($userId);
        if ($sharedUser == null) {
            return ['success' => false, 'message' => 'user_not_found', 'data' => null];
        }

        if ($sharedUser->isAdmin()) {
            return ['success' => false, 'message' => 'no_need_set_admin_permission', 'data' => null];
        }

        // Validate profile
        $profiles = Profile::active()->whereIn('id', $profileIds)->get();

        $count = 0;
        $lastError = null;
        foreach ($profiles as $profile) {
            $result = $this->shareProfile($profile->id, $userId, $role, $currentUser);
            if ($result['success']) {
                $count++;
            } else {
                $lastError = $result['message'];
            }
        }

        $total = count(value: $profileIds);
        return [
            'success' => $count > 0,
            'message' => $count === $total ? 'ok' : ($count > 0 ? 'partial_profiles_shared' : 'no_profiles_shared'),
            'data' => [
                'shared_count' => $count,
                'total_profiles' => $total,
                'last_error' => $lastError
            ]
        ];

    }

    public function removeShareProfile(string $profileId, string $userId)
    {
        $user = auth()->user();

        if (!$this->checkAccessProfile($profileId, $user, [ProfileShare::ROLE_FULL])) {
            return ['success' => false, 'message' => 'insufficient_permission_profile_share_remove', 'data' => null];
        }

        $profileShare = ProfileShare::where('profile_id', $profileId)->where('user_id', $userId)->first();
        if ($profileShare == null) {
            return ['success' => false, 'message' => 'share_not_found', 'data' => null];
        }

        $profileShare->delete();

        return ['success' => true, 'message' => 'ok', 'data' => null];
    }

    public function bulkRemoveShareProfile(array $profileIds, string $userId)
    {
        $count = 0;
        $lastError = null;
        foreach ($profileIds as $profileId) {
            $result = $this->removeShareProfile($profileId, $userId);
            if ($result['success']) {
                $count++;
            } else {
                $lastError = $result['message'];
            }
        }

        return ['success' => true, 'message' => 'ok', 'data' => [
            'removed_count' => $count,
            'total_profiles' => count(value: $profileIds),
            'last_error' => $lastError
        ]];
    }

    function arrayHasKeyPath(array $array, string $path): bool
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (!is_array($array) || !array_key_exists($key, $array)) {
                return false;
            }
            $array = $array[$key];
        }
        return true;
    }

    function arraySetByPath(array &$array, string $path, $value)
    {
        $keys = explode('.', $path);
        $ref =& $array;
        foreach ($keys as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref =& $ref[$key];
        }
        $ref = $value;
    }

    function editProperty(string $profileId, string $fieldName, ?string $newValue)
    {
        $profile = Profile::active()->find($profileId);
        if ($fieldName == 'is_deleted'){
            $profile = Profile::find($profileId);
        }

        if ($profile === null) {
            return ['success' => false, 'message' => 'profile_not_found', 'data' => null];
        }

        $user = auth()->user();
        if(!$this->canModifyProfile($profileId, $user)) {
            return ['success' => false, 'message' => 'insufficient_permission_profile_edit', 'data' => null];
        }

        // Lấy danh sách cột của bảng
        $columns = Schema::getColumnListing('profiles');

        if (in_array($fieldName, $columns)) {
            $profile->$fieldName = $newValue;
        } else {
            $updated = false;

            // Cập nhật trong dynamic_data (hỗ trợ key lồng: proxy.raw_proxy)
            $dynamicData = json_decode($profile->dynamic_data, true);
            if (is_array($dynamicData)) {
                if ($this->arrayHasKeyPath($dynamicData, $fieldName)) {
                    $this->arraySetByPath($dynamicData, $fieldName, $newValue);
                    $profile->dynamic_data = json_encode($dynamicData);
                    $updated = true;
                }
            }

            // Nếu chưa cập nhật, thử trong fingerprint_data
            if (!$updated) {
                $fingerprintData = json_decode($profile->fingerprint_data, true);
                if (is_array($fingerprintData)) {
                    if ($this->arrayHasKeyPath($fingerprintData, $fieldName)) {
                        $this->arraySetByPath($fingerprintData, $fieldName, $newValue);
                        $profile->fingerprint_data = json_encode($fingerprintData);
                        $updated = true;
                    }
                }
            }

            if (!$updated) {
                return ['success' => false, 'message' => 'field_not_found', 'data' => null];
            }
        }

        $profile->save();

        return ['success' => true, 'message' => 'ok', 'data' => null];
    }



    public function bulkEditProperty(array $profileIds, string $fieldName, ?string $newValue)
    {
        $count = 0;
        $lastError = null;
        if($newValue != null) {
            $array = preg_split('/\r\n|\r|\n/', $newValue);
            $countArray = count($array);
            $index = 0;
            foreach ($profileIds as $profileId) {
                $value = $array[$index % $countArray] ?? null;
                $result = $this->editProperty($profileId, $fieldName, $value);
                if ($result['success']) {
                    $count++;
                } else {
                    $lastError = $result['message'];
                }
                $index++;
            }
        }

        $total = count(value: $profileIds);
        return ['success' => $count > 0,
            'message' => $count === $total ? 'ok' : ($count > 0 ? 'partial_profiles_updated' : 'no_profiles_updated'),
            'data' => [
            'updated_count' => $count,
            'total_profiles' => $total,
            'last_error' => $lastError
        ]];
    }

    public function bulkEditProxy(array $profileIds, array $proxies)
    {
        $count = 0;
        $index = 0;
        $countProxies = count($proxies);
        $lastError = null;
        foreach ($profileIds as $profileId) {
            $newValue = $proxies[$index % $countProxies] ?? null;
            if($newValue == "null")
                $newValue = null;
            $result = $this->editProperty($profileId, "proxy.raw_proxy", $newValue);
            if ($result['success']) {
                $count++;
            } else {
                $lastError = $result['message'];
            }
            $index++;
        }

        return ['success' => true, 'message' => 'ok', 'data' => [
            'updated_count' => $count,
            'total_profiles' => count(value: $profileIds),
            'last_error' => $lastError
        ]];
    }

    /**
     * Get total profile count
     *
     * @return int
     */
    public function getTotalProfiles()
    {
        return Profile::active()->count();
    }

    /**
     * Check if user can modify profile
     *
     * @param int $profileId
     * @param User $logonUser
     * @return bool
     */
    public function canModifyProfile(string $profileId, User $logonUser)
    {
        return $this->checkAccessProfile($profileId, $logonUser, [ProfileShare::ROLE_FULL, ProfileShare::ROLE_EDIT]);
    }

    /**
     * Check if user can access profile
     *
     * @param int $profileId
     * @param User $logonUser
     * @return bool
     */
    public function canAccessProfile(string $profileId, User $logonUser)
    {
        return $this->checkAccessProfile($profileId, $logonUser, [ProfileShare::ROLE_FULL, ProfileShare::ROLE_EDIT, ProfileShare::ROLE_VIEW]);
    }

    public function checkAccessProfile(string $profileId, User $logonUser, array $allowRoles)
    {
        if ($logonUser == null) {
            return false;
        }

        if ($logonUser->isAdmin()) {
            return true; // Admin can access all
        }

        $profile = Profile::find($profileId);
        if (!$profile) {
            return false;
        }

        // Check if user is the creator
        if ($profile->created_by == $logonUser->id) {
            return true;
        }

        // Check profile shares
        $profileShare = ProfileShare::where('user_id', $logonUser->id)
            ->where('profile_id', $profileId)
            ->whereIn('role', $allowRoles)
            ->first();

        if ($profileShare != null) {
            return true;
        }

        // Check group shares
        if ($profile->group) {
            $groupShare = GroupShare::where('user_id', $logonUser->id)
                ->where('group_id', $profile->group_id)
                ->whereIn('role', $allowRoles)
                ->first();
            if ($groupShare != null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Legacy method for backward compatibility - Get profile roles
     * This method maps to the new profile shares system
     *
     * @param int $profileId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProfileRoles(string $profileId)
    {
        return $this->getProfileShares($profileId);
    }

    /**
     * Start using profile
     *
     * @param int $profileId
     * @param int $userId
     * @return array
     */
    public function startUsingProfile(string $profileId, string $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'user_not_found', 'data' => null];
        }

        if (!$this->canAccessProfile($profileId, $user)) {
            return ['success' => false, 'message' => 'insufficient_permission_profile', 'data' => null];
        }

        $profile = Profile::active()->find($profileId);
        if (!$profile) {
            return ['success' => false, 'message' => 'profile_not_found', 'data' => null];
        }

        // Check if profile is already in use by someone else
        if ($profile->isInUse() && $profile->using_by != $userId) {
            return ['success' => false, 'message' => 'profile_in_use_by_others', 'data' => null];
        }

        // Mark profile as in use
        $profile->markAsInUse($user);
        $profile->recordUsage($user);

        return ['success' => true, 'message' => 'ok', 'data' => null];
    }

    /**
     * Stop using profile
     *
     * @param int $profileId
     * @param int $userId
     * @return array
     */
    public function stopUsingProfile(string $profileId, string $userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'user_not_found', 'data' => null];
        }

        if (!$this->canAccessProfile($profileId, $user)) {
            return ['success' => false, 'message' => 'insufficient_permission_profile', 'data' => null];
        }

        $profile = Profile::active()->find($profileId);
        if (!$profile) {
            return ['success' => false, 'message' => 'profile_not_found', 'data' => null];
        }

        // Only allow user to stop using if they are the current user
        if ($profile->using_by != $userId) {
            return ['success' => false, 'message' => 'profile_not_current_user', 'data' => null];
        }

        // Mark profile as ready
        $profile->markAsReady();

        return ['success' => true, 'message' => 'ok', 'data' => null];
    }

    /**
     * Add tags to profile
     *
     * @param int $profileId
     * @param array $tagNames
     * @param User $user
     * @return array
     */
    public function addTagsToProfile(string $profileId, array $tags, User $user)
    {
        try {
            if (!$this->canModifyProfile($profileId, $user)) {
                return ['success' => false, 'message' => 'insufficient_permission_profile_tags', 'data' => null];
            }

            $profile = Profile::active()->find($profileId);
            if (!$profile) {
                return ['success' => false, 'message' => 'profile_not_found', 'data' => null];
            }

            if (empty($tags)) {
                return ['success' => false, 'message' => 'tag_list_empty', 'data' => null];
            }

            // Find or create tags
            $isArrayOfIds = is_array($tags) && collect($tags)->every(fn($tag) => is_int($tag) || is_string($tag));
            if ($isArrayOfIds) {
                $tagIds = $tags;
            } else {
                $tags = $this->tagService->createOrUpdateTags($tags, $user->id);
                $tagIds = collect($tags)->pluck('id')->toArray();
            }

            // $tags = $this->tagService->findOrCreateTags($tagNames, $user->id);
            // $tagIds = collect($tags)->pluck('id')->toArray();

            // Attach tags to profile (avoid duplicates)
            $profile->tags()->syncWithoutDetaching($tagIds);

            return [
                'success' => true,
                'message' => 'ok',
                'data' => $profile->load(['tags', 'creator', 'group'])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'error_with_details',
                'data' => null
            ];
        }
    }

    /**
     * Remove tags from profile
     *
     * @param int $profileId
     * @param array $tagIds
     * @param User $user
     * @return array
     */
    public function removeTagsFromProfile(string $profileId, array $tagIds, User $user)
    {
        try {
            if (!$this->canModifyProfile($profileId, $user)) {
                return ['success' => false, 'message' => 'insufficient_permission_profile_remove_tags', 'data' => null];
            }

            $profile = Profile::active()->find($profileId);
            if (!$profile) {
                return ['success' => false, 'message' => 'profile_not_found', 'data' => null];
            }

            if (empty($tagIds)) {
                return ['success' => false, 'message' => 'tag_id_list_empty', 'data' => null];
            }

            // Remove tags from profile
            $profile->tags()->detach($tagIds);

            return [
                'success' => true,
                'message' => 'ok',
                'data' => $profile->load(['tags', 'creator', 'group'])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'error_with_details',
                'data' => null
            ];
        }
    }

    public function removeAllTagsFromProfile(string $profileId)
    {
        try {
            $profile = Profile::active()->find($profileId);
            if (!$profile) {
                return [
                    'success' => false,
                    'message' => 'profile_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to manage this proxy
            $user = auth()->user();
            if (!$this->canModifyProfile($profileId, $user)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_remove_tags',
                    'data' => null
                ];
            }

            $profile->tags()->detach();

            return [
                'success' => true,
                'message' => 'ok',
                'data' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'error_with_details',
                'data' => ['details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Get group shares for a specific group
     *
     * @param int $groupId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProfileShareUsers(string $profileId, $paginate = false)
    {
        $query = ProfileShare::join('users', 'profile_shares.user_id', '=', 'users.id')
        ->where('profile_shares.profile_id', $profileId)
        ->select('users.id', 'users.display_name', 'users.email', 'profile_shares.role');

        if ($paginate) {
            return $query->paginate(20);
        }

        return $query->get();
    }
}
