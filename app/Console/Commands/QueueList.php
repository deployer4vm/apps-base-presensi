<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use App\Jobs\GenerateBackup;
use App\Services\Utilities;

class QueueList extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synapse:queuelist '
        . '{group? : group name (default, tenant, additional)} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get data list semua queue';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $group = $this->argument('group');

        if ($group && !in_array($group, ['default', 'tenant', 'additional']))
            $group = false;

        $queueList = Utilities::listQueueCommand($group ?: false);
        $projectPath = base_path() . DIRECTORY_SEPARATOR . 'artisan ';
        foreach ($queueList as $command)
            echo 'php ' . $projectPath . $command . "\n";
    }
}
