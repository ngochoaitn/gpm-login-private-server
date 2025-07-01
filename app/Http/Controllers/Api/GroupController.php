<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Services\GroupService;

class GroupController extends BaseController
{
    protected GroupService $groupService;

    public function __construct(GroupService $groupService)
    {
        $this->groupService = $groupService;
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->search ?? null,
            'per_page' => $request->per_page ?? 30,
            'page' => $request->page ?? 1
        ];

        $groups = $this->groupService->getAllGroups($filters);
        return $this->getJsonResponse(true, 'OK', $groups);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $group = $this->groupService->createGroup(
            $request->name,
            $request->order,
            $user->id
        );

        return $this->getJsonResponse(true, 'group_created', $group);
    }

    public function show($id, Request $request)
    {
        $includeShareUsers = $request->include_share_users ?? false;
        $group = $this->groupService->getGroupById($id, $includeShareUsers);
        return $this->getJsonResponse(true, 'OK', $group);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $group = $this->groupService->updateGroup(
            $id,
            $request->name,
            $request->order,
            $user->id
        );

        if ($group == null) {
            return $this->getJsonResponse(false, 'can_not_update_group', null);
        }

        return $this->getJsonResponse(true, 'group_updated', null);
    }

    public function destroy($id, Request $request)
    {
        $result = $this->groupService->deleteGroup($id);

        return $this->getJsonResponse($result['success'], $result['message'], null);
    }


    public function getTotal()
    {
        $total = $this->groupService->getTotalGroups();
        return $this->getJsonResponse(true, 'OK', $total);
    }


    public function getGroupShareUsers($id)
    {
        $groupShares = $this->groupService->getGroupShareUsers($id, true);
        return $this->getJsonResponse(true, 'OK', $groupShares);
    }

    public function share($id, Request $request)
    {
        $user = $request->user();

        $result = $this->groupService->shareGroup(
            $id,
            $request->user_id,
            $request->role,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], null);
    }

    public function removeShare($id, Request $request)
    {
        $result = $this->groupService->removeShareGroup($id, $request->user_id);

        return $this->getJsonResponse($result['success'], $result['message'], null);
    }
}
