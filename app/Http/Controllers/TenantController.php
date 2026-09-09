<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Tenant;
use App\Models\TenantGroup;
use App\Models\TenantGroupTenant;

use App\Base\BaseController;

class TenantController extends BaseController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * initiate tenant app
     *
     * @param Request $request *semua optional
     *      group_app : apps id / path nya
     * @return array
     *      tenant_list array
     *      active_tenant record array tenant
     *      active_tenant_group array berisi tenant group id dari tenant yg aktif
     */
    public function activeTenant(Request $request)
    {
        $tenant = [
            'tenant_list' => '',
            'active_tenant' => false,
            'active_tenant_group' => false
        ];
        $groupApp = config('tenant.group_app', $request->input('group_app'));
        if ($groupApp) {
            $tenant['active_tenant'] = Tenant::where('group_app', $groupApp)
                ->select(['id', 'name', 'group_app', 'is_main'])
                ->first();
            if ($tenant['active_tenant']) {
                $tenant['active_tenant_group']
                    = TenantGroupTenant::where('tenant_id', $tenant['active_tenant']->id)
                        ->select('tenant_group_id')
                        ->get()
                        ->pluck('tenant_group_id');
                if ($tenant['active_tenant_group']->count() <= 0) {
                    $tenant['active_tenant_group'] = false;
                } else {
                    $tmpTenantGroupList = [];
                    foreach ($tenant['active_tenant_group'] as $value) {
                        $tmpTenantGroupList[] = (int) $value;
                    }
                    $tenant['active_tenant_group'] = $tmpTenantGroupList;
                }
            } else {
                $tenant['active_tenant'] = false;
            }
        }

        // $tenant['tenant_list'] = Tenant::all();

        return response()->json($tenant);
    }

    /**
     * list tenant group
     *
     * @param Request $request *semua optional
     *      group_app : apps id / path nya
     *
     * @return array list tenant group
     */
    public function tenantGroupList(Request $request)
    {
        $data = TenantGroup::get();
        return response()->json($data);
    }

    /**
     * list tenant group
     *
     * @param Request $request *semua optional
     *      group_app : apps id / path nya
     *
     * @return array list tenant
     */
    public function listTenant(Request $request)
    {
        $this->output['data'] = Tenant::all();
        return $this->done();
    }
}
