<?php

namespace App\Jobs;

// use Exception;
use Throwable;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

use App\Facades\Export as FExport;

/**
 * Bagian dari general Excel Export functionality (Export)
 */
class Export implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $cacheKey,$curQueue,$startTime;

    public $tries = 1;
    // public $backoff = 10;
    public $timeout = 36000;

    /**
     * Create a new job instance.
     *
     * @param String $cacheKey key id jobs nya
     *
     * @return void
     */
    public function __construct($cacheKey,$curQueue='export1')
    {
        $this->cacheKey = $cacheKey;
        $this->curQueue = $curQueue;
        $this->startTime = now()->format('Y-m-d H:i:s');
    }

    public function failed(Throwable $error)
    {
        Log::error('Export Jobs2 '.$this->cacheKey.' ERROR : '.$error->getMessage());
        FExport::setExportJobFailed($this->cacheKey,$error);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            FExport::processExport($this->cacheKey,$this->curQueue);
        } catch (Exception $e) {
            Log::error('Export Jobs '.$this->cacheKey.' ERROR : '.$e->getMessage());
            FExport::setExportJobFailed($this->cacheKey,$e);
            // throw $e;
        }
    }
}
