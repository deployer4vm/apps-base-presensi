<?php

use App\Services\Utilities;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

if (!function_exists('initHPsynapseConfig')) {
    function initHPsynapseConfig()
    {
        // Return blank config jika file belum tergenerate
        if (
            !file_exists(__DIR__ . '/../app/MainApp/config/_system.json')
            || !file_exists(__DIR__ . '/../app/MainApp/config/_client.json')
        ) {
            return [
                'bindings' => [
                    'controller' => [],
                    'interface' => [
                        // 'App\\Contracts\\UserLog' => 'App\\Services\\UserLog',
                        // 'App\\Contracts\\HybridAuth' => 'App\\Services\\HybridAuth',
                        'App\\Contracts\\Tenant' => 'App\\Services\\Tenant',
                        'App\\Contracts\\Excel' => 'App\\Services\\Excel',
                        'App\\Contracts\\Backup' => 'App\\Services\\Backup',
                        'App\\Contracts\\CacheConfig' => 'App\\Services\\CacheConfig',
                        'App\\Contracts\\DbConfig' => 'App\\Services\\DbConfig',
                        'App\\Contracts\\Helper' => 'App\\Services\\Helper',
                        'App\\Contracts\\Web' => 'App\\Services\\Web',
                        'App\\Contracts\\Trans' => 'App\\Services\\Trans',
                        'App\\Contracts\\Export' => 'App\\Services\\Export',
                        'App\\Contracts\\Import' => 'App\\Services\\Import',
                        'App\\Contracts\\PostReference' => 'App\\Services\\PostReference',
                        'App\\Contracts\\SystemCallback'=>'App\\Services\\SystemCallback'
                    ],
                    'route' => []
                ],
                'lang_path' => [], //language path
                'controller_path' => [], //controller path
                'view_path' => [], //blade view path
                'migration_path' => [], //migrations path
                'seed_path' => [], //seeds path
                /*
                * namespace ke path lokasi daftar module module
                *  NAMESPACE => [path_to_module_group, FILTER PREFIX
                */
                'namespaces' => [],
                'lib_namespace' => ['hpsynapse' => [base_path('vendor' . DS . 'hp-synapse'), 'lib-']],

                'resource_namespace' => '',

                'language_folder_name' => '',

                'view_folder_name' => '',

                /*
                * dev_package_path : path ke package disimpan secara fisik saat development
                * relative ke base_path()
                */
                'dev_package_path' => '../',
                'protection_middleware' => [],
                /*
                * struktur table default yang akan digenerate jika tidak mencantumkan
                * nama tabel saat generate
                */
                'generate_table_default' => [
                    'name' => 'varchar',
                    'description' => 'text'
                ],
                /*
                * field yang akan di hilangkan form dan list serta akan dimasukan
                * ke model guarded attribut
                */
                'generate_table_field_exclude' => [
                    'id', 'created_at', 'updated_at'
                ],
                /*
                * template layout utama yg akan di extend saat generate module
                */
                'generate_default_layout' => 'layouts.app',
                /*
                * view dari sidebar menu yang akan ditambahkan menu baru oleh system
                */
                'generate_sidebar_layouts' => 'layouts.adminsidebar',
                /*
                * tag html container menu sidebar yg akan ditambah
                */
                'generate_sidebar_menu_tag' => 'ul',
                /*
                * zappid dari tag container menu sidebar yg akan ditambah
                */
                'generate_sidebar_menu_id' => 'menusidebar'
            ];
        }

        $system = json_decode(
            file_get_contents(__DIR__ . '/../app/MainApp/config/_system.json'),
            true
        );
        $client = json_decode(
            file_get_contents(__DIR__ . '/../app/MainApp/config/_client.json'),
            true
        );

        $config = [
            'namespaces' => [
                'general' => [],
                'pertenant' => [],
                'all' => []
            ],
            'language_folder_name' => 'lang',
            'resource_namespace' => 'resources',
            'view_folder_name' => 'views',
        ];

        $mainAppProjectPath = 'MainApp' . DS . 'Projects' . DS . $client['project_code'];
        $mainAppClassPath = 'App\\MainApp\\Projects\\' . $client['project_code'];

        // jika multi project, maka masukan namespace project active nya
        if (
            isset($system['multiproject']['active'])
            && $system['multiproject']['active'] == 1
        ) {
            $config['namespaces']['general'][$mainAppClassPath . '\\Modules'] =
                [
                    app_path($mainAppProjectPath . DS . 'Modules') . DS,
                    false
                ];
        }

        // masukan default2 path dari yg paling priority di load ke yg less priority
        $config['namespaces']['general']['App\\MainApp\\Modules'] = [
            app_path('MainApp' . DS . 'Modules') . DS,
            false
        ];
        $config['namespaces']['general']['hpsynapse'] = [
            base_path('vendor' . DS . 'hp-synapse') . DS,
            ['mod-', 'apps-']
        ];

        $config['namespaces']['all'] = $config['namespaces']['general'];

        if (
            isset($system['multitenant']['active'])
            && $system['multitenant']['active'] == 1
        ) {
            $tenantConfigPath = __DIR__ . '/../app/MainApp/config/_tenant.json';

            if (!file_exists($tenantConfigPath))
                file_put_contents($tenantConfigPath, json_encode([], JSON_PRETTY_PRINT));

            // tenant list ini digunakan juga di process2 selanjutnya
            $tenantList = json_decode(file_get_contents($tenantConfigPath), true);

            foreach ($tenantList as $tenant) {
                $tenantId = isset($tenant['id']) ? $tenant['id'] : $tenant;
                $mainAppTenantPath = 'MainApp' . DS . 'Tenants' . DS . 'ID' . $tenantId;
                $mainAppProjectTenantPath = $mainAppProjectPath . DS  . 'Tenants' . DS . 'ID' . $tenantId;

                if (
                    isset($system['multiproject']['active'])
                    && $system['multiproject']['active'] == 1
                ) {
                    $config['namespaces']['pertenant'][$tenantId][$mainAppClassPath
                        . '\\Tenants\\ID'
                        . $tenantId
                        . '\\Modules'] =
                        [
                            app_path($mainAppProjectTenantPath . DS . 'Modules') . DS,
                            false
                        ];
                    $config['namespaces']['all'][$mainAppClassPath
                        . '\\Tenants\\ID'
                        . $tenantId
                        . '\\Modules'] =
                        [
                            app_path($mainAppProjectTenantPath . DS . 'Modules') . DS,
                            false
                        ];
                } else {
                    $config['namespaces']['pertenant'][$tenantId]['App\\MainApp\\Tenants\\ID' . $tenantId . '\\Modules'] =
                        [
                            app_path($mainAppTenantPath . DS . 'Modules') . DS,
                            false
                        ];
                    $config['namespaces']['all']['App\\MainApp\\Tenants\\ID' . $tenantId . '\\Modules'] = [
                        app_path($mainAppTenantPath . DS . 'Modules') . DS,
                        false
                    ];
                }
            }
        }

        /**
         * Load Controller path
         * ---------------------------------------------------------------------
         */
        //
        $controllerPath = ['general' => [], 'pertenant' => []];
        // load path general
        $tmpControllerPath = Utilities::listModulePath(
            $config['namespaces']['general'],
            function ($namespace, $pathToModule) {
                return [$namespace, $pathToModule];
            }
        );
        foreach ($tmpControllerPath as $key => $value) {
            $controllerPath['general'][$value[0]] = $value[1];
        }

        // load path pertenant
        if (isset($system['multitenant']['active']) && $system['multitenant']['active'] == 1) {

            foreach ($tenantList as $tenant) {
                $tenantId = isset($tenant['id']) ? $tenant['id'] : $tenant;

                $tmpControllerPath = Utilities::listModulePath(
                    $config['namespaces']['pertenant'][$tenantId],
                    function ($namespace, $pathToModule) {
                        return [$namespace, $pathToModule];
                    }
                );
                foreach ($tmpControllerPath as $key => $value) {
                    $controllerPath['pertenant'][$tenantId][$value[0]] = $value[1];
                }
            }
        }

        /**
         * Load LANG path
         * ---------------------------------------------------------------------
         */
        $langPath = ['general' => [], 'pertenant' => []];
        // load general
        $langPath['general'] = Utilities::findNamespaceResources(
            $config['namespaces']['general'],
            $config['language_folder_name'],
            $config['resource_namespace']
        );

        $langPath['general'] = array_merge(
            [
                resource_path('lang')
            ],
            $langPath['general']
        );

        $langPath['general'][] = app_path('MainApp' . DS . 'resources' . DS . 'lang');

        // load path pertenant
        if (isset($system['multitenant']['active']) && $system['multitenant']['active'] == 1) {
            foreach ($tenantList as $tenant) {
                $tenantId = isset($tenant['id']) ? $tenant['id'] : $tenant;

                $langPath['pertenant'][$tenantId] = Utilities::findNamespaceResources(
                    $config['namespaces']['pertenant'][$tenantId],
                    $config['language_folder_name'],
                    $config['resource_namespace']
                );
            }
        }


        /**
         * Load view path
         * ---------------------------------------------------------------------
         */
        $viewPath = ['general' => [], 'pertenant' => []];
        // load general
        $viewPath['general'] = Utilities::findNamespaceResources(
            $config['namespaces']['general'],
            $config['view_folder_name'],
            $config['resource_namespace']
        );
        $viewPath['general'] = array_merge(
            [
                base_path('resources' . DS . 'views'),
                app_path('MainApp' . DS . 'resources' . DS . 'views')
            ],
            $viewPath['general']
        );

        // load path pertenant
        if (isset($system['multitenant']['active']) && $system['multitenant']['active'] == 1) {
            foreach ($tenantList as $tenant) {
                $tenantId = isset($tenant['id']) ? $tenant['id'] : $tenant;

                $viewPath['pertenant'][$tenantId] = Utilities::findNamespaceResources(
                    $config['namespaces']['pertenant'][$tenantId],
                    $config['view_folder_name'],
                    $config['resource_namespace']
                );
            }
        }

        /**
         * Load migration path
         * ---------------------------------------------------------------------
         */
        $packageLocal = json_decode(
            file_get_contents(__DIR__ . '/../app/MainApp/config/_packageLocal.json'),
            true
        );
        $migrationModulePath = Utilities::listModulePath(
            $config['namespaces']['all'],
            function ($namespace, $pathToModule) use ($packageLocal) {
                $moduleNamespace = explode('\\', trim($namespace, '\\'));
                $moduleNamespace = array_pop($moduleNamespace);
                $pathToModule .= DS . 'database' . DS . 'migrations';
                if (
                    (!isset($packageLocal[$moduleNamespace]['database']['run_migration'])
                        || $packageLocal[$moduleNamespace]['database']['run_migration'] == 1
                    )
                    && file_exists($pathToModule)
                ) {
                    return $pathToModule;
                }
            }
        );

        /**
         * Set Migrations Path
         */

        $migrationPath = array_merge([database_path('migrations')], $migrationModulePath);

        $migrationPath[] = app_path('MainApp' . DS . 'database' . DS . 'migrations');

        // jika multi project maka load juga migration project nya
        if (isset($system['multiproject']['active']) && $system['multiproject']['active'] == 1)
            $migrationPath[] = app_path($mainAppProjectPath . DS . 'database' . DS . 'migrations');

        // jika multi tenant maka load path migration per tenant
        if (isset($system['multitenant']['active']) && $system['multitenant']['active'] == 1) {
            foreach ($tenantList as $tenant) {
                $tenantId = isset($tenant['id']) ? $tenant['id'] : $tenant;

                if (isset($system['multiproject']['active']) && $system['multiproject']['active'] == 1) {
                    $path = app_path(
                        $mainAppProjectTenantPath
                            . DS . 'database'
                            . DS . 'migrations'
                    );
                    if (file_exists($path))
                        $migrationPath[] = $path;
                } else {
                    $path = app_path(
                        $mainAppTenantPath
                            . DS . 'database'
                            . DS . 'migrations'
                    );
                    if (file_exists($path))
                        $migrationPath[] = $path;
                }
            }
        }

        /**
         * Set Seeds Path
         */

        $seedPath = array_merge([database_path('seeds')], $migrationModulePath);

        $seedPath[] = app_path('MainApp' . DS . 'database' . DS . 'seeds');

        // jika multi project maka load juga migration project nya
        if (isset($system['multiproject']['active']) && $system['multiproject']['active'] == 1)
            $seedPath[] = app_path($mainAppProjectPath . DS . 'database' . DS . 'seeds');

        // jika multi tenant maka load path migration per tenant
        if (isset($system['multitenant']['active']) && $system['multitenant']['active'] == 1) {
            foreach ($tenantList as $tenant) {
                $tenantId = isset($tenant['id']) ? $tenant['id'] : $tenant;

                if (isset($system['multiproject']['active']) && $system['multiproject']['active'] == 1) {
                    $path = app_path(
                        $mainAppProjectTenantPath
                            . DS . 'database'
                            . DS . 'seeds'
                    );
                    if (file_exists($path))
                        $seedPath[] = $path;
                } else {
                    $path = app_path(
                        $mainAppTenantPath
                            . DS . 'database'
                            . DS . 'seeds'
                    );
                    if (file_exists($path))
                        $seedPath[] = $path;
                }
            }
        }

        unset($config['namespaces']['all']);

        return [
            'bindings' => [
                'controller' => [],
                'interface' => [
                    // 'App\\Contracts\\UserLog' => 'App\\Services\\UserLog',
                    // 'App\\Contracts\\HybridAuth' => 'App\\Services\\HybridAuth',
                    'App\\Contracts\\Tenant' => 'App\\Services\\Tenant',
                    'App\\Contracts\\Excel' => 'App\\Services\\Excel',
                    'App\\Contracts\\Backup' => 'App\\Services\\Backup',
                    'App\\Contracts\\CacheConfig' => 'App\\Services\\CacheConfig',
                    'App\\Contracts\\DbConfig' => 'App\\Services\\DbConfig',
                    'App\\Contracts\\Helper' => 'App\\Services\\Helper',
                    'App\\Contracts\\Web' => 'App\\Services\\Web',
                    'App\\Contracts\\Trans' => 'App\\Services\\Trans',
                    'App\\Contracts\\Export' => 'App\\Services\\Export',
                    'App\\Contracts\\Import' => 'App\\Services\\Import',
                    'App\\Contracts\\PostReference' => 'App\\Services\\PostReference',
                    'App\\Contracts\\SystemCallback'=>'App\\Services\\SystemCallback'
                ],
                'route' => []
            ],
            'lang_path' => $langPath, //language path
            'controller_path' => $controllerPath, //controller path
            'view_path' => $viewPath, //blade view path
            'migration_path' => $migrationPath, //migrations path
            'seed_path' => $seedPath, //seeds path
            /*
            * namespace ke path lokasi daftar module module
            *  NAMESPACE => [path_to_module_group, FILTER PREFIX
            */
            'namespaces' => $config['namespaces'],
            'lib_namespace' => ['hpsynapse' => [base_path('vendor' . DS . 'hp-synapse'), 'lib-']],

            'resource_namespace' => $config['resource_namespace'],

            'language_folder_name' => $config['language_folder_name'],

            'view_folder_name' => $config['view_folder_name'],

            /*
            * dev_package_path : path ke package disimpan secara fisik saat development
            * relative ke base_path()
            */
            'dev_package_path' => '../',
            'protection_middleware' => [],
            /*
            * struktur table default yang akan digenerate jika tidak mencantumkan
            * nama tabel saat generate
            */
            'generate_table_default' => [
                'name' => 'varchar',
                'description' => 'text'
            ],
            /*
            * field yang akan di hilangkan form dan list serta akan dimasukan
            * ke model guarded attribut
            */
            'generate_table_field_exclude' => [
                'id', 'created_at', 'updated_at'
            ],
            /*
            * template layout utama yg akan di extend saat generate module
            */
            'generate_default_layout' => 'layouts.app',
            /*
            * view dari sidebar menu yang akan ditambahkan menu baru oleh system
            */
            'generate_sidebar_layouts' => 'layouts.adminsidebar',
            /*
            * tag html container menu sidebar yg akan ditambah
            */
            'generate_sidebar_menu_tag' => 'ul',
            /*
            * zappid dari tag container menu sidebar yg akan ditambah
            */
            'generate_sidebar_menu_id' => 'menusidebar'
        ];
    }
}

return initHPsynapseConfig();
