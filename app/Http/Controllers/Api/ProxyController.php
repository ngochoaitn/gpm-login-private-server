<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Services\ProxyService;
use App\Http\Requests\CreateProxyRequest;

class ProxyController extends BaseController
{
    protected $proxyService;

    public function __construct(ProxyService $proxyService)
    {
        $this->proxyService = $proxyService;
    }


    public function index(Request $request)
    {
        $user = $request->user();
        $filters = [
            'search' => $request->search ?? null,
            'tags' => $request->tags ?? null,
            'per_page' => $request->per_page ?? 30,
            'page' => $request->page ?? 1,
            'tag_id' => $request->tag_id ?? null,
        ];
        $sort = $request->sort ?? 'created_desc';
        $proxies = $this->proxyService->getProxies($user, $filters, $sort);
        return $this->getJsonResponse(true, 'OK', $proxies);
    }


    public function store(CreateProxyRequest $request)
    {
        $user = $request->user();

        $result = $this->proxyService->createProxy(
            $request->raw_proxy,
            $request->meta_data ?? null,
            $user->id,
            $user->id
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkStore(Request $request)
    {
        $user = $request->user();

        $result = $this->proxyService->bulkCreateProxy(
            $request->proxies ?? $request->all() ?? [],
            $user->id
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }


    public function show($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->getProxy($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }


    public function update(Request $request, $id)
    {
        $user = $request->user();

        $result = $this->proxyService->updateProxy(
            $id,
            $request->raw_proxy ?? null,
            $request->meta_data ?? null,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->deleteProxy($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], null);
    }

    public function bulkDelete(Request $request)
    {
        $user = $request->user();
        $proxyIds = $request->proxy_ids ?? $request->ids ?? $request->all() ?? [];
        $result = $this->proxyService->bulkDeleteProxy($proxyIds, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function addTags($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->addTagsToProxy($id, $request->tags ?? $request->all() ?? [], $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function removeTags($id, Request $request)
    {
        $user = $request->user();
        $tags = $request->tags ?? $request->tag_ids ?? $request->ids ?? $request->all() ?? [];
        $result = $this->proxyService->removeTagsFromProxy($id, $tags, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function removeAllTags($id, Request $request)
    {
        $user = $request->user();
        $result = $this->proxyService->removeAllTagsFromProxy($id, $user);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkShare(Request $request)
    {
        $user = $request->user();

        $result = $this->proxyService->bulkShareProxy(
            $request->proxy_ids ?? $request->ids,
            $request->user_id,
            $request->role,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function removeShare($id, Request $request)
    {
        $user_id = $request->user_id;
        $result = $this->proxyService->removeShareProxy($id, $user_id);
        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function bulkRemoveShare(Request $request)
    {
        $proxyIds = $request->proxy_ids ?? $request->ids ?? $request->all() ?? [];
        $result = $this->proxyService->bulkRemoveShareProxy(
            $proxyIds,
            $request->user_id
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

    public function getProxyShareUsers($id)
    {
        $proxyShares = $this->proxyService->getProxyShareUsers($id, true);
        return $this->getJsonResponse(true, 'OK', $proxyShares);
    }

    public function share($id, Request $request)
    {
        $user = $request->user();

        $result = $this->proxyService->shareProxy(
            $id,
            $request->user_id,
            $request->role,
            $user
        );

        return $this->getJsonResponse($result['success'], $result['message'], $result['data']);
    }

}
