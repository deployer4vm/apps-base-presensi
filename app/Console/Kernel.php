<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use App\Jobs\PruneTelescope;
use App\Services\Utilities;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1

        // $schedule->command('synapse:syshealthcheck')->everyMinute();
        $schedule->command('synapse:calculateTenantResource')->everySixHours();

        // eksekusi garbage collector post reference tiap tengah malam
        $schedule->command('synapse:postRefGc')->daily();
        
        // jalankan queue worker jika mode nya menggunakan scheduler
        if (config('AppConfig.system.jobs.worker_mode', 1) == 1)
            $this->runQueueWorker($schedule);

        // jika websockets aktif maka aktifkan worker server socket nya
        if (config('AppConfig.packageLocal.moduser.broadcast.local_server_enabled')) {
            $schedule->command('websockets:serve')->everyMinute()->withoutOverlapping();
        }

        // run telescope prune 1 minggu sekali (sunday at 00:00)
        $schedule->job(new PruneTelescope)->weekly()->withoutOverlapping();
    }

    protected function runQueueWorker(&$schedule)
    {
        $queueList = Utilities::listQueueCommand();

        foreach ($queueList as $command)
            $schedule->command($command)->everyMinute()->withoutOverlapping();

        // entah kenapa karena sering error jadi restart aja queuenya tiap setangah jam
        $schedule->command('queue:restart')->everyThirtyMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(app_path('MainApp/Console/Commands'));
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
