<?php

use Illuminate\Database\Seeder;
use App\Services\Utilities;

use App\Models\Seed;
use App\Facades\Tenant;
// use Exception;

class DatabaseSeeder extends Seeder
{
    public $tenantId = 0;
    
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $run = true;
        $addToSeed = true;

        // $run = false;
        // $addToSeed = false;

        /**
         * Load seed per module
         */
        $namespaces = config('hpsynapse.namespaces.general', []);
        $nameSpaceTenant = config('hpsynapse.namespaces.pertenant', []);
        foreach ($nameSpaceTenant as $key => $value) {
            foreach ($value as $key => $value2) {
                $namespaces[] = $value2;
            }
        }
        $modulePath = Utilities::listModulePath($namespaces, function ($namespace, $pathToModule) {
            $moduleNamespace = explode('\\', trim($namespace, '\\'));
            $moduleNamespace = array_pop($moduleNamespace);
            // $moduleNamespace = strtolower($moduleNamespace);

            $pathToModule .= DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'SeedList.php';
            if (config('AppConfig.packageLocal.' . $moduleNamespace . '.database.run_seed', true) && file_exists($pathToModule)) {
                return $pathToModule;
            }
        });
        $moduleSeeds = [];
        foreach ($modulePath as $value) {
            if ($value) $moduleSeeds = array_merge($moduleSeeds, include($value));
        }

        /**
         * Load seed project, dan merge kan dengan seed per module yg sebelumnya diload
         */
        $projectSeeds = include(app_path('MainApp/database/SeedList.php'));
        $projectSeeds = array_merge($moduleSeeds, $projectSeeds);

        /**
         * Eksekusi semua seeds yang terdetek dan belum dieksekusi sebelumnya
         */
        $runAbleSeeds = [];
        foreach ($projectSeeds as $class) {
            if (!Seed::where('seed', $class)->exists()) {
                $runAbleSeeds[] = $class;
                // try {
                //     $this->call($class);
                //     Seed::create(['seed'=>$class]);
                // } catch (Exception $th) {
                //     throw $th;
                // }

            }
        }

        if (empty($runAbleSeeds)) {
            if (isset($this->command)) {
                $this->command->getOutput()->writeln("<error>No new seed detected !</error>");
            } else {
                echo 'No new seed detected !';
            }
            return;
        }

        //jika mode nya 1 tenant 1 database atau 1 tenant beda table
        if (config('AppConfig.system.multitenant.active', false) && config('AppConfig.system.multitenant.data_mode', 1) != 1) {

            $this->callPerTenant($runAbleSeeds,$run, $addToSeed);

            // jika dalam 1 database utama
        } else {
            foreach ($runAbleSeeds as $class) {
                try {
                    if($run)
                        $this->call($class);

                    if($addToSeed)
                        Seed::create(['seed' => $class]);
                } catch (Exception $th) {
                    throw $th;
                }
            }
        }
    }

    /**
     * jika seed multi tenant
     */
    public function callPerTenant($runAbleSeeds,$run=true,$addToSeed=true)
    {
        ini_set('memory_limit', '5524M');

        $filter = isset($this->tenantId) ? [['id', $this->tenantId]] : [];
        $tenantList = Tenant::listTenant($filter);

        foreach ($runAbleSeeds as $class) {
            $tmpClass = new $class;

            if (isset($this->command)) {
                $this->command->getOutput()->writeln("<comment>Seeding:</comment> {$class}");
            }

            // jika seed per tenant maka jalankan per tenant
            if (method_exists($tmpClass, 'setTenantId')) {
                foreach ($tenantList['data'] as $tenant) {

                    //jika per database
                    if (config('AppConfig.system.multitenant.data_mode', 1) == 3) {
                        if (Tenant::dbExists($tenant['id'])) {

                            $startTime = microtime(true);

                            Tenant::setActiveTenantById($tenant['id']);

                            // if(method_exists($tmpClass,'setTenantId'))
                            $tmpClass->setTenantId($tenant['id']);

                            try {
                                if($run)
                                    $tmpClass->run();
                            } catch (Exception $th) {
                                throw $th;
                            }

                            $runTime = round(microtime(true) - $startTime, 2);
                            if (isset($this->command)) {
                                $this->command->getOutput()->writeln("<info>Seeded in tenant " . $tenant['id'] . ":</info>  {$class} ({$runTime} seconds)");
                            }

                            usleep(100);
                        }

                        // jika per table pake prefix nama table
                    } else {
                        // TO DO - seed tenant per table
                    }
                }
            } else {
                if($run)
                    $tmpClass->run();
            }

            // tambahkan class seed yg sudah dieksekusi
            if($addToSeed)
                Seed::create(['seed' => $class]);
        }
    }
}
