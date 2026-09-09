<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Models\MConfig;
use App\Models\MConfigTenant;
use App\Facades\DbConfig;
use App\Facades\CacheConfig;
use App\Facades\Tenant;

use App\Base\BaseController;

use hpsynapse\moduser\Facades\UserAuth;

class ConfigController extends BaseController
{
    protected $cacheActive = true;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    private function initTenantId($request)
    {        
        $tenantId = $request->input('tenant_id', false);
        if($tenantId && config('tenant.id', 0) != $tenantId){
            Tenant::setActiveTenantById($tenantId);
        }else{
            $tenantId = config('tenant.id', 0);
        }
        return $tenantId;
    }

    /**
     * GET - /sys/config/
     * api list config (config yang disimpan didatabase)
     *
     * @param Request $request
     *      group *optional
     *      key *optional
     *      tenant_id *optional
     *
     * @return JSON_ARRAY list data config dengan key 'group', 'key', 'value'
     */
    public function readList(Request $request)
    {
        $tenantId = $this->initTenantId($request);

        $model = $tenantId?MConfigTenant::select(['group', 'key', 'value']):MConfig::select(['group', 'key', 'value']);

        $model = $model->where('tenant_id', $tenantId);

        if ($group = $request->input('group', false)) {
            if (is_array($group)) {
                $model = $model->whereIn('group', $group);
            } else {
                $model = $model->where('group', $group);
            }
        }

        if ($key = $request->input('key', false)) {
            if (is_array($key)) {
                $model = $model->whereIn('key', $key);
            } else {
                $model = $model->where('key', $key);
            }
        }

        $data = $model->get();

        return response()->json($data);
    }

    /**
     * POST - /sys/config/
     * create or update
     *
     * @param Request $request
     *      tenant_id
     *      data array list data config yang akan di create / update
     *          name
     *          value
     *          group
     *          key
     *
     * @return array list data config
     */
    public function createUpdate(Request $request)
    {
        $tenantId = $this->initTenantId($request);

        $groupWhere = [];
        $keyWhere = [];
        if ($data = $request->input('data', false)) {
            foreach ($data as $value) {
                $updateData = [];
                if (isset($value['name']))
                    $updateData['name'] = $value['name'];
                if (isset($value['value']))
                    $updateData['value'] = $value['value'];
                if ($updateData) {
                    $model = $tenantId?MConfigTenant::where('group', $value['group']):MConfig::where('group', $value['group']);
                    
                    $model = $model->where('key', $value['key'])
                        ->where('tenant_id', $tenantId);

                    $groupWhere[] = $value['group'];
                    $keyWhere[] = $value['key'];
                    if ($model->exists()) {
                        $model->update($updateData);
                    } else {
                        $updateData['group'] = $value['group'];
                        $updateData['key'] = $value['key'];
                        $updateData['tenant_id'] = $tenantId;
                        $model->create($updateData);
                    }
                }
            }
        }
        $model = $tenantId?MConfigTenant::where('tenant_id', $tenantId):MConfig::where('tenant_id', $tenantId);
        return response()->json($model
            ->whereIn('group', $groupWhere)
            ->whereIn('key', $keyWhere)
            ->get());
    }

    /**
     * GET - /sys/config/access
     *
     * manage access config
     *
     * @param Request $request
     *      tenant_id
     * @return SynapseReturnFormat
     */
    public function accessConfig(Request $request)
    {
        $this->initTenantId($request);

        $config = CacheConfig::getConfig('accesss', [
            'allow_login' => 1,
            'allow_login_exept' => [],
            'allow_login_only' => []
        ]);

        $this->output['data'] = [
            'allow_login' => (bool) ($config['allow_login'] ?? true),
        ];
        return $this->done();
    }

    /**
     * PUT - /sys/config/access
     * Reset locking
     */
    public function unlockAccess(Request $request)
    {
        $this->forceApiOutput();

        \hpsynapse\moduser\Facades\UserAuth::unlockLogin();
        return $this->done();
    }
}
