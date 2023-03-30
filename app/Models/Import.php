<?php

namespace App\Models;

use App\Base\BaseModel;

class Import extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'imports';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id', 'created_at'];

    protected $casts  = [
        'data' => 'array',
    ];
}
