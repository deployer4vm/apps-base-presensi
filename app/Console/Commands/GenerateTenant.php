<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

/**
 * generate list tenant id ke file _tenants.json di config
 */
class GenerateTenant extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synapse:updateTenantList';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synapse - generate list tenant id ke file _tenants.json di config';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tenants = DB::table('tenants')->select(['id', 'db', 's3storage', 'status'])->get();
        $tenatIdList = [];
        foreach ($tenants as $tenant) {
            $tenatIdList[$tenant->id] = [
                'id' => $tenant->id,
                'db' => $tenant->db,
                's3storage' => $tenant->s3storage,
                'status' => $tenant->status,
            ];
        }

        if (file_put_contents(
            app_path('MainApp' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . '_tenant.json'),
            str_replace('\/', '/', json_encode($tenatIdList, JSON_PRETTY_PRINT))
        )) {
            $this->info('Regenerate _tenant.json !');
            $this->info('SUCCESS!');
        } else {
            $this->error('Regenerate _tenant.json FAILED!');
        }
    }
}
