<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TenantModel extends Model
{
    use SoftDeletes, HasUuids;
    
    protected $table = 'tenants';
    
    protected $fillable = [
        'name',
        'domain',
        'status',
        'database_name',
        'settings',
        'trial_ends_at',
    ];
    
    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    protected $hidden = [
        'deleted_at',
    ];
}
