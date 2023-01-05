<?php

namespace App\Jobs;

use Exception;
use Throwable;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

use App\Facades\Import as FImport;

/**
 * Bagian dari general Excel Import functionality (Import)
 */
class Import implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $cacheKey,$curQueue,$startTime;

    public $tries = 1;
    // public $backoff = 10;
    public $timeout = 3600000;//1000 jam

    /**
     * Create a new job instance.
     *
     * @param String $cacheKey key id jobs nya
     *
     * @return void
     */
    public function __construct($cacheKey,$curQueue='import1')
    {
        $this->cacheKey = $cacheKey;
        $this->curQueue = $curQueue;
        $this->startTime = now()->format('Y-m-d H:i:s');
    }

    public function failed(Throwable $error)
    {
        FImport::setImportJobFailed($this->cacheKey,$error);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            FImport::processImport($this->cacheKey,$this->curQueue);
        } catch (Exception $e) {
            Log::error('Import Jobs '.$this->cacheKey.' ERROR : '.$e->getMessage());
            throw $e;
        }
    }
}
