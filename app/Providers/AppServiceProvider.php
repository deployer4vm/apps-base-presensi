<?php

namespace App\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Mixins\RouterMixin;
use Illuminate\Pagination\Paginator;

use App\Facades\CacheConfig;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // spl_autoload_register([$this, 'autoLoad'],true, true);

        //load alias class
        if (config('AppConfig.binding.alias'))
            $this->app->booting(function () {
                $loader = \Illuminate\Foundation\AliasLoader::getInstance();
                $classList = config('AppConfig.binding.alias');
                foreach ($classList as $classAliasName => $className) {
                    $loader->alias($classAliasName, $className);
                }
            });

        // set public path sesuai config
        if(config('AppConfig.system.public_path')){
            $this->app->bind('path.public', function() {
                return realpath(__DIR__.'/../..'.config('AppConfig.system.public_path'));
            });
        }

        //bind interface global
        foreach (config('AppConfig.binding.interface', []) as $contract => $service) {
            $this->app->bind(
                $contract,
                $service
            );
        }

        //bind class rebind global
        foreach (config('AppConfig.binding.class', []) as $class => $newClass) {
            // $this->app->extend($class, function ($service, $app) use ($newClass) {
            //     return new $newClass($service);
            // });
            $this->app->bind($class, function ($app, $args) use ($newClass) {
                if (empty($args)) return new $newClass();
                return new $newClass(...$args);
            });
        }

        // bind config binding per tenant
        if (config('AppConfig.system.multitenant.active')) $this->bindTenant();
    }

    // public function autoLoad($className)
    // {
    //     if($className == 'hpsynapse\moduser\Facades\UserAuth' && !class_exists($className) && config('AppConfig.binding.class.'.$className,false)){
    //         $loader = \Illuminate\Foundation\AliasLoader::getInstance();
    //         $loader->alias($className, config('AppConfig.binding.class.'.$className));
    //     }
    // }

    protected function bindTenant()
    {
        $this->app->bind('bindTenant', function ($app, $params) {

            //load alias class
            $tenantConfig = 'AppConfig.system.binding.tenant.' . $params['tenant_id'];
            if (config($tenantConfig . '.alias', false)) {
                $this->app->booting(function () use ($params,$tenantConfig) {
                    $loader = \Illuminate\Foundation\AliasLoader::getInstance();
                    foreach (config($tenantConfig . '.alias', []) as $classAliasName => $className) {
                        $loader->alias($classAliasName, $className);
                    }
                });
            }

            //bind interface global
            foreach (config($tenantConfig . '.interface', []) as $contract => $service) {
                $this->app->bind(
                    $contract,
                    $service
                );
            }

            //bind class rebind global
            foreach (config($tenantConfig . '.class', []) as $controller => $newClass) {
                // $this->app->extend($class, function ($service, $app) use ($newClass) {
                //     return new $newClass($service);
                // });
                $this->app->bind($controller, function ($app, $args) use ($newClass) {
                    if (empty($args)) return new $newClass();
                    return new $newClass(...$args);
                });
            }
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();

        Schema::defaultStringLength(191);
        Router::mixin(new RouterMixin());
        bcscale(8);

        Validator::extend('cannot_empty', function ($attribute, $value, $parameters, $validator) {
            return !empty($value);
        });
        CacheConfig::setCacheEngine(config('cache.default','file'));
        if (isset($_GET['logquery'])) {
            $aksesId = rand() . '-' . hash_hmac('md5', now(), 'wek');
            DB::listen(function ($query) use ($aksesId) {
                // $query->sql
                // $query->bindings
                // $query->time
                // if(true){//stripos($query->sql,'absensi')!=false && stripos($query->sql,'select')===false){
                    $sql = str_replace('?', "'?'", $query->sql);
                    $sql = vsprintf(str_replace('?', '%s', $sql), $query->bindings);
                    Log::info('[QUERY] [' . $aksesId . '] '.$query->time.' : ' . $sql);
                
            });
        }
    }
}
