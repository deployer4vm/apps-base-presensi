<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;

class Project extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'synapse:setproject '
        . '{projectCode : project code} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set active project yang digunakan';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $projectCode = $this->argument('projectCode');

        $projectConfigPath = 'app/MainApp/Project/' . $projectCode . '/config';
        if (file_exists(base_path($projectConfigPath . '/clientEnv.json'))) {
            //copy env var ke main app
            $data = json_decode(
                file_get_contents(base_path($projectConfigPath . '/clientEnv.json')),
                true
            );
            file_put_contents(
                base_path('app/MainApp/config/clientEnv.json'),
                json_encode($data, JSON_PRETTY_PRINT)
            );

            $data = json_decode(
                file_get_contents(base_path($projectConfigPath . '/packageLocalEnv.json')),
                true
            );
            file_put_contents(
                base_path('app/MainApp/config/packageLocalEnv.json'),
                json_encode($data, JSON_PRETTY_PRINT)
            );

            $data = json_decode(
                file_get_contents(base_path($projectConfigPath . '/systemEnv.json')),
                true
            );
            file_put_contents(
                base_path('app/MainApp/config/systemEnv.json'),
                json_encode($data, JSON_PRETTY_PRINT)
            );
            //config cache
            Artisan::call('config:cache');
            $this->info('Change project to : ' . $projectCode);
        } else {
            $this->error('Project Code tidak ditemukan!');
        }
    }
}
