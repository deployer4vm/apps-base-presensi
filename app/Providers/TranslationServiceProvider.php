<?php

namespace App\Providers;

use Illuminate\Translation\TranslationServiceProvider as BaseTranslationServiceProvider;
use App\Services\Translation\DistributedFileLoader;

class TranslationServiceProvider extends BaseTranslationServiceProvider
{

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader()
    {
        if (config('AppConfig.system.multitenant.active', false))
            $this->AppGroupCheck();

        $langPath = array_merge(
            config('hpsynapse.lang_path.general', []),
            config('hpsynapse.lang_path.pertenant.' . config('tenant.id', 0), [])
        );

        $this->app->singleton('translation.loader', function ($app) use ($langPath) {
            return new DistributedFileLoader($app['files'], $langPath);
        });
    }
    
    private function AppGroupCheck()
    {
        if (config('AppConfig.system.multitenant.detect_mode', 1) == 1) {
            // jika detect by subfolder
            \App\Facades\Tenant::setActiveTenantByGroup();
        } else {
            // jika detect by subdomain/domain
            \App\Facades\Tenant::setActiveTenantByDomain();
        }
    }
}
