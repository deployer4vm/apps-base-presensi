<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

// use Exception;
use Throwable;

/**
 * Bagian dari general Excel Import functionality (ResImportTraits)
 * opsi handling proses import excel menggunakan background proses
 */
class ResImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $repo,$startRow,$addsJobsParam,$resumeParam,$tenantId;
    public $tries = 1;
    // public $backoff = 10;
    public $timeout = 36000;

    /**
     * Create a new job instance.
     *
     * @param ResImport $repo instance ResImport
     * @param array $addsJobsParam tambah parameter yang akan di passing ke initImportOnJon
     *
     * @return void
     */
    public function __construct($repo,int $startRow=2,array $addsJobsParam = [],array $resumeParam = [],$tenantId=0)
    {
        $this->repo = $repo;
        $this->startRow = $startRow;
        $this->addsJobsParam = $addsJobsParam;
        $this->resumeParam = $resumeParam;
        $this->tenantId = $tenantId?$tenantId:config('tenant.id',0);
    }

    public function failed(Throwable $error)
    {
        $repo = new $this->repo;
        $repo->initImportOnJob($this->addsJobsParam);
        $repo->setImportJobFailed($error);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $repo = new $this->repo;
        $repo->setImportTenantId($this->tenantId);
        $repo->initImportOnJob($this->addsJobsParam);
        $repo->setImportStartRow($this->startRow);
        $repo->setImportAsResume($this->resumeParam);
        $repo->importProcess();
    }
}
