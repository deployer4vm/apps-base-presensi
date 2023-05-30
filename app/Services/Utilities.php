<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;

use App\Models\Tenant as MTenant;
use App\Facades\Tenant;

class Utilities
{
    /**
     * looping per module per module namespace nya (letak modul bisa dimana saja)
     *
     * @param
     *
     * @return
     */
    public static function findNamespaceResources(array $namespaces, $resourceFolderName, $resourceNamespace)
    {
        return array_reduce(
            $namespaces,
            function ($carry, $namespacePath) use ($resourceNamespace, $resourceFolderName) {
                $modulePrefix = $namespacePath[1];
                $components = glob(sprintf('%s*', $namespacePath[0]), GLOB_ONLYDIR);
                $isModuleOk = true;
                $paths = array_map(function ($component) use (
                    $resourceNamespace,
                    $resourceFolderName,
                    $modulePrefix,
                    $isModuleOk
                ) {

                    if ($modulePrefix) {

                        $moduleName = substr($component, strrpos($component, DIRECTORY_SEPARATOR) + 1);

                        if (!is_array($modulePrefix)) $modulePrefix = [$modulePrefix];
                        $skipModule = true;
                        foreach ($modulePrefix as $val) {
                            if (strpos($moduleName, $val) === 0)
                                $skipModule = false;
                        }
                        if ($skipModule)
                            return false;

                        $component .= DIRECTORY_SEPARATOR . 'src';
                    }

                    $path = [$component];

                    if (!empty($resourceNamespace)) {
                        $path[] = $resourceNamespace;
                    }

                    $path[] = $resourceFolderName;

                    $path = implode(DIRECTORY_SEPARATOR, $path);

                    return is_dir($path) ? $path : false;
                }, $components);

                return array_merge($carry, array_filter($paths));
            },
            []
        );
    }

    /*
     * looping per module per module namespace nya (letak modul bisa dimana saja)
     */
    public static function listModulePath(array $namespaces, $func)
    {
        $return = [];

        //$namespace = namespace
        //$path[0] = path ke namespace
        //$path[1] = array prefix directory yang ada di path bersangkutan, false jika tanpa prefix
        foreach ($namespaces as $namespace => $path) {
            $tmp = glob(sprintf('%s*', $path[0]), GLOB_ONLYDIR);
            foreach ($tmp as $modulePath) {
                //$component : nama/folder module nya
                $component = substr($modulePath, strrpos($modulePath, DIRECTORY_SEPARATOR) + 1);
                //cek jika ada prefix maka hanya ambil path yg sesuai prefix nya saja
                if ($path[1]) {
                    if (!is_array($path[1])) $path[1] = [$path[1]];
                    $skipModule = true;
                    foreach ($path[1] as $val) {
                        if (strpos($component, $val) === 0)
                            $skipModule = false;
                    }
                    if ($skipModule)
                        continue;
                }

                if ($namespace == 'hpsynapse') {
                    $modulePath .= DIRECTORY_SEPARATOR . 'src';
                }

                //$newNamespace : namespace ke folder per modulenya App/Modules/NAMAMODULE
                $newNamespace = sprintf(
                    '%s\\%s\\',
                    $namespace,
                    str_replace('-', '', $component)
                );

                $tmpfunc = $func($newNamespace, $modulePath);
                if ($tmpfunc)
                    $return[] = $tmpfunc;
            }
        }
        return $return;
    }

    /**
     * eksekusi perintah artisan
     *
     * @param string $command perintah artisan, misal 'config:cache'
     * @return array
     */
    public static function artisan($command = '')
    {
        //list perintah artisan yg hanya bisa dieksekusi langsung via command line, tidak bisa via class Artisan
        $shellOnlyCommands = [
            'synapse:updateTenantList', //ditambahkan disini agar bisa dieksekusi tanpa bergantung pada facade Artisan
            'clear-compiled',
            'package:discover',
            'backup:run',
            'passport:client --password',
            'passport:install',
            'apidoc:generate',
            'route:list',
            'config:cache',
            'config:clear',
            'migrate',
            'db:seed',
            'route:cache',
            'route:clear',
            'view:cache',
            'view:clear',
            'optimize:clear',
            'optimize'
        ];
        $ret = '';
        if (in_array($command, $shellOnlyCommands)) {
            $ret = shell_exec('cd ' . base_path('') . ' && php artisan ' . $command);
        } else {
            Artisan::call($command);
            $ret = Artisan::output();
        }
        return ['command' => $command, 'return' => $ret];
    }

    /**
     * untuk mereset semua worker artisan scheduller
     *
     * @return array
     */
    public static function resetSchedulerWorker()
    {
        $ret = [];
        // kill all artisan
        $ret[] = shell_exec('pkill -f artisan');
        // reset cache
        $ret[] = self::artisan('optimize:clear');
        $ret[] = self::artisan('optimize:clear'); // 2 kali eksekusi untuk memastikan benar2 terhapus
        $ret[] = self::artisan('config:cache');

        return $ret;
    }

    /**
     * List queue command
     *
     * @param false|string    false view all, ID GROUP view queue per group,
     *                          ID GROUP : default, tenant, additional
     *
     * @return array    List command queue
     */
    public static function listQueueCommand($group = false)
    {
        // group : default
        if ($group == 'default' || $group == false) {
            $queueList = [
                'queue:work --tries=1 --queue=verification,email',
                'queue:work --tries=1 --queue=high,default,low'
            ];

            // aktifkan queue worker export general jika export_handler = 2 atau 3
            if (config('AppConfig.system.jobs.export_handler', 1) >= 2) {
                // Export Worker General
                $exportChildCount = config('AppConfig.system.jobs.export_worker', 2);
                for ($i = 1; $i <= $exportChildCount; $i++) {
                    $queueList[] = 'queue:work --tries=1 --queue=export' . $i;
                }
            }

            // aktifkan queue worker import general jika export_handler = 2 atau 3
            if (config('AppConfig.system.jobs.import_handler', 1) >= 2) {
                // Import Worker General
                $importChildCount = config('AppConfig.system.jobs.import_worker', 2);
                for ($i = 1; $i <= $importChildCount; $i++) {
                    $queueList[] = 'queue:work --tries=1 --queue=import' . $i;
                }
            }
        }
        if ($group == 'default') return $queueList;

        if ($group == 'additional' || $group == false) {
            // load queuetambahan jika ada, bisa digunakan untuk per tenant juga
            $queueAdds = config(
                'AppConfig.system.jobs.queue_addlist',
                config('AppConfig.system.jobs.queue_adds', [])
            );
            foreach ($queueAdds as $queue) {
                $queueList[] = 'queue:work --tries=1 --queue=' . $queue;
            }
        }
        if ($group == 'additional') return $queueList;

        if ($group == 'tenant' || $group == false) {
            // load queue tambahan per tenant jika aktif
            if (config('AppConfig.system.jobs.multitenant_add', false)) {
                $tenantList = config('AppConfig.tenant', []);
                foreach ($tenantList as $tenant) {
                    $tenantId = isset($tenant['id']) ? $tenant['id'] : $tenant;

                    // Worker per tenant
                    $queueList[] = 'queue:work --tries=1 --queue=tenant' . $tenantId;

                    // aktifkan queue worker export per tenant jika export_handler = 3
                    if (config('AppConfig.system.jobs.export_handler', 1) == 3) {
                        // Export Worker per tenant
                        for ($i = 1; $i <= $exportChildCount; $i++) {
                            $queueList[] = 'queue:work --tries=1 --queue=tenant' . $tenantId . 'export' . $i;
                        }
                    }

                    // aktifkan queue worker export per tenant jika export_handler = 3
                    if (config('AppConfig.system.jobs.import_handler', 1) == 3) {
                        // Import Worker per tenant
                        for ($i = 1; $i <= $importChildCount; $i++) {
                            $queueList[] = 'queue:work --tries=1 --queue=tenant' . $tenantId . 'import' . $i;
                        }
                    }
                }
            }
        }
        if ($group == 'tenant') return $queueList;

        return $queueList;
    }

    /**
     * Loop semua tenant
     *
     * @param Function $callBack            fungsi callback yang akan diloopigng nya
     * @param Array|Number $tenantIds       kosongkan jika looping seluruh tenant,
     *                                      atau isi dengan list id tenant
     */
    public static function loopTenant($callBack, $tenantIds = [])
    {
        if (empty($tenantIds)) {
            $tenantList = MTenant::get();
        } else {
            $tenantIds = is_array($tenantIds) ? $tenantIds : [$tenantIds];
            $tenantList = MTenant::whereIn('id', $tenantIds)->get();
        }

        foreach ($tenantList as $tenant) {
            if ($tenant->status) {
                if (!empty($tenantIds) && !in_array($tenant->id, $tenantIds))
                    continue;

                if (!Tenant::dbExists($tenant->id))
                    continue;

                Tenant::setDB($tenant->id);
                $conTenant = config('database.perTenant') . $tenant->id;

                $callBack($tenant, $conTenant);
            }
        }
    }
}
