<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use App\Jobs\RestoreBackup;
use \App\Facades\SystemCallback;
use App\Facades\Tenant;

/**
 * dischedulling tiap 5 menit
 */
class CallbackBg extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synapse:callbackbg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 're-callback yang gagal';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $listCallback = SystemCallback::listActiveCallBack();

        foreach ($listCallback as $key => $value) {
            Tenant::setActiveTenantById($value['tenant_id']);
            SystemCallback::retrySecureCallBack($value['id']);
        }
    }
}
