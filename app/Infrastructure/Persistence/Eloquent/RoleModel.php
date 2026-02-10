<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RoleModel extends TenantAwareModel
{
    use HasUuids;
    
    protected $table = 'roles';
    
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'status',
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    protected $hidden = [
        'deleted_at',
    ];
    
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantModel::class, 'tenant_id');
    }
    
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(PermissionModel::class, 'role_permission', 'role_id', 'permission_id')
            ->withTimestamps();
    }
    
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(UserModel::class, 'user_role', 'role_id', 'user_id')
            ->withTimestamps();
    }
}
