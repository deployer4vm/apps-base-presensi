<?php

namespace App\Models;

use App\Base\BaseModel;

class TenantGroupTenant extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_group_tenants';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id', 'created_at'];

    public function group()
    {
        return $this->hasOne(TenantGroup::class, 'id', 'tenant_group_id');
    }
}
