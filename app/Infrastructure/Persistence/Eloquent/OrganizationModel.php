<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationModel extends Model
{
    use SoftDeletes, HasUuids;
    
    protected $table = 'organizations';
    
    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'code',
        'level',
        'path',
        'status',
        'settings',
    ];
    
    protected $casts = [
        'level' => 'integer',
        'settings' => 'array',
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
    
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationModel::class, 'parent_id');
    }
    
    public function children(): HasMany
    {
        return $this->hasMany(OrganizationModel::class, 'parent_id');
    }
}
