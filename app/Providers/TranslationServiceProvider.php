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

        // tambah prefix untuk multi tenant
        $tmpConfig = config('filesystems.disks.local');
        $tmpConfig['root_old'] = $tmpConfig['root'];
        $tmpConfig['root'] = $tmpConfig['root'] . '/tenant_' . config('tenant.id', 0);
        app()->config['filesystems.disks.local'] = $tmpConfig;

        // jika tenant aktif menggunakan s3 storage, maka set default dan public jadi s3 stroage bersangkutan
        if (config('tenant.s3storage', 0)) {
            // set default ke s3 tenant
            app()->config['filesystems.default'] = 's3_' . config('tenant.s3storage');

            // set public storage ke s3 public tenant
            $tmpConfig = config('filesystems.disks.s3_' . config('tenant.s3storage'));
            $tmpConfig['visibility'] = 'public';
            app()->config['filesystems.disks.public'] = $tmpConfig;
        } else {
            // tambah prefix untuk multi tenant
            $tmpConfig = config('filesystems.disks.public');
            $tmpConfig['root_old'] = $tmpConfig['root'];
            $tmpConfig['root'] = $tmpConfig['root'] . '/tenant_' . config('tenant.id', 0);
            app()->config['filesystems.disks.public'] = $tmpConfig;
        }

        // set domain khusus untuk cdn multi tenant
        $tmpConfig = config('filesystems.disks.alltenant');
        $tmpConfig['url'] = config(
            'AppConfig.system.multitenant.alltenant_storage_domain',
            config('AppConfig.system.multitenant.owner_domain', '')
        ) . $tmpConfig['url'];
        app()->config['filesystems.disks.alltenant'] = $tmpConfig;

        // set domain khusus untuk cdn multi tenant
        $tmpConfig = config('filesystems.disks.alltenant_public');
        $tmpConfig['url'] = config(
            'AppConfig.system.multitenant.alltenant_storage_domain',
            config('AppConfig.system.multitenant.owner_domain', '')
        ) . $tmpConfig['url'];
        app()->config['filesystems.disks.alltenant_public'] = $tmpConfig;
    }
}
