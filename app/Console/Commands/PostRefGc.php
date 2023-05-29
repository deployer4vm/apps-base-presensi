<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

use App\Services\Utilities;
use App\Facades\Tenant;

/**
 * generate list tenant id ke file _tenants.json di config
 */
class PostRefGc extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synapse:postRefGc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synapse - Post Reference Garbage collection';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $oneDayAgo = now()->subDays(1)->format('Y-m-d H:i:s');
        
        DB::table('post_references')
            ->where('status',0)
            ->where('created_at','<=',$oneDayAgo)
            ->delete();

        if(config('AppConfig.system.multitenant.active',false))
            $this->handleTenant($oneDayAgo);

        // $this->info('Post Reference Garabage Collected !');
        // $this->info('SUCCESS!');
    }

    public function handleTenant($oneDayAgo)
    {
        Utilities::loopTenant(function($tenant,$conTenant) use ($oneDayAgo) {
            
            if(!Tenant::tableExists('post_references',$tenant->id))
                return false;  
                
            DB::connection($conTenant)->table('post_references')
                ->where('status',0)
                ->where('created_at','<=',$oneDayAgo)
                ->delete();

            // $this->info('Execute tenant : '.$tenant->id);
            
        });
    }
}