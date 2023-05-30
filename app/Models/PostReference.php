<?php

namespace App\Models;

use App\Base\BaseModel;
use App\Base\Traits\ModelDataTenant;

class PostReference extends BaseModel
{
    use ModelDataTenant;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'post_references';

}