<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PermissionModel extends Model
{
    use SoftDeletes, HasUuids;
    
    protected $table = 'permissions';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'module',
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
    
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RoleModel::class, 'role_permission', 'permission_id', 'role_id')
            ->withTimestamps();
    }
}
