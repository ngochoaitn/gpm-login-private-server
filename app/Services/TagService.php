<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TagService
{
    /**
     * Get all tags with pagination and search
     */
    public function getAllTags(array $filters = [])
    {
        $query = Tag::query();

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Default ordering
        $query->orderBy('name');

        $perPage = $filters['per_page'] ?? 30;
        $page = $filters['page'] ?? null;
        $result = $query->paginate($perPage, ['*'], 'page', $page);

        // Return all results if no pagination
        return [
            'success' => true,
            'message' => 'ok',
            'data' => $result
        ];
    }

    public function getTagByName(string $name, string $category)
    {
        $query = Tag::query()->select('id', 'name', 'color', 'category');
        $query->where('name', $name);
        $query->where('category', $category);
        $result = $query->first();
        return [
            'success' => $result ? true : false,
            'message' => $result ? 'ok' : 'tag_not_found',
            'data' => $result
        ];
    }

    /**
     * Get tag by ID
     */
    public function getTag($id)
    {
        return Tag::find($id);
    }

    /**
     * Create new tag
     */
    public function createTag($name, $color = '#007bff', $category = null, $createdBy = null)
    {
        try {
            // Check if tag with same name already exists
            $existingTag = Tag::where('name', $name)->where('category', $category)->first();
            if ($existingTag) {
                return [
                    'success' => true,
                    'message' => 'tag_exists',
                    'data' => $existingTag
                ];
            }

            $tag = Tag::create([
                'name' => $name,
                'color' => $color ?? '#007bff',
                'category' => $category,
                'created_by' => $createdBy
            ]);

            return [
                'success' => true,
                'message' => 'tag_created',
                'data' => $tag
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
     * Update tag
     */
    public function updateTag($id, $name, $color, $category, User $user)
    {
        try {
            $tag = Tag::find($id);
            if (!$tag) {
                return [
                    'success' => false,
                    'message' => 'tag_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to update this tag
            if (!$this->canManageTag($user, $tag)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_tag_edit',
                    'data' => null
                ];
            }

            // Check if another tag with same name already exists
            $existingTag = Tag::where('name', $name)->where('id', '!=', $id)->where('category', $category)->first();
            if ($existingTag) {
                return [
                    'success' => false,
                    'message' => 'tag_name_exists',
                    'data' => null
                ];
            }

            $tag->update([
                'name' => $name,
                'color' => $color ?? $tag->color,
                'category' => $category ?? $tag->category
            ]);

            return [
                'success' => true,
                'message' => 'tag_updated',
                'data' => $tag
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
     * Delete tag
     */
    public function deleteTag($id, User $user)
    {
        try {
            $tag = Tag::find($id);
            if (!$tag) {
                return [
                    'success' => false,
                    'message' => 'tag_not_found',
                    'data' => null
                ];
            }

            // Check if user has permission to delete this tag
            if (!$this->canManageTag($user, $tag)) {
                return [
                    'success' => false,
                    'message' => 'insufficient_permission_tag_delete',
                    'data' => null
                ];
            }

            // Check if tag is being used by any profiles or proxies
            $profileTagsCount = $tag->profiles()->count();
            $proxyTagsCount = $tag->proxies()->count();

            if ($profileTagsCount > 0 || $proxyTagsCount > 0) {
                return [
                    'success' => false,
                    'message' => 'tag_in_use',
                    'data' => null
                ];
            }

            $tag->delete();

            return [
                'success' => true,
                'message' => 'tag_deleted',
                'data' => null
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'error_with_details' . $e->getMessage(),
                'data' => ['details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Get tags with profile count
     */
    public function getTagsWithProfileCount()
    {
        return Tag::withCount('profileTags')
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if user can manage tag
     */
    private function canManageTag(User $user, Tag $tag)
    {
        // Admin and mod can manage any tag
        if (in_array($user->system_role, ['ADMIN', 'MOD'])) {
            return true;
        }

        // User can only manage tags they created
        return $tag->created_by === $user->id;
    }

    /**
     * Find or create tags by names
     */
    public function findOrCreateTags(array $tagNames, $createdBy = null)
    {
        $tags = [];

        foreach ($tagNames as $tagName) {
            $tag = Tag::firstOrCreate(
                ['name' => trim($tagName)],
                [
                    'color' => '#007bff',
                ]
            );
            $tags[] = $tag;
        }

        return $tags;
    }

    public function createOrUpdateTags(array $tags)
    {
        $result = [];

        foreach ($tags as $tagData) {
            $name = trim($tagData['name']);
            $color = $tagData['color'] ?? '#007bff';
            $category = $tagData['category'] ?? 'null';

            $tag = Tag::where('name', $name)->where('category', $category)->first();

            if ($tag) {
                $tag->color = $color;
                $tag->save();
            } else {
                $tag = Tag::create([
                    'name' => $name,
                    'color' => $color,
                    'category' => $category,
                ]);
            }

            $result[] = $tag;
        }

        return $result;
    }
}
