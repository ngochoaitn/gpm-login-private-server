<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Proxy extends Model
{
    use HasFactory;

    protected $table = 'proxies';

    public $incrementing = false; // Disable auto-increment
    protected $keyType = 'string'; // ID is string (UUID)

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'raw_proxy',
        'meta_data',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        // Add any sensitive fields here if needed
    ];

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
     * Tags associated with this proxy
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'proxy_tags')
            ->using(ProxyTag::class)
            ->withTimestamps();
    }

    /**
     * User who created this proxy
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated this proxy
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Proxy shares
     */
    public function shares()
    {
        return $this->hasMany(ProxyShare::class);
    }

    /**
     * Users who have access to this proxy through shares
     */
    public function sharedUsers()
    {
        return $this->belongsToMany(User::class, 'proxy_shares')
            ->withPivot(['role'])
            ->withTimestamps();
    }


    /**
     * Check if proxy requires authentication
     */
    public function requiresAuth(): bool
    {
        $components = $this->proxy_components;
        return !empty($components['username']) && !empty($components['password']);
    }

    /**
     * Scope to get proxies by type (legacy support)
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('raw_proxy', 'like', strtolower($type) . '://%');
    }

    /**
     * Get the validation rules for the model
     */
    public static function validationRules($userId = null, $proxyId = null)
    {
        $rules = [
            'raw_proxy' => [
                'required',
                'string',
                'max:500'
            ],
            'created_by' => 'required|integer|exists:users,id',
            'updated_by' => 'nullable|integer|exists:users,id'
        ];

        // Add unique rule for raw_proxy + created_by combination
        if ($userId) {
            $uniqueRule = 'unique:proxies,raw_proxy,NULL,id,created_by,' . $userId;
            
            // If updating existing proxy, exclude current proxy from unique check
            if ($proxyId) {
                $uniqueRule = 'unique:proxies,raw_proxy,' . $proxyId . ',id,created_by,' . $userId;
            }
            
            $rules['raw_proxy'][] = $uniqueRule;
        }

        return $rules;
    }

    /**
     * Get validation messages
     */
    public static function validationMessages()
    {
        return [
            'raw_proxy.required' => 'Proxy address is required.',
            'raw_proxy.max' => 'Proxy address cannot exceed 500 characters.',
            'raw_proxy.unique' => 'You already have this proxy address in your list.',
            'created_by.required' => 'Creator is required.',
            'created_by.exists' => 'Creator must be a valid user.',
            'updated_by.exists' => 'Updater must be a valid user.'
        ];
    }

    /**
     * Parse the raw proxy string and return components
     */
    public function getProxyComponentsAttribute(): array
    {
        if (empty($this->raw_proxy)) {
            return [
                'protocol' => null,
                'username' => null,
                'password' => null,
                'host' => null,
                'port' => null,
            ];
        }

        // Parse proxy string like: http://username:password@host:port or host:port
        $components = [
            'protocol' => null,
            'username' => null,
            'password' => null,
            'host' => null,
            'port' => null,
        ];

        $proxy = trim($this->raw_proxy);
        
        // Check if protocol is specified
        if (preg_match('/^(\w+):\/\/(.+)$/', $proxy, $matches)) {
            $components['protocol'] = strtoupper($matches[1]);
            $proxy = $matches[2];
        }

        // Check for username:password@ pattern
        if (preg_match('/^([^:]+):([^@]+)@(.+)$/', $proxy, $matches)) {
            $components['username'] = $matches[1];
            $components['password'] = $matches[2];
            $proxy = $matches[3];
        }

        // Parse host:port
        if (preg_match('/^([^:]+):(\d+)$/', $proxy, $matches)) {
            $components['host'] = $matches[1];
            $components['port'] = (int)$matches[2];
        }

        return $components;
    }

    /**
     * Get the proxy's full address
     */
    public function getFullAddressAttribute(): string
    {
        $components = $this->proxy_components;
        if ($components['host'] && $components['port']) {
            return "{$components['host']}:{$components['port']}";
        }
        return $this->raw_proxy ?? '';
    }

    /**
     * Get the proxy connection string
     */
    public function getConnectionStringAttribute(): string
    {
        $components = $this->proxy_components;
        
        if (!$components['host'] || !$components['port']) {
            return $this->raw_proxy ?? '';
        }

        $protocol = strtolower($components['protocol'] ?? 'http');
        $auth = '';
        
        if ($components['username'] && $components['password']) {
            $auth = "{$components['username']}:{$components['password']}@";
        }

        return "{$protocol}://{$auth}{$components['host']}:{$components['port']}";
    }
}
