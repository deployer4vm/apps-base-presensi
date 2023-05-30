<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use App\Jobs\GenerateBackup;

class RunBackup extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synapse:runbackup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'run backup';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        GenerateBackup::dispatch();

        $this->info('SUCCESS!');
    }
}
