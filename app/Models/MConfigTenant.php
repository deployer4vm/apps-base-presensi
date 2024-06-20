<?php

namespace App\Models;

use App\Base\BaseModel;
use App\Base\Traits\ModelDataTenant;

class MConfigTenant extends BaseModel
{
    use ModelDataTenant;
    protected $connection = 'perTenant'; 
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'config';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id', 'created_at'];
}
