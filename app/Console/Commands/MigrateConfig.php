<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

use App\Facades\Tenant;
use App\Facades\DbConfig;

use App\Models\MConfig;
use App\Models\MConfigTenant;

// * * * * * cd /home/aplikasikop/koperasiv15/synapse/ && ea-php74 artisan synapse:runqueue >> /dev/null 2>&1

/**
 * run queue job without scheduler
 */
class MigrateConfig extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synapse:configToTenant '    
        . '{tenantId? : id tenant} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synapse - migrate config utama ke per tenant';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tenantId = $this->argument('tenantId');
        $where = [];
        if($tenantId)
            $where[] = ['id',$tenantId];

        $tenantList = Tenant::listTenant($where);
        foreach ($tenantList['data'] as $tenant) {
            $configList = MConfig::where('tenant_id',$tenant['id'])->get();
            if(Tenant::dbExists($tenant['id'])){
                Tenant::setActiveTenantById($tenant['id']);
                echo "Tenant ID : ".$tenant['id']." - ".$configList->count()."\n";
                foreach ($configList as $config) {
                    DbConfig::setConfig(
                        $config->group,
                        $config->key,
                        $config->value,
                        $config->tenant_id,
                    );
                }
            }
        }
    }
}
