<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\MConfig;
use App\Facades\DbConfig;
use App\Facades\CacheConfig;

use App\Base\BaseController;

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

    /**
     * GET - /sys/config/
     * api list config (config yang disimpan didatabase)
     *
     * @param Request $request
     *      group *optional
     *      key *optional
     *      tenant_id *optional
     *
     * @return array list data config
     */
    public function readList(Request $request)
    {
        $model = new MConfig;

        $model = $model->where('tenant_id', $request->input('tenant_id', config('tenant.id', 0)));

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
     *      data array list data config yang akan di create / update
     *
     * @return array list data config
     */
    public function createUpdate(Request $request)
    {
        $tenantId = $request->input('tenant_id', config('tenant.id', 0));
        if ($data = $request->input('data', false)) {
            foreach ($data as $value) {
                $updateData = [];
                if (isset($value['name']))
                    $updateData['name'] = $value['name'];
                if (isset($value['value']))
                    $updateData['value'] = $value['value'];
                if ($updateData) {
                    $model = MConfig::where('group', $value['group'])
                        ->where('key', $value['key'])
                        ->where('tenant_id', $tenantId);
                    if ($model->exists()) {
                        $model->update($updateData);
                    } else {
                        $updateData['group'] = $value['group'];
                        $updateData['key'] = $value['key'];
                        $model->create($updateData);
                    }
                }
            }
        }

        return response()->json(MConfig::get());
    }

    /**
     * GET - /sys/config/access
     *
     * manage access config
     *
     * @return SynapseReturnFormat
     */
    public function accessConfig(Request $request)
    {
        // if(!($config = $this->_getCache('generalconfig','accesss'))){
        //     $config = [
        //         'allow_login' => 1,
        //         'allow_login_exept' => [],
        //         'allow_login_only' => []
        //     ];
        //     $this->_saveCache('generalconfig','accesss',$config);
        // }

        $config = CacheConfig::getConfig('accesss', [
            'allow_login' => 1,
            'allow_login_exept' => [],
            'allow_login_only' => []
        ]);

        $this->output['data'] = $config; //UserAuth::getAccessConfig();
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
        // $this->output['data'] = ;
        return $this->done();
    }
}
