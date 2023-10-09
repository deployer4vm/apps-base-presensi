<?php

namespace App\Services;

// use Exception;
use Throwable;
use Carbon\Carbon;

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Box\Spout\Writer\Common\Creator\Style\BorderBuilder;
use Box\Spout\Common\Entity\Style\CellAlignment;
use Box\Spout\Common\Entity\Style\Color;
use Box\Spout\Common\Entity\Style\Border;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use App\Base\BaseRepository;

use App\Facades\Excel;
use App\Facades\Tenant;

use App\Models\Job;
use App\Models\Import as MImport;

use App\Jobs\Import as JImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Calculation\Logical\Boolean;

class Import extends BaseRepository
{
    protected $cacheActive = true;

    // 0 new process
    // 1 sudah diinput/dispatch ke jobs
    // 2 process import sudah / sedang berjalan
    // 3 process import selesai dan berhasil
    // 4 process import gagal (bisa gagal saat import pertama, ataupun gagal saat approve ataupun cancle approve)
    // 5 process approve import on progress
    // 6 process approve import berhasil
    // 7 process cancel approve import on progress
    // 8 process cancel approve import berhasil
    const IMPORT_STATUS_NEW = 0;
    const IMPORT_STATUS_DISPATCHED = 1;
    const IMPORT_STATUS_ON_PROGRESS = 2;
    const IMPORT_STATUS_SUCCESS = 3;
    const IMPORT_STATUS_FAILED = 4;
    const IMPORT_STATUS_APPROVE_ON_PROGRESS = 5;
    const IMPORT_STATUS_APPROVE_SUCCESS = 6;
    const IMPORT_STATUS_CANCEL_APPROVE_ON_PROGRESS = 7;
    const IMPORT_STATUS_CANCEL_APPROVE_SUCCESS = 8;

    //
    protected $_mainCacheKeyGroup = 'synapse.import'; //
    protected $_mainCacheKeyList = 'list'; //
    protected $_mainCacheKeyDetailPrefix = 'detail.'; //

    // format output file
    protected $outputHeaderStartRow = 1; //poisi baris header
    protected $outputHeader = []; //judul2 header

    /**
     * PUBLIC
     * -------------------------------------------------------------------------
     */


    /**
     * Convert data format record database, ke format cache lama (agar tidak banyak mengubah bisnis proses)
     *
     * @param array $data
     * @return array
     */
    private function convertDbToCache($data)
    {
        $newData = [
            'tenantId' => $data['tenant_id'],
            'userId' => $data['user_id'],
            'cacheKey' => $data['cache_key'],
            'jobsId' => $data['jobs_id'],
            'processId' => $data['process_id'],
            'queue' => $data['queue'],
            'jobDispatchTime' => $data['job_dispatch_time'],
            'jobStartTime' => $data['job_start_time'],
            'log' => $data['log'],
            'status' => $data['status'],
        ];
        $newData = array_merge($newData, $data['data']);
        return $newData;
    }

    /**
     * Convert data format cache lama, ke format record database (agar tidak banyak mengubah bisnis proses)
     *
     * @param array $data
     * @return array
     */
    private function convertCacheToDb($data)
    {
        $newData = [
            'tenant_id' => $data['tenantId'],
            'user_id' => $data['userId'],
            'cache_key' => $data['cacheKey'],
            'jobs_id' => $data['jobsId'],
            'process_id' => $data['processId'],
            'queue' => $data['queue'],
            'job_dispatch_time' => $data['jobDispatchTime'],
            'job_start_time' => $data['jobStartTime'],
            'log' => $data['log'],
            'status' => $data['status'],
        ];
        unset(
            $data['tenantId'],
            $data['userId'],
            $data['cacheKey'],
            $data['jobsId'],
            $data['processId'],
            $data['queue'],
            $data['jobDispatchTime'],
            $data['jobStartTime'],
            $data['log'],
            $data['status'],
        );
        $newData = array_merge($newData, ['data' => $data]);
        return $newData;
    }

    /**
     * List seluruh import
     *
     * @param array $filter Synapse Filter Array
     * @param array $orderBy Synapse Order Array
     */
    public function listImport($filter, $orderBy)
    {
        $list = $this->_list(new MImport, $filter, 0, 0, $orderBy);
        // $newData = [];
        // foreach($list['data'] as $v){
        //     $newData[] = $this->convertDbToCache($v);
        // }
        return $list['data'];
    }

    /**
     * Get Next Import Jobs Queue group
     *
     * @param integer $tenantId
     * @return string
     */
    public function nextImportQueue($tenantId = 0)
    {
        // $idxQueue=1;
        // while (Job::where('queue','import'.$idxQueue)->exists()) {
        //     $idxQueue++;
        //     // berarti semua penuh, maka tambahkan ke yg paling sedikit
        //     if($idxQueue>5){
        //         $ret = DB::select("SELECT * FROM (SELECT COUNT(*) AS 'jm_queue',`queue` FROM `jobs` WHERE `queue` LIKE 'import%' GROUP BY `queue`) AS tmp_table ORDER BY `jm_queue` ASC LIMIT 1");
        //         return isset($ret['queue'])?$ret['queue']:'import1';
        //     }
        // }
        // return 'import'.$idxQueue;


        $isPerTenant = config('AppConfig.system.jobs.multitenant_add', false)
            && !empty($tenantId)
            && $tenantId > 0
                ? true : false;

        // jika queue worker dihandle oleh default atau default per tenant
        if (config('AppConfig.system.jobs.import_handler', 1) == 1) {
            return $isPerTenant ? ('tenant' . $tenantId) : 'default';

            // jika queue worker dihandle oleh worker import genaral
        } else if (config('AppConfig.system.jobs.import_handler', 1) == 2) {
            $idxQueue = 1;
            while (Job::where('queue', 'import' . $idxQueue)->exists()) {
                $idxQueue++;
                // berarti semua penuh, maka tambahkan ke yg paling sedikit
                if ($idxQueue > config('AppConfig.system.jobs.import_worker', 2)) {
                    $ret = DB::select(
                        "SELECT * FROM ("
                            . "SELECT COUNT(*) AS 'jm_queue',`queue` FROM `jobs` "
                                . "WHERE `queue` LIKE 'import%' GROUP BY `queue`"
                        . ") AS tmp_table ORDER BY `jm_queue` ASC LIMIT 1"
                    );
                    return $ret['queue'] ?? 'import1';
                }
            }
            return 'import' . $idxQueue;

            // jika queue worker dihandle oleh import handle per tenant
        } else if (config('AppConfig.system.jobs.import_handler', 1) == 3 && $isPerTenant) {
            $idxQueue = 1;
            while (Job::where('queue', 'tenant' . $tenantId . 'import' . $idxQueue)->exists()) {
                $idxQueue++;
                // berarti semua penuh, maka tambahkan ke yg paling sedikit
                if ($idxQueue > config('AppConfig.system.jobs.import_worker', 2)) {
                    $ret = DB::select(
                        "SELECT * FROM ("
                            . "SELECT COUNT(*) AS 'jm_queue',`queue` FROM `jobs` "
                                . "WHERE `queue` LIKE 'tenant" . $tenantId . "import%' GROUP BY `queue`"
                        . ") AS tmp_table ORDER BY `jm_queue` ASC LIMIT 1"
                    );
                    return $ret['queue'] ?? ('tenant' . $tenantId . 'import1');
                }
            }
            return 'tenant' . $tenantId . 'import' . $idxQueue;
        }
    }

    /**
     * get data import
     *
     * @param string $cacheKey
     * @param boolean $reload Unused
     *
     * @return false|array false = gagal
     */
    public function getImport($cacheKey, $reload = true)
    {
        $tmp = MImport::where('cache_key', $cacheKey)->first();
        return $tmp ? $this->convertDbToCache($tmp->toArray()) : false;
    }

    /**
     * STEP 1 - yang harus diset pertama kali di controllernya (atau di tempat import ini dicreate/diinisasi)
     *
     * membuat process import baru
     *
     * @param string $cacheKey
     * @param string $importModel       String path model nya, tidak perlu diisi jika setCoreMainLooping digunakan
     * @param string $filepath          false jika didetek otomatis atau isi dengan full path excel yg diimport (sudah diupload dengan sempurna)
     * @param integer $dataStartRow
     * @param integer $userId
     * @param integer $tenantId
     * @param boolean $importApproval
     *
     * @return false|array false = gagal import
     */
    public function createImport(
        $cacheKey,
        $importModel = false,
        $filepath = false,
        $dataStartRow = 0,
        $userId = 0,
        $tenantId = 0,
        $importApproval = false
    ) {
        $request = request();

        if (empty($filepath)) {
            $tenantPath = ''; // config('AppConfig.system.multitenant.active',false)?('/tenant_'.config('tenant.id',0)):'';
            $filepath = $request
                ->file('importFile')
                ->store($tenantPath . '/system_import/' . $cacheKey . '/');
            $filepath = Storage::path($filepath);
        }

        $tenantId = $tenantId ?: config('tenant.id', 0);

        $importData = $this->getImport($cacheKey);
        $createNew = true;

        if ($importData) {
            $createNew = false;

            // jika sedang dalam proses
            if (
                $importData['status'] == self::IMPORT_STATUS_DISPATCHED
                || $importData['status'] == self::IMPORT_STATUS_ON_PROGRESS
                || $importData['status'] == self::IMPORT_STATUS_CANCEL_APPROVE_ON_PROGRESS
                || $importData['status'] == self::IMPORT_STATUS_APPROVE_ON_PROGRESS
            ) {
                $this->error = 'Jobs already exists';
                return false;
                // delete file sebelumnya
            } else {
                Storage::delete($importData['filepath']);
            }
        }

        try {
            $lastImport = $importModel::orderBy('import_id', 'DESC')->first();
            $importId = !$lastImport || $lastImport->import_id == 0 ? 1 : $lastImport->import_id++;
        } catch (\Exception  $e) {
            // jika error berarti ga set field import_id
            $importId = 1;
        }

        // set baru import
        $importData = $this->initImportStatus();
        $importData['importApproval'] = $importApproval ? 1 : $request->input('importApproval', 1);
        $importData['cacheKey'] = $cacheKey;
        $importData['importId'] = $importId;
        $importData['importModel'] = $importModel;
        $importData['filepath'] = $filepath;
        $importData['format']['dataStartRow'] = $dataStartRow;
        $importData['userId'] = $userId;
        $importData['tenantId'] = $tenantId;

        // tambah detail nya
        if ($createNew) {
            MImport::create($this->convertCacheToDb($importData));
        } else {
            $this->updateImport($cacheKey, $importData);
        }

        return $importData;
    }


    /**
     * STEP TERAKHIR - step terakhir di controllernya
     *
     * dispatch import ke queue untuk pertama kali
     * dieksekusi setelah createImport dan set-set config
     *
     * @param string $cacheKey
     *
     * @return false|array false jika gagal
     */
    public function dispatchImport($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['jobDispatchTime'] = now()->format('Y-m-d H:i:s');
        $importData['status'] = self::IMPORT_STATUS_DISPATCHED;
        $importData['queue'] = $this->nextImportQueue($importData['tenantId']);

        $this->updateImport($cacheKey, $importData);

        JImport::dispatch($cacheKey, $importData['queue'])->onQueue($importData['queue']);

        return $importData;
    }

    /**
     * BELUM DISET DI PROCESS NYA, JADI BELUM BERFUNGSI
     * cancel import yg sedang berjalan
     *
     * @param string $cacheKey
     * @return boolean false = Data Import tidak ada
     */
    public function stopImport($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;
        $importData['forceCancle'] = 1; //tandai sebagai force cancle
        $this->updateImport($cacheKey, $importData);

        return true;
    }

    /**
     * delete log import dan file hasil importnya
     *
     * @param string $cacheKey
     * @return boolean false = Data Import tidak ada
     */
    public function deleteImport($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        Storage::disk(Tenant::storageGetDiskLocal($importData['tenantId']))
            ->delete($importData['filepath']);

        MImport::where('cache_key', $cacheKey)->delete();
        return true;
    }

    /**
     * FUNGSI CONFIG SETTER SETELAH createImport
     * -------------------------------------------------------------------------
     */


    /**
     * set general parameter yang bisa digunakan untuk custom formating nantinya
     *
     * @param string $cacheKey
     * @param array $addsParam Additional Job Parameters
     *
     * @return boolean false = Data Import tidak ada
     */
    public function setAddsParam($cacheKey, $addsParam)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['addsParam'] = $addsParam;

        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * Untuk meng-OVERIDE main process
     * method-method untuk mengganti method utama dalam pemrosesan data
     * -------------------------------------------------------------------------
     */

    /**
     * fungsi untuk meng-OVERIDE looping data utama
     *
     * @param string $cacheKey key / kode unik per export
     * @param string $coreMainLoopingClass Class untuk menghandle data yang di-loop
     * @param string $coreMainLoopingMethod Method untuk mendhandle data yang di-loop
     * @return boolean false = Data Import tidak ada
     */
    public function setCoreMainLooping(
        $cacheKey,
        string $coreMainLoopingClass,
        string $coreMainLoopingMethod
    ) {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['format']['coreMainLoopingMethod'] = [
            $coreMainLoopingClass,
            $coreMainLoopingMethod
        ];

        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * set class dan method untuk memformat data per row
     *
     * @param string $cacheKey key / kode unik per export
     * @param string $coreRowFormaterClass Class untuk menghandle pemformatan row
     * @param string $coreRowFormaterMethod Method untuk mendhandle pemformatan row
     * @return boolean false = Data Import tidak ada
     */
    public function setCoreRowFormater(
        $cacheKey,
        string $coreRowFormaterClass,
        string $coreRowFormaterMethod
    ) {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['format']['coreRowFormaterMethod'] = [
            $coreRowFormaterClass,
            $coreRowFormaterMethod
        ];

        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * set class dan method untuk memformat excel $reader saat setelah beres semua
     *
     * @param string $cacheKey key / kode unik per export
     * @param string $coreLastFormaterClass Class untuk menghandle pemformatan row terakhir
     * @param string $coreLastFormaterMethod Method untuk mendhandle pemformatan row terakhir
     * @return boolean false = Data Import tidak ada
     */
    public function setCoreLastFormater(
        $cacheKey,
        string $coreLastFormaterClass,
        string $coreLastFormaterMethod
    ) {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['format']['coreLastFormaterMethod'] = [
            $coreLastFormaterClass,
            $coreLastFormaterMethod
        ];

        $this->updateImport($cacheKey, $importData);
        return true;
    }


    /**
     * set class dan method untuk handle proses approval
     *
     * @param string $cacheKey key / kode unik per export
     * @param string $coreApproveClass Class untuk menghandle proses approval
     * @param string $coreApproveMethod Method untuk mendhandle proses approval
     * @return boolean false = Data Import tidak ada
     */
    public function setCoreApprove($cacheKey, string $coreApproveClass, string $coreApproveMethod)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['format']['coreApproveMethod'] = [$coreApproveClass, $coreApproveMethod];

        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * set class jobs untuk approve
     *
     * @param string $cacheKey key / kode unik per export
     * @param string $coreApproveJob Class untuk menghandle job approval
     * @return boolean false = Data Import tidak ada
     */
    public function setCoreApproveJob($cacheKey, string $coreApproveJob)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['format']['coreApproveJob'] = $coreApproveJob;

        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * set class dan method untuk cancel import
     *
     * @param string $cacheKey key / kode unik per export
     * @param string $coreCancelClass Class untuk menghandle proses cancel import
     * @param string $coreCancelMethod Method untuk mendhandle proses cancel import
     * @return boolean false = Data Import tidak ada
     */
    public function setCoreCancel($cacheKey, string $coreCancelClass, string $coreCancelMethod)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['format']['coreCancelMethod'] = [$coreCancelClass, $coreCancelMethod];

        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * set class djobs untuk cancel import
     *
     * @param string $cacheKey key / kode unik per export
     * @param string $coreApproveJob Class untuk menghandle job cancel import
     * @return boolean false = Data Import tidak ada
     */
    public function setCoreCancelJob($cacheKey, string $coreCancelJob)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['format']['coreCancelJob'] = $coreCancelJob;

        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * CORE - TIDAK DIAKSES / DIGUNAKAN DARI APLIKASI SECARA LANGSUNG
     * -------------------------------------------------------------------------
     */

     /**
      * Get Default/Initial Import Status array
      *
      * @return array
      */
    protected function initImportStatus()
    {
        $importData = [];

        $importData['tenantId'] = 0;
        $importData['userId'] = 0;
        $importData['cacheKey'] = '';

        $importData['jobsId'] = 0; //id table jobs
        $importData['processId'] = 0; //id process (system process)

        $importData['queue'] = '';

        $importData['jobDispatchTime'] = ''; //waktu pertama kali jobs diproses (saat ke status 1)
        $importData['jobStartTime'] = ''; //waktu jobs pertama pertama kali diproses (saat ke status 2)

        $importData['log'] = ''; //log status

        $importData['status'] = 0;
        // status :
        // 0 new process
        // 1 sudah diinput ke jobs
        // 2 jobs sudah di dispatch (run) / sedang berjalan
        // 3 jobs selesai
        // 4 jobs gagal

        //----------------------------------------------------------------------

        $importData['forceCancle'] = 0; //1 jika force cancel
        $importData['importId'] = 0; //id dari import_id di table yg diimportnya, otomatis generate saat insert pertama kali
        $importData['importApproval'] = 1; //apakah ada process approve & cancel atau tidak

        $importData['addsParam'] = []; //general additional parameter jika diperlukan
        $importData['importModel'] = ''; //string namespace class model nya, khusus jika tidak mengisi coreMainLoopingMethod

        $importData['format'] = [
            'formatRow' => [], // format per index kolom (dari 0 dst)
            'dataStartRow' => 2, //baris pertama data diget
            'coreMainLoopingMethod' => [], // [class,static method]
            'coreRowFormaterMethod' => [], // [class,static method]
            'coreLastFormaterMethod' => [], // [class,static method]
            'coreApproveMethod' => [], // [class,static method]
            'coreApproveJob' => '', // class jobs approve
            'coreCancelMethod' => [], // [class,static method]
            'coreCancelJob' => '' //class jobs cancle
        ];

        $importData['filepath'] = ''; // file path excel yang diupload

        $importData['processedCount'] = 0; //jumlah record yg sudah diproses

        $importData['inputTime'] = now()->format('Y-m-d H:i:s'); //waktu import dicreate pertama kali

        return $importData;
    }

    /**
     * Update Import Data
     *
     * @param string $cacheKey
     * @param array $importData
     * @return void
     */
    protected function updateImport($cacheKey, $importData)
    {
        $importData = $this->convertCacheToDb($importData);
        MImport::where('cache_key', $cacheKey)->update($importData);
    }

    /**
     * Set Import Status to Done
     *
     * @param string $cacheKey
     * @return boolean false = Data Import tidak ada
     */
    protected function setImportDone($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['log'] .= '<br><b class="text-success">Import Done !</b><br>';
        $importData['log'] .= '<span class="text-info">Jobs ended at : <b>'
            . now()->format('Y-m-d H:i:s') . '</b></span>';
        $importData['status'] = self::IMPORT_STATUS_SUCCESS; //2: success
        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * dieksekusi saat import gagal
     *
     * @param string $cacheKey
     * @return boolean false = Data Import tidak ada
     */
    protected function setImportFailed($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['log'] .= '<br><b class="text-danger">Import Failed !</b><br>';
        $importData['log'] .= '<span class="text-info">Jobs ended at : <b>'
            . now()->format('Y-m-d H:i:s') . '</b></span>';
        $importData['status'] = self::IMPORT_STATUS_FAILED; //4: failed

        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * set dari cronjob, jika cronjob ada uncaught error
     *
     * @param string $cacheKey key / kode unik per import
     * @param Throwable $exception instance Exception dari job failed
     * @return void
     */
    public function setImportJobFailed($cacheKey, Throwable $exception)
    {
        $this->setImportFailed($cacheKey);

        $log = "<br><b class='text-danger'>Jobs terminated !</b>\n<hr>\n\nError message :<br>\n";
        $log .= $exception->getMessage();
        $log .= '<hr>';
        $log .= str_replace("\n", '<br>', $exception->getTraceAsString());

        $this->appendImportLog($cacheKey, $log);
        // report($exception); //lanjutkan error ke login (meureun)
    }

    /**
     * Append Import Log
     *
     * @param string $cacheKey
     * @param string $log
     * @return boolean false = Data Import tidak ada
     */
    public function appendImportLog($cacheKey, string $log = '')
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['log'] .= $log;
        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * -------------------------------------------------------------------------
     */

    /**
     * Initialize Data Import
     *
     * @param array $importData
     * @param string $curQueue Current Queue Name
     * @return array
     */
    private function _initImportData($importData, $curQueue)
    {
        // set teknikal
        $importData['queue'] = $curQueue;
        $importData['processId'] = getmypid();

        $jobs = Job::where('payload', 'LIKE', '%\"' . $importData['cacheKey'] . '\\\\\"%')
            ->get()
            ->append(['formated_payload'])
            ->toArray();
        foreach ($jobs as $value) {
            $importData['jobsId'] = $value['id'];
            break;
        }

        return $importData;
    }


    /**
     * Check Process and Update Status accordingly, and increase processed queue counter
     *
     * @param string $cacheKey
     * @return boolean false = gagal
     */
    private function _checkAndCounter($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false)
            return false;

        if ($importData['forceCancle'] == 1) {
            $importData['log'] .= '<br><b class="text-danger">Import Canceled !</b><br>';
            $importData['log'] .= '<span class="text-info">Jobs ended at : <b>'
                . now()->format('Y-m-d H:i:s') . '</b></span>';
            $importData['status'] = self::IMPORT_STATUS_FAILED; //4: failed
            $this->updateImport($cacheKey, $importData);
            return false;
        }

        $importData['log'] .= '. ';
        $importData['processedCount']++;
        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * Increase Processed Queue Counter
     *
     * @param string $cacheKey
     * @return void
     */
    public function importIncrementProcessedCount($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        $importData['processedCount']++;
        $importData['log'] .= '. ';
        $this->updateImport($cacheKey, $importData);
    }

    /**
     * proses utama yang dieksekusi dari jobs
     *
     * @param string $cacheKey
     * @param string $curQueue
     * @return void
     */
    public function processImport($cacheKey, $curQueue = 'import1')
    {
        ini_set('memory_limit', '5524M');
        set_time_limit(0);

        /**
         * init status & var
         */
        $importData = $this->getImport($cacheKey);
        if ($importData['tenantId'] != 0)
            Tenant::setActiveTenantById($importData['tenantId']);

        $importData['status'] = self::IMPORT_STATUS_ON_PROGRESS;
        $importData = $this->_initImportData($importData, $curQueue);

        $importData['jobStartTime'] = now()->format('Y-m-d H:i:s');

        $this->updateImport($cacheKey, $importData);

        $this->appendImportLog($cacheKey, '<span class="text-info">Jobs started at : <b>'
            . now()->format('Y-m-d H:i:s') . '</b></span><br>');

        // we need a reader to read the existing file...
        $reader = ReaderEntityFactory::createReaderFromFile($importData['filepath']);
        $reader->setShouldFormatDates(true); // this is to be able to copy dates
        $reader->open($importData['filepath']);

        $idxRow = 1; //nomor urut data dari 1 dst
        $i = 1;

        // pastikan format data nya sudah ada, jika belum ada maka generate default
        if (
            empty($importData['format']['formatRow'])
            || empty($importData['format']['coreRowFormaterMethod'])
        ) {
            $importData['format']['formatRow'] = $this->formatImportDefault($importData);
        }

        $startTime = microtime(true);
        // let's read the entire spreadsheet...
        foreach ($reader->getSheetIterator() as $sheetIndex => $sheet) {
            foreach ($sheet->getRowIterator() as $row) {

                if ($i < $importData['format']['dataStartRow']) {
                    $i++;
                    continue;
                }

                $dataRow = $row->getCells();

                // jika false berarti di cancel
                // if($this->_checkAndCounter($cacheKey)==false)
                //     return false;

                if (!empty($importData['format']['coreMainLoopingMethod'])) {
                    $mainLoopClass = $importData['format']['coreMainLoopingMethod'][0];
                    $mainLoopMethod = $importData['format']['coreMainLoopingMethod'][1];
                    $mainLoopClass::{$mainLoopMethod}(
                        $this,
                        $importData,
                        $dataRow,
                        $idxRow
                    );
                } else {

                    if (empty($importData['format']['coreRowFormaterMethod'])) {
                        $insertRow = $this->formatImportExcelRow($importData, $dataRow, $idxRow);
                    } else {
                        $rowFormatterClass = $importData['format']['coreRowFormaterMethod'][0];
                        $rowFormatterMethod = $importData['format']['coreRowFormaterMethod'][1];
                        $insertRow = $rowFormatterClass::{$rowFormatterMethod}(
                            $importData,
                            $dataRow,
                            $idxRow
                        );
                    }

                    // default insert data
                    $importData['importModel']::insert($insertRow);
                }

                $this->importIncrementProcessedCount($cacheKey);
                $idxRow++;

                if ((microtime(true) - $startTime) >= 10) {
                    usleep(100);
                }
            }

            // hanya baca worksheet pertamanya saja
            break;
        }

        $reader->close();
        //pastikan semua selesai dan memory di-free-kan kembali
        $reader = null;
        unset($reader);

        if (!empty($importData['format']['coreLastFormaterMethod'])) {
            $lastFormatterClass = $importData['format']['coreLastFormaterMethod'][0];
            $lastFormatterMethod = $importData['format']['coreLastFormaterMethod'][1];
            $lastFormatterClass::{$lastFormatterMethod}(
                $this,
                $importData
            );
        }

        //ubah status jadi ok jika sudah benar-benar selesai
        $this->setImportDone($cacheKey);
    }

    /**
     * Approve Import Data
     *
     * @param string $cacheKey
     * @return boolean false = gagal
     */
    public function approveImport($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        $return = false;
        $notOnJobs = true;
        // proses approve hanya boleh dilakukan jika berstatus 3 (Import Berhasil) atau 4 (gagal)
        if ($importData && ($importData['status'] == 3 || $importData['status'] == 4)) {
            $this->setImportApproveStart($cacheKey);
            if (!empty($importData['format']['coreApproveJob'])) {
                $importData['format']['coreApproveJob']::withChain(function () use ($cacheKey) {
                    $this->setImportApproveSuccess($cacheKey);
                });
                $return = true;
                $notOnJobs = false;
            } else {

                $dontHaveTransactionLevel = !Tenant::dbTransactionLevel();

                if ($dontHaveTransactionLevel)
                    Tenant::dbBeginTransaction();
                try {

                    if (!empty($importData['format']['coreApproveMethod'])) {
                        $approveClass = $importData['format']['coreApproveMethod'][0];
                        $approveMethod = $importData['format']['coreApproveMethod'][1];
                        $approveClass::{$approveMethod}(
                            $this,
                            $importData
                        );
                    } else {
                        $this->approveImportDo($importData);
                    }

                    if ($dontHaveTransactionLevel)
                        Tenant::dbCommit();

                    $return = true;
                } catch (\Exception  $e) {

                    if ($dontHaveTransactionLevel)
                        Tenant::dbRollback();

                    Log::info('Import::approveImport() ERROR');
                    Log::error($e);

                    $return = false;
                }
            }
        } else {

            Log::info('Import::approveImport() ERROR : No import data');
            Log::info($importData);
        }

        if ($return) {
            //tandai sucess jika process approve nya tidak menggunakan jobs
            if ($notOnJobs)
                $this->setImportApproveSuccess($cacheKey);
            return true;
        } else {
            $this->error = 'Data import yang bisa diapprove tidak ditemukan';
            return false;
        }
        return false;
    }

    /**
     * Update Approve Status
     *
     * @param array $importData
     * @return void
     */
    private function approveImportDo($importData)
    {
        $importData['importModel']::where('import_id', $importData['importId'])
            ->where('is_import', 1)
            ->update([
                'import_publish_time' => now(),
                'import_status' => 1
            ]);
    }

    /**
     * Set Approve Start Status
     *
     * @param string $cacheKey
     * @return boolean false = Data Import tidak ada
     */
    protected function setImportApproveStart($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['log'] .= '<br><b class="text-info">Import Approved...</b><br>';
        $importData['status'] = self::IMPORT_STATUS_APPROVE_ON_PROGRESS; //5 process approve import on progress
        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * Set Import Approval Status to Success
     *
     * @param string $cacheKey
     * @return boolean false = Data Import tidak ada
     */
    protected function setImportApproveSuccess($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['log'] .= '<br><b class="text-success">Import Approved Successfully !</b><br>';
        $importData['log'] .= '<span class="text-info">Jobs ended at : <b>'
            . now()->format('Y-m-d H:i:s') . '</b></span>';
        $importData['status'] = self::IMPORT_STATUS_APPROVE_SUCCESS; //6 process approve import berhasil
        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * Cancel Import Process
     *
     * @param string $cacheKey
     * @return boolean false = gagal
     */
    public function cancelImport($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        // proses cancel hanya boleh dilakukan jika berstatus 3 (Import Berhasil) atau 4 (import gagal)
        if ($importData && ($importData['status'] == 3 || $importData['status'] == 4)) {

            $this->setImportCancelStart($cacheKey);
            $return = false;
            $notOnJobs = true;
            if (!empty($importData['format']['coreCancelJob'])) {
                $importData['format']['coreCancelJob']::withChain(function () use ($cacheKey) {
                    $this->setImportCancelSuccess($cacheKey);
                });
                $return = true;
                $notOnJobs = false;
            } else {

                $dontHaveTransactionLevel = !Tenant::dbTransactionLevel();

                if ($dontHaveTransactionLevel)
                    Tenant::dbBeginTransaction();
                try {

                    if (!empty($importData['format']['coreCancelMethod'])) {
                        $cancelClass = $importData['format']['coreCancelMethod'][0];
                        $cancelMethod = $importData['format']['coreCancelMethod'][1];
                        $cancelClass::{$cancelMethod}(
                            $this,
                            $importData
                        );
                    } else {
                        $this->cancelImportDo($importData);
                    }

                    if ($dontHaveTransactionLevel)
                        Tenant::dbCommit();
                    $return = true;
                } catch (\Exception  $e) {

                    if ($dontHaveTransactionLevel)
                        Tenant::dbRollback();

                    $return = false;
                }
            }
        }

        if ($return) {
            //tandai sucess jika process cancel nya tidak menggunakan jobs
            if ($notOnJobs)
                $this->setImportCancelSuccess($cacheKey);
            return true;
        } else {
            $this->setImportCancelFailed($cacheKey);
            $this->error = 'Data import yang bisa dibatalkan tidak ditemukan';
            return false;
        }
    }

    /**
     * Delete Import Data
     *
     * @param array $importData
     * @return void
     */
    private function cancelImportDo($importData)
    {
        $importData['importModel']::where('import_id', $importData['importId'])
            ->where('is_import', 1)
            ->delete();
    }

    /**
     * Set Status for Cancelling Import to Start
     *
     * @param string $cacheKey
     * @return boolean false = Data Import tidak ada
     */
    protected function setImportCancelStart($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['log'] .= '<br><b class="text-info">Import Canceled...</b><br>';
        $importData['status'] = self::IMPORT_STATUS_CANCEL_APPROVE_ON_PROGRESS; //7: Approve success
        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * Set Status for Cancelling Import to Success
     *
     * @param string $cacheKey
     * @return boolean false = Data Import tidak ada
     */
    protected function setImportCancelSuccess($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['log'] .= '<br><b class="text-danger">Import Canceled Successfully !</b><br>';
        $importData['log'] .= '<span class="text-info">Jobs ended at : <b>'
            . now()->format('Y-m-d H:i:s') . '</b></span>';
        $importData['status'] = self::IMPORT_STATUS_CANCEL_APPROVE_SUCCESS; //8 process cancel approve import berhasil
        $this->updateImport($cacheKey, $importData);
        return true;
    }
    
    /**
     * Set Status for Cancelling Import to failed
     *
     * @param string $cacheKey
     * @return boolean false = Data Import tidak ada
     */
    protected function setImportCancelFailed($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['log'] .= '<br><b class="text-danger">Cancel Import Failed !</b><br>';
        $importData['log'] .= '<span class="text-info">Jobs ended at : <b>'
            . now()->format('Y-m-d H:i:s') . '</b></span>';
        $importData['status'] = self::IMPORT_STATUS_FAILED; //4 process cancel approve import berhasil
        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * set format row
     *
     * @param string $cacheKey
     * @param array $formatRow isi kolom sesuai urutan dengan format :
     *      [
     *              [
     *                  'field' => NAMA FIELD,
     *                  'default' => ISI DEFAULT VALUE,
     *                  'type' => string tipe datanya,
     *                  'format' => format tambahan dari type, misal type date isi format 'Y-m-d'
     *              ]
     *              ,...
     *      ]
     * @return boolean false = Data Import tidak ada
     */
    public function setFormatRow($cacheKey, array $formatRow)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        $importData['format']['formatRow'] = $formatRow;

        $this->updateImport($cacheKey, $importData);
        return true;
    }

    /**
     * untuk nambah pemformatan setelah formating default dieksekusi
     *
     * @param array $importData
     * @param array $row array row database (dari model)
     * @param integer $indexData unused
     *
     * @return array
     */
    protected function formatImportExcelRow($importData, array $row = [], int $indexData = 0)
    {
        $insertRow = [];

        foreach ($importData['format']['formatRow'] as $idx => $format) {
            $insertRow[] =
                empty($row[$idx]) && isset($format['default']) ?
                $format['default'] :
                $this->importFormatRowValue($row[$idx], $format);
        }

        return $insertRow;
    }

    /**
     * get nama field sesuai urutan di database
     *
     * @param array $importData
     *
     */
    protected function formatImportDefault($importData)
    {
        $model = new $importData['importModel']();
        if ($model->getConnectionName()) {
            $fields = Schema::connection($model->getConnectionName())
                ->getColumnListing($model->getTable());
        } else {
            $fields = Schema::getColumnListing($model->getTable());
        }
        $fieldFormated = [];
        foreach ($fields as $key => $value) {
            $fieldFormated[] = [
                'field' => $value
            ];
        }
        return $fieldFormated;
    }


    /**
     * format value cell sesuai config format columnya
     *
     * @param mixed value per cell/field dari database
     * @param array format, format dari konfig column, format :
     *  [
     *      'field' => NAMA FIELD,
     *      'default' => DEFAULT VALUE YANG DIGUNAKAN JIKA VALUE KOSONG ATAU JIKA TIDAK SESUAI FORMAT/TYPE
     *      'type' => 'type' ---> string, number, date, datetime, auto (default)
     *      'format' =>  ''--> format tambahan dari type, misal type date isi format 'Y-m-d'
     *  ]
     * @return mixed
     */
    protected function importFormatRowValue($value, array $format = [])
    {
        if (isset($format['type'])) {
            switch ($format['type']) {
                case 'string':
                    $value = (string) $value;
                    break;
                case 'date':
                    $format['format'] = empty($format['format']) ? 'Y-m-d' : $format['format'];
                    $value = (new Carbon($value))->format($format['format']);
                    break;
                case 'datetime':
                    $format['format'] = empty($format['format']) ? 'Y-m-d h:m:s' : $format['format'];
                    $value = (new Carbon($value))->format($format['format']);
                    break;
                case 'integer':
                case 'int':
                    $value = intval($value);
                    break;
                case 'float':
                    $value = floatval($value);
                    break;
                default:
                    # code...
                    break;
            }
        }

        return $value;
    }

    /**
     * Check if jobs executed per Tenant
     *
     * @param string $cacheKey
     * @return boolean
     */
    private function isImportJobsPerTenant($cacheKey)
    {
        $importData = $this->getImport($cacheKey);
        if ($importData == false) return false;

        return config('AppConfig.system.jobs.multitenant_add', false)
            && !empty($importData['tenantId']) && $importData['tenantId'] > 0
                ? true : false;
    }
}
