<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Services\Utilities;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureEtaskRateLimiting();

        if ($this->app->runningInConsole()) {
            $this->bootMigration();
        } else if (config('AppConfig.system.multitenant.active', false)) {
            // set storage config
            $this->bootStorage();
        }

        parent::boot();
    }

    private function configureEtaskRateLimiting(): void
    {
        RateLimiter::for('etask-login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(8)->by('etask-login-email:' . hash('sha256', $email)),
                Limit::perMinute(60)->by('etask-login-ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('etask-verify', function (Request $request) {
            $flowToken = (string) $request->input('flow_token', '');

            return [
                Limit::perMinute(10)->by('etask-verify-flow:' . hash('sha256', $flowToken)),
                Limit::perMinute(120)->by('etask-verify-ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('etask-otp', function (Request $request) {
            $flowToken = (string) $request->input('flow_token', '');

            return [
                Limit::perMinute(3)->by('etask-otp-flow:' . hash('sha256', $flowToken)),
                Limit::perMinute(60)->by('etask-otp-ip:' . $request->ip()),
            ];
        });
    }

    private function bootStorage()
    {
        // tambah prefix untuk multi tenant
        $tmpConfig = config('filesystems.disks.local');
        $tmpConfig['root_old'] = $tmpConfig['root'];
        $tmpConfig['root'] = $tmpConfig['root'] . '/tenant_' . config('tenant.id', 0);
        app()->config['filesystems.disks.local'] = $tmpConfig;

        // jika tenant aktif menggunakan s3 storage, maka set default dan public jadi s3 stroage bersangkutan
        if (config('tenant.s3storage', 0)) {
            // set public storage ke s3 public tenant
            $tmpConfig = config('filesystems.disks.s3_' . config('tenant.s3storage'));
            if($tmpConfig){
                // set default ke s3 tenant
                app()->config['filesystems.default'] = 's3_' . config('tenant.s3storage');

            // jika tidak multi server maka gunakan default s3
            }else{
                $tmpConfig = config('filesystems.disks.s3');
                // set default ke s3 tenant
                app()->config['filesystems.default'] = 's3';
            }
            
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

    /**
     * tambah migration path disetiap module
     */
    private function bootMigration()
    {
        //boot additional data type migration
        try {
            \Doctrine\DBAL\Types\Type::addType('double', \App\Base\DoctrineType\DoubleType::class);
            \Doctrine\DBAL\Types\Type::addType('tinyInteger', \App\Base\DoctrineType\TinyIntegerType::class);
        } catch (\Throwable $th) {
            //throw $th;
        }

        $this->loadMigrationsFrom(config('hpsynapse.migration_path'));
    }

    /**
     * Register
     */
    public function register()
    {
        require_once app_path('Helpers/Helper.php');

        parent::register();

        // $this->mergeConfigFrom(
        //     __DIR__ . '/../config/HPSynapse.php',
        //     config_path('hpsynapse.php')
        // );
        // $this->app->singleton('breadcrumb', function ($app) {
        //     return new \hpsynapse\appscore\Services\Breadcrumb();
        // });
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        // if(!$this->app->runningInConsole())
        $this->registerControllerNamespace();
    }

    /**
     * initiate route untuk Admin area Vue Frontend di web akses
     */
    protected function registerControllerNamespace()
    {
        $controllerPaths = array_merge(
            config('hpsynapse.controller_path.pertenant.' . config('tenant.id', 0), []),
            config('hpsynapse.controller_path.general', []),
        );

        foreach ($controllerPaths as $namespace => $pathToModule) {

            $fileNames = [
                'routes_api' => true,
                'routes' => false
            ];

            $moduleNamespace = explode("\\", trim($namespace, "\\"));
            $moduleNamespace = array_pop($moduleNamespace);
            // $moduleNamespace = strtolower($moduleNamespace);

            $namespace .= 'Controllers';

            //load seluruh routes yg ada di setiap module
            foreach ($fileNames as $fileName => $isApi) {
                $path = sprintf('%s' . DIRECTORY_SEPARATOR . '%s.php', $pathToModule, $fileName);

                // var_dump([$namespace,$path]);echo('<br><br>');

                //load general route tambahan jika ada
                if (
                    $pathBinding = config(
                        'AppConfig.binding.route.' . $moduleNamespace . '.' . ($isApi ? 'api' : 'web'),
                        false
                    )
                ) {
                    $pathBinding = app_path('MainApp' . DIRECTORY_SEPARATOR . $pathBinding);
                    if (file_exists($pathBinding)) {
                        Route::middleware($isApi ? ['api'] : ['web'])
                            ->prefix(
                                $isApi && $moduleNamespace != 'moduser'
                                    ? config('AppConfig.endpoint.laravel.api.' . $moduleNamespace)
                                    : ''
                            )
                            ->namespace($namespace)
                            ->group($pathBinding);
                    }
                }

                if (!file_exists($path)) {
                    continue;
                }

                // Log::debug([$moduleNamespace, $namespace, $path, config('AppConfig.endpoint.laravel.api.' . $moduleNamespace)]);
                // register router utama per module
                Route::middleware($isApi ? ['api'] : ['web'])
                    ->prefix(
                        $isApi && $moduleNamespace != 'moduser'
                            ? config('AppConfig.endpoint.laravel.api.' . $moduleNamespace)
                            : ''
                    )
                    ->namespace($namespace)
                    ->group($path);
            }
        }
        // dd($controllerPaths);

        $this->mapApiRoutes();
        $this->mapWebRoutes();

        //jika full_vue aktif maka load route config nya
        if (config('AppConfig.system.web_admin.full_vue'))
            $this->mapWebFullVueRoutes();
    }

    /**
     * full vue web routes
     *
     * @return void
     */
    protected function mapWebFullVueRoutes()
    {
        $adminEndpoint = config('AppConfig.endpoint.laravel.admin.app');
        if ($adminEndpoint != '/' && !empty($adminEndpoint)) {
            Route::middleware('web')
                ->get($adminEndpoint, function () {
                    return view('layouts.full_vue.main');
                });
        } else {
            $adminEndpoint = '';
        }
        Route::middleware('web')
            ->get($adminEndpoint . '{any}', function () {
                return view('layouts.full_vue.main');
            })->where('any', '.*');
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix(config('AppConfig.endpoint.laravel.api.app'))
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/api.php'));
    }
}
