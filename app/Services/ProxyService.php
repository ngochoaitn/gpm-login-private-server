<?php

namespace App\Services;

use App\Models\Proxy;
use App\Models\ProxyShare;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Log;

class ProxyService
{
    protected $tagService;

    public function __construct(TagService $tagService)
    {
        $this->tagService = $tagService;
    }

    /**
     * Get proxies with filters
     */
    public function getProxies(User $user, array $filters = [], string $sort = 'created_desc')
    {
        $query = Proxy::with(['tags' => function ($q) {
                    $q->select('tags.id', 'name', 'color', 'category')->orderBy('proxy_tags.created_at');
                }])->select('id', 'raw_proxy', 'meta_data', 'created_by', 'updated_by', 'created_at', 'updated_at');

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('raw_proxy', 'like', "%{$search}%");
            });
        }
        // Apply tag filter
        if (!empty($filters['tag_id'])) {
            $tagId = $filters['tag_id'];
            $query->whereHas('tags', function ($q) use ($tagId) {
                $q->where('tags.id', $tagId);
            });
        }

        switch ($sort) {
            case 'created_desc':
                $query->orderBy('created_at', 'desc');
                break;
            case 'created_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('raw_proxy', 'desc');
                break;
            case 'name_asc':
                $query->orderBy('raw_proxy', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Apply user permissions
        if (!$user->isAdmin()) {
            $proxyShareIds = ProxyShare::where('user_id', $user->id)->pluck('proxy_id');

            $query->where(function ($q) use ($user, $proxyShareIds) {
                $q->where('created_by', $user->id)
                    ->orWhereIn('id', $proxyShareIds);
            });
        }

        $perPage = $filters['per_page'] ?? 30;
        $page = $filters['page'] ?? null;
        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get proxy by ID
     */
    public function getProxy($id, User $user)
    {
        try {
            $proxy = Proxy::with(['tags:id,name,color,category'])->select('id', 'raw_proxy', 'meta_data', 'created_at', 'updated_at', 'created_by', 'updated_by')->find($id);

            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to view this proxy
            if (!$this->canAccessProxy($user, $proxy, [ProxyShare::ROLE_FULL, ProxyShare::ROLE_EDIT, ProxyShare::ROLE_VIEW])) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy',
                    'data' => null
                ];
            }

            return [
                'success' => true,
                'message' => 'OK',
                'data' => $proxy
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
     * Create new proxy
     */
    public function createProxy($rawProxy, $metaData = null, $createdBy = null, $updatedBy = null)
    {
        // die($rawProxy ?? 'huhu');
        try {
            $proxy = Proxy::create([
                'raw_proxy' => $rawProxy,
                'meta_data' => $metaData,
                'created_by' => $createdBy,
                'updated_by' => $updatedBy ?? $createdBy
            ]);

            return [
                'success' => true,
                'message' => 'ok',
                'data' => $proxy
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
     * Bulk create proxies
     */
    public function bulkCreateProxy(array $proxiesData, $createdBy = null)
    {
        try {
            $errorProxies = [];
            $successCount = 0;

            foreach ($proxiesData as $index => $proxyData) {
                //die(json_encode($proxyData['meta_data']) ?? 'huhu');
                $result = $this->createProxy(
                    $proxyData['raw_proxy'] ?? $proxyData,
                    $proxyData['meta_data'] ?? null,
                    $createdBy,
                    $createdBy
                );

                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorProxies[] = [
                        'index' => $index,
                        'proxy_data' => $proxyData,
                        'error' => $result['data']['details']
                    ];
                }
            }

            $totalCount = count($proxiesData);
            $errorCount = count($errorProxies);

            return [
                'success' => $successCount > 0,
                'message' => $successCount === $totalCount ? 'all_proxies_created' :
                    ($successCount > 0 ? 'partial_proxies_created' : 'no_proxies_created'),
                'data' => [
                    'created_count' => $successCount,
                    'total_count' => $totalCount,
                    'error_count' => $errorCount,
                    'errors' => $errorProxies
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'bulk_create_failed',
                'data' => ['details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Update proxy
     */
    public function updateProxy($id, $rawProxy, $metaData, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to update this proxy
            if (!$this->canAccessProxy($user, $proxy, [ProxyShare::ROLE_FULL, ProxyShare::ROLE_EDIT])) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_edit',
                    'data' => null
                ];
            }

            $updateData = [
                'raw_proxy' => $rawProxy ?? $proxy->raw_proxy,
                'metaData' => $metaData ?? $proxy->meta_data,
                'updated_by' => $user->id
            ];

            $proxy->update($updateData);

            return [
                'success' => true,
                'message' => 'proxy_updated',
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
     * Delete proxy
     */
    public function deleteProxy($id, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to delete this proxy
            if (!$this->canAccessProxy($user, $proxy, [ProxyShare::ROLE_FULL])) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_delete',
                    'data' => null
                ];
            }

            // Remove all tag associations
            $proxy->tags()->detach();

            $proxy->delete();

            return [
                'success' => true,
                'message' => 'proxy_deleted',
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

    public function bulkDeleteProxy(array $proxyIds, User $user)
    {
        try {
            $count = 0;
            foreach ($proxyIds as $proxyId) {
                $result = $this->deleteProxy($proxyId, $user);
                if ($result['success']) {
                    $count++;
                }
            }

            $total = count($proxyIds);
            return [
                'success' => $count > 0,
                'message' => $count === $total ? 'all_proxies_deleted' :
                    ($count > 0 ? 'partial_proxies_deleted' : 'no_proxies_deleted'),
                'data' => [
                    'deleted_count' => $count,
                    'total_proxies' => $total
                    ]
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
     * Add tags to proxy
     * @param array $tags array of {name, color, category} or tag ids ['1', '2', '3']
     */
    public function addTagsToProxy($id, array $tags, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to manage this proxy
            if (!$this->canAccessProxy($user, $proxy, [ProxyShare::ROLE_FULL, ProxyShare::ROLE_EDIT])) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_tags',
                    'data' => null
                ];
            }

            $isArrayOfIds = is_array($tags) && collect($tags)->every(fn($tag) => is_int($tag) || is_string($tag));
            if ($isArrayOfIds) {
                $tagIds = $tags;
            } else {
                $tags = $this->tagService->createOrUpdateTags($tags, $user->id);
                $tagIds = collect($tags)->pluck('id')->toArray();
            }

            $proxy->tags()->syncWithoutDetaching($tagIds);
            $proxy->update(['updated_by' => $user->id]);

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
     * Remove tags from proxy
     */
    public function removeTagsFromProxy($id, array $tagIds, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to manage this proxy
            if (!$this->canAccessProxy($user, $proxy, [ProxyShare::ROLE_FULL, ProxyShare::ROLE_EDIT])) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_remove_tags',
                    'data' => null
                ];
            }

            $proxy->tags()->detach($tagIds);
            $proxy->update(['updated_by' => $user->id]);

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

    public function removeAllTagsFromProxy($id, User $user)
    {
        try {
            $proxy = Proxy::find($id);
            if (!$proxy) {
                return [
                    'success' => false,
                    'message' => 'proxy_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to manage this proxy
            if (!$this->canAccessProxy($user, $proxy, [ProxyShare::ROLE_FULL, ProxyShare::ROLE_EDIT])) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_proxy_remove_tags',
                    'data' => null
                ];
            }

            $proxy->tags()->detach();
            $proxy->update(['updated_by' => $user->id]);

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
     * Get proxy shares
     *
     * @param int $proxyId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProxyShareUsers(string $proxyId, $paginate = false)
    {
        $query = ProxyShare::join('users', 'proxy_shares.user_id', '=', 'users.id')
        ->where('proxy_shares.proxy_id', $proxyId)
        ->select('users.id', 'users.display_name', 'users.email', 'proxy_shares.role');

        if ($paginate) {
            return $query->paginate(20);
        }

        return $query->get();
    }

    /**
     * Share proxy with user
     *
     * @param int $proxyId
     * @param int $userId
     * @param string $role
     * @param User $currentUser
     * @return array
     */
    public function shareProxy(string $proxyId, string $userId, string $role, User $currentUser)
    {
        // Validate shared user
        $sharedUser = User::find($userId);
        if ($sharedUser == null) {
            return ['success' => false, 'message' => 'user_not_found', 'data' => null];
        }

        if ($sharedUser->isAdmin()) {
            return ['success' => false, 'message' => 'no_need_set_admin_permission', 'data' => null];
        }

        // Validate proxy
        $proxy = Proxy::find($proxyId);
        if ($proxy == null) {
            return ['success' => false, 'message' => 'proxy_not_found', 'data' => null];
        }

        // Check permission
        if (!$this->canAccessProxy($currentUser, $proxy, [ProxyShare::ROLE_FULL])) {
            return ['success' => false, 'message' => 'owner_required', 'data' => null];
        }

        // Handle proxy share
        $proxyShare = ProxyShare::where('proxy_id', $proxyId)
            ->where('user_id', $userId)
            ->first();

        // If role is empty or invalid, remove the share
        if (empty($role) || !in_array($role, [ProxyShare::ROLE_FULL, ProxyShare::ROLE_EDIT, ProxyShare::ROLE_VIEW])) {
            if ($proxyShare != null) {
                $proxyShare->delete();
            }
            return ['success' => true, 'message' => 'ok', 'data' => null];
        }

        // Create or update share
        if ($proxyShare == null) {
            $proxyShare = new ProxyShare();
        }

        $proxyShare->proxy_id = $proxyId;
        $proxyShare->user_id = $userId;
        $proxyShare->role = $role;
        $proxyShare->save();

        return ['success' => true, 'message' => 'ok', 'data' => null];
    }

    /**
     * Bulk share proxies with user
     *
     * @param array $proxyIds
     * @param int $userId
     * @param string $role
     * @param User $currentUser
     * @return array
     */
    public function bulkShareProxy(array $proxyIds, string $userId, string $role, User $currentUser)
    {
        // Validate proxies
        $count = 0;
        foreach ($proxyIds as $id) {
            $result = $this->shareProxy($id, $userId, $role, $currentUser);
            if ($result['success']) {
                $count++;
            }
        }

        $total = count($proxyIds);
        return [
            'success' => $count > 0,
            'message' => $count === $total ? 'all_proxies_shared' :
                ($count > 0 ? 'partial_proxies_shared' : 'no_proxies_shared'),
            'data' => [
                'shared_count' => $count,
                'total_proxies' => $total
            ]
        ];
    }

    public function removeShareProxy(string $proxyId, string $userId)
    {
        $proxyShare = ProxyShare::where('proxy_id', $proxyId)
            ->where('user_id', $userId)
            ->first();

        if ($proxyShare == null) {
            return ['success' => false, 'message' => 'share_not_found', 'data' => null];
        }

        $proxy = Proxy::find($proxyId);
        if ($proxy == null) {
            return ['success' => false, 'message' => 'proxy_not_found', 'data' => null];
        }

        $currentUser = auth()->user();

        if (!$this->canAccessProxy($currentUser, $proxy, [ProxyShare::ROLE_FULL])) {
            return ['success' => false, 'message' => 'owner_required', 'data' => null];
        }

        $proxyShare->delete();

        return ['success' => true, 'message' => 'ok', 'data' => null];
    }

    public function bulkRemoveShareProxy(array $proxyIds, string $userId)
    {
        $count = 0;
        $lastError = null;
        foreach ($proxyIds as $id) {
            $result = $this->removeShareProxy($id, $userId);
            if ($result['success']) {
                $count++;
            } else {
                $lastError = $result['message'];
            }
        }

        $total = count($proxyIds);
        return [
            'success' => $count > 0,
            'message' => $count === $total ? 'all_proxies_removed_share' :
                ($count > 0 ? 'partial_proxies_removed_share' : 'no_proxies_removed_share'),
            'data' => [
                'removed_count' => $count,
                'total_proxies' => $total,
                'last_error' => $lastError
            ]
        ];
    }

    /**
     * Check if user can manage proxy
     */
    private function canAccessProxy(User $user, Proxy $proxy, array $allowRoles)
    {
        // Admin can manage any proxy
        if ($user->isAdmin()) {
            return true;
        }

        // User can manage proxies they created
        if ($proxy->created_by === $user->id) {
            return true;
        }

        // Check proxy shares with FULL access
        $proxyShare = ProxyShare::where('user_id', $user->id)
            ->where('proxy_id', $proxy->id)
            ->whereIn('role', $allowRoles)
            ->first();  

        return $proxyShare !== null && in_array($proxyShare->role, $allowRoles);
    }
}
