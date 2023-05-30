<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use App\Jobs\RestoreBackup;

class RunRestore extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synapse:runrestore '
        . '{backupDate : tanggal backup YYYY-MM-DD} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'runrestore';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $backupDate = $this->argument('backupDate');
        RestoreBackup::dispatch($backupDate);
        $this->info('SUCCESS!');
    }
}
