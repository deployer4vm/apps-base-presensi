<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Exception;

// use Exception;
use Throwable;

/**
 * Bagian dari general Excel Export functionality (ResExportTraits)
 * opsi handling proses export excel menggunakan background proses
 */
class ResExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $repo,$addsJobsParam,$homeUrl,$resumeParam,$tenantId;
    public $tries = 1;
    // public $backoff = 10;
    public $timeout = 36000;

    /**
     * Create a new job instance.
     *
     * @param ResExport $repo instance RestExport
     * @param array $addsJobsParam tambah parameter yang akan di passing ke initExportOnJon
     * @param string $homeUrl url lengkap ke home index
     * @param array $resumeParam parameter ygn diinpunkan jika berupa proses resume
     *
     * @return void
     */
    public function __construct($repo,array $addsJobsParam = [],string $homeUrl='',array $resumeParam = [],$tenantId=0)
    {
        $this->repo = $repo;
        $this->addsJobsParam = $addsJobsParam;
        $this->homeUrl = $homeUrl;
        $this->resumeParam = $resumeParam;
        $this->tenantId = $tenantId?$tenantId:config('tenant.id',0);
    }

    public function failed(Throwable $error)
    {
        $repo = new $this->repo;
        $repo->initExportOnJob($this->addsJobsParam);
        $repo->setExportHomeUrl($this->homeUrl);
        $repo->setExportJobFailed($error);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{
            $repo = new $this->repo;
            $repo->setExportTenantId($this->tenantId);
            $repo->initExportOnJob($this->addsJobsParam);
            $repo->setExportHomeUrl($this->homeUrl);
            $repo->setExportAsResume($this->resumeParam);
            $repo->processExport();

        } catch (Exception $e) {
            Log::error('ResExport ERROR : '.$e->getMessage());
            throw $e;
        }
    }
}
