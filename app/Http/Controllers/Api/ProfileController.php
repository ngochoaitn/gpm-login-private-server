<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Services\ProfileService;
use Carbon\Carbon;

class ProfileController extends BaseController
{
    protected $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $filters = [
            'group_id' => $request->group_id ?? null,
            'search' => $request->search ?? null,
            'share_mode' => $request->share_mode ?? null,
            'tags' => $request->tags ?? $request->tag_id ?? null,
            'sort' => $request->sort ?? null,
            'per_page' => $request->per_page ?? 30,
            'page' => $request->page ?? 1,
            'is_deleted' => $request->is_deleted ?? 0,
            'id' => $request->id ?? null,
        ];

        $extensiveFields = $request->extensive_fields ?? [];
        if ($extensiveFields && is_string($extensiveFields)) {
            $extensiveFields = array_map('trim', explode(',', $extensiveFields));
        }

        $profiles = $this->profileService->getProfiles($user, $filters, $extensiveFields);
        return $this->getJsonResponse(true, 'OK', $profiles);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $result = $this->profileService->createProfile(
            $request->name,
            $request->storage_path,
            $request->fingerprint_data,
            $request->dynamic_data,
            $request->meta_data,
            $request->group_id,
            $user->id,
            $request->storage_type ?? 'S3'
        );

        return $this->getJsonResponse(true, 'success', $result);
    }

    public function show($id, Request $request)
    {
        $result = $this->profileService->getProfile($id);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $result = $this->profileService->updateProfile(
            $id,
            $request->name,
            $request->storage_path,
            $request->fingerprint_data,
            $request->dynamic_data,
            $request->meta_data,
            $request->group_id,
            Carbon::now('UTC'),
            $user->id,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function updateStatus($id, Request $request)
    {
        $user = $request->user();

        $result = $this->profileService->updateProfileStatus(
            $id,
            $request->status,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function destroy($id, Request $request)
    {
        $delete_mode = $request->mode ?? 'soft';
        $result = $this->profileService->deleteProfile($id, $delete_mode);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkDelete(Request $request)
    {
        $profile_ids = $request->profile_ids ?? $request->ids ?? $request->all() ?? [];
        $delete_mode = $request->mode ?? 'soft';
        $result = $this->profileService->bulkDeleteProfile($profile_ids, $delete_mode);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function getProfileShares($id)
    {
        $profileShares = $this->profileService->getProfileShares($id);
        return $this->getJsonResponse(true, 'OK', $profileShares);
    }

    public function share($id, Request $request)
    {
        $user = $request->user();

        $result = $this->profileService->shareProfile(
            $id,
            $request->user_id,
            $request->role,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function removeShare($id, Request $request)
    {
        $user_id = $request->user_id;
        $result = $this->profileService->removeShareProfile($id, $user_id);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkShare(Request $request)
    {
        $user = $request->user();

        $profile_ids = $request->profile_ids ?? $request->ids ?? [];
        $result = $this->profileService->bulkShareProfile(
            $profile_ids,
            $request->user_id,
            $request->role,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkRemoveShare(Request $request)
    {
        $profile_ids = $request->profile_ids ?? $request->ids ?? [];
        $result = $this->profileService->bulkRemoveShareProfile($profile_ids, $request->user_id);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkEditProperty(Request $request)
    {
        $profile_ids = $request->profile_ids ?? $request->ids ?? [];
        $result = $this->profileService->bulkEditProperty($profile_ids, $request->field_name, $request->new_value);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkEditProxy(Request $request)
    {
        $profile_ids = $request->profile_ids ?? $request->ids ?? [];
        $result = $this->profileService->bulkEditProxy($profile_ids, $request->proxies);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function getTotal()
    {
        $total = $this->profileService->getTotalProfiles();
        return $this->getJsonResponse(true, 'OK', ['total' => $total]);
    }

    public function startUsing($id, Request $request)
    {
        $user = $request->user();
        $result = $this->profileService->startUsingProfile($id, $user->id);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function stopUsing($id, Request $request)
    {
        $user = $request->user();
        $result = $this->profileService->stopUsingProfile($id, $user->id);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function addTags($id, Request $request)
    {
        $user = $request->user();
        $tags = $request->tags ?? $request->all() ?? [];
        $result = $this->profileService->addTagsToProfile($id, $tags, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function removeTags($id, Request $request)
    {
        $user = $request->user();
        $tags = $request->tags ?? $request->tag_ids ?? $request->ids ?? $request->all() ?? [];
        $result = $this->profileService->removeTagsFromProfile($id, $tags, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function removeAllTags($id, Request $request)
    {
        $result = $this->profileService->removeAllTagsFromProfile($id);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function restore($id, Request $request)
    {
        $user = $request->user();
        $result = $this->profileService->restoreProfile($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkRestore(Request $request)
    {
        $profile_ids = $request->profile_ids ?? $request->ids ?? $request->all() ?? [];
        $result = $this->profileService->bulkRestoreProfile($profile_ids);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function getProfileShareUsers($id)
    {
        $profileShares = $this->profileService->getProfileShareUsers($id, true);
        return $this->getJsonResponse(true, 'OK', $profileShares);
    }
}
