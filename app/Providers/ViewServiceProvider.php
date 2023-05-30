<?php

namespace App\Providers;

// use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;

use Illuminate\Support\Facades\DB;
use Illuminate\View\ViewServiceProvider as BaseViewServiceProvider;

class ViewServiceProvider extends BaseViewServiceProvider
{

    public function register()
    {
        $tenantId = 0;
        // get tenant id jika dijalan di http bukan console
        if (
            !$this->app->runningInConsole() // jika dijalan di http
            && config('AppConfig.system.multitenant.active', false) // jika multi tenant aktif
            && !config('tenant', false) // jika tenant belum terset
        )
            $tenantId = $this->getTenantId();

        // $this->app['config']['view.paths'] = array_merge(config('hpsynapse.view_path',[]),$this->app['config']['view.paths']);
        $this->app['config']['view.paths'] = array_merge(
            config('hpsynapse.view_path.pertenant.' . $tenantId, []),
            config('hpsynapse.view_path.general', [])
        );

        parent::register();
    }

    private function getTenantId()
    {
        $tenant = false;

        // jika detect by subfolder
        if (config('AppConfig.system.multitenant.detect_mode', 1) == 1) {
            $appGroup = request()->header('Group-App', false)
                ?: request()->route('group_app', false)
                ?: request()->input('group_app', false);
            // if (!($appGroup = request()->header('Group-App', false))) {
            //     if (!($appGroup = request()->route('group_app', false))) {
            //         $appGroup = request()->input('group_app', false);
            //     }
            // }

            // jika mengakses aplikasi tenant manager
            if (
                (!$appGroup && config('AppConfig.system.multitenant.owner_subfolder', '') == '')
                || ($appGroup && $appGroup == config('AppConfig.system.multitenant.owner_subfolder'))
            ) {
                // ... do nothing
                // jika mengakses aplikasi per tenant
            } else {
                $tenant = DB::table('tenants')->where('group_app', $appGroup)->first();
            }
            // jika detect by subdomain
        } else {
            $domain = request()->getHttpHost();
            $tenant = DB::table('tenants')->where('domain', $domain)->first();
        }

        return $tenant ? $tenant->id : 0;
    }
}
