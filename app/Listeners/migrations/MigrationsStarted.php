<?php

namespace App\Listeners\migrations;

use Exception;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

// use hpsynapse\moduser\Facades\UserAuth;
// use App\Facades\CacheConfig;
// use App\Facades\DbConfig;

// use App\MainApp\Modules\DigitalPayment\Facades\Transaksi;
// use App\MainApp\Modules\DigitalPayment\Facades\DigitalPayment;

/**
 * saat data user berhasil diubah
 */
class MigrationsStarted
{
    use ResetModelCacheTraits;

    /**
     * @param  object  $event
     *
     * @return void
     */
    public function handle($event)
    {
        // $this->resetCache('MigrationsStarted',$event);
    }
}
