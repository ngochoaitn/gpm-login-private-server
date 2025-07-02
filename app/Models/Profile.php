<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'profiles';

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // ID is string (UUID)

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'storage_type',
        'storage_path',
        'meta_data',
        'fingerprint_data',
        'dynamic_data',
        'group_id',
        'created_by',
        'last_run_by',
        'last_run_at',
        'status',
        'using_by',
        'last_used_at',
        'usage_count',
        'is_deleted',
        'deleted_at',
        'deleted_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'meta_data' => 'array',
        'last_run_at' => 'datetime',
        'last_used_at' => 'datetime',
        'deleted_at' => 'datetime',
        'status' => 'integer',
        'usage_count' => 'integer',
        'is_deleted' => 'boolean',
    ];

    /**
     * Storage type constants
     */
    const STORAGE_S3 = 'S3';
    const STORAGE_GOOGLE_DRIVE = 'GOOGLE_DRIVE';
    const STORAGE_LOCAL = 'LOCAL';

    /**
     * Status constants
     */
    const STATUS_READY = 1;
    const STATUS_IN_USE = 2;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * User who last ran this profile
     */
    public function lastRunUser()
    {
        return $this->belongsTo(User::class, 'last_run_by');
    }

    /**
     * User who created this profile
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User currently using this profile
     */
    public function currentUser()
    {
        return $this->belongsTo(User::class, 'using_by');
    }

    /**
     * User who deleted this profile
     */
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Group this profile belongs to
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Profile shares
     */
    public function shares()
    {
        return $this->hasMany(ProfileShare::class);
    }

    /**
     * Users who have access to this profile through shares
     */
    public function sharedUsers()
    {
        return $this->belongsToMany(User::class, 'profile_shares')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Tags associated with this profile
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'profile_tags')
            ->using(ProfileTag::class)
            ->withTimestamps();
    }

    /**
     * Check if profile is currently in use
     */
    public function isInUse(): bool
    {
        return $this->status === self::STATUS_IN_USE && !is_null($this->using_by);
    }

    /**
     * Check if profile is ready to use
     */
    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && is_null($this->using_by);
    }

    /**
     * Check if profile is soft deleted
     */
    public function isDeleted(): bool
    {
        return $this->is_deleted;
    }

    /**
     * Increment usage count and update last used timestamp
     */
    public function recordUsage(User $user = null): void
    {
        $this->increment('usage_count');
        $this->update([
            'last_used_at' => Carbon::now('UTC'),
            'last_run_at' => Carbon::now('UTC'),
            'using_by' => $user?->id,
        ]);
    }

    /**
     * Mark profile as in use by a user
     */
    public function markAsInUse(User $user): void
    {
        $this->update([
            'status' => self::STATUS_IN_USE,
            'using_by' => $user->id,
            'last_used_at' => Carbon::now('UTC'),
            'last_run_at' => Carbon::now('UTC')
        ]);
    }

    /**
     * Mark profile as ready (not in use)
     */
    public function markAsReady(): void
    {
        $this->update([
            'status' => self::STATUS_READY,
            'using_by' => null,
        ]);
    }

    /**
     * Soft delete the profile
     */
    public function softDelete(User $user): void
    {
        $this->update([
            'is_deleted' => true,
            'deleted_at' => Carbon::now('UTC'),
            'deleted_by' => $user->id,
        ]);
    }

    /**
     * Restore soft deleted profile
     */
    public function restore(): void
    {
        $this->update([
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by' => null,
        ]);
    }

    /**
     * Scope to get only active (not deleted) profiles
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', false);
    }

    /**
     * Scope to get only deleted profiles
     */
    public function scopeIntrashed($query)
    {
        return $query->where('is_deleted', true);
    }
}
