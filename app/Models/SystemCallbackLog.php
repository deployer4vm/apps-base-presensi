<?php

namespace App\Models;

use App\Base\BaseModel;

class SystemCallbackLog extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system_callback_log';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id', 'created_at'];

    protected $casts  = [
        'response' => 'array',
    ];
}
