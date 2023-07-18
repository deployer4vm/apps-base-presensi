<?php

namespace App\Models;

use App\Base\BaseModel;

class SystemCallback extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system_callback';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id', 'created_at'];

    protected $casts  = [
        'data' => 'array',
    ];

    public function log()
    {
        return $this->hasMany(SystemCallbackLog::class, 'system_callback_id', 'id');
    }
}
