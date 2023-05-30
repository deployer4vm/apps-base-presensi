<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use App\Facades\Backup;

use Carbon\Carbon;


class GenerateBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $now = now()->format('Y-m-d');
        $dbName = config('database.connections.mysql.database');
        $userName = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $backupPath = public_path('backup_file');
        $newBackupPath = public_path('backup_file'.DIRECTORY_SEPARATOR.$now);
        $uploadPath = public_path('upload');

        if(!file_exists($newBackupPath)){
            exec('cd "'.$backupPath.'" && mkdir "'.$now.'"');
        }

        //backup database
        exec('cd "'.$newBackupPath.'" && mysqldump -u '.$userName.' -p"'.$password.'" '.$dbName.' > db.sql');
        // exec('cd "'.$newBackupPath.'" && touch db.sql');
        //compress file upload
        exec('cd "'.$newBackupPath.'" && tar -C "'.$uploadPath.'" -zcvf upload.tar.gz .');
        //compress semua hasil backup
        exec('cd "'.$backupPath.'" && tar -C "'.$newBackupPath.'" -zcvf '.$now.'.tar.gz .');
        //delete semua file
        exec('rm -rf "'.$newBackupPath.'"');
        Backup::create([
            'backup_date'=>$now,
            'path'=>$backupPath.DIRECTORY_SEPARATOR.$now.'.tar.gz',
            'status'=>1
        ]);

    }
}
