<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

use App\Facades\Tenant;

/**
 * generate list tenant id ke file _tenants.json di config
 */
class CalculateTenantResource extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synapse:calculateTenantResource';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synapse - calculate tenant resource';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tenants = DB::table('tenants')->select('id')->get();
        foreach ($tenants as $tenant) {
            $tmpStorage = Tenant::storageSize($tenant->id);
            $tmpDb = Tenant::getDbSize($tenant->id);
            DB::table('tenants')->where('id', $tenant->id)->update([
                'res_storage_size' => $tmpStorage,
                'res_db_size' => $tmpDb,
                'res_total' => $tmpDb + $tmpStorage,
                'res_storage_size_last_update' => now(),
                'res_db_size_last_update' => now(),
            ]);
        }

        $this->info('Renant resource recalculated !');
        $this->info('SUCCESS!');
    }
}
