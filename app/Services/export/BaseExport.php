<?php

namespace App\Services\export;

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
use App\Models\Export;

use App\Jobs\Export as JExport;


class BaseExport extends BaseRepository
{
    protected $cacheActive = true;
    private $_exportUploadPath = 'export/'; //default path ke upload relative dari storage_path per tenant
    private $_defaultFilename = 'export_data';
    protected $_defaultDriver = 'spout';

    // 0 new process
    // 1 sudah diinput/dispatch ke jobs
    // 2 jobs sudah / sedang berjalan
    // 3 jobs selesai
    // 4 jobs gagal
    const EXPORT_STATUS_NEW = 0;
    const EXPORT_STATUS_DISPATCHED = 1;
    const EXPORT_STATUS_ON_PROGRESS = 2;
    const EXPORT_STATUS_SUCCESS = 3;
    const EXPORT_STATUS_FAILED = 4;

    //
    protected $_mainCacheKeyGroup = 'synapse.export'; //
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
     * mengconvert data format record database, ke format cache lama (agar tidak banyak mengubah bisnis proses)
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
     * mengconvert data format cache lama, ke format record database (agar tidak banyak mengubah bisnis proses)
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
     * list seluruh export
     */
    public function listExport($filter, $orderBy)
    {
        $list = $this->_list(new Export, $filter, 0, 0, $orderBy);
        // $newData = [];
        // foreach($list['data'] as $v){
        //     $newData[] = $this->convertDbToCache($v);
        // }
        return $list['data'];
    }

    /**
     * get queue yg available selanjutnya
     */
    public function nextExportQueue($tenantId = 0)
    {
        $isPerTenant = config('AppConfig.system.jobs.multitenant_add', false)
            && !empty($tenantId)
            && $tenantId > 0
            ? true : false;

        // jika queue worker dihandle oleh default atau default per tenant
        if (config('AppConfig.system.jobs.export_handler', 1) == 1) {
            return $isPerTenant ? ('tenant' . $tenantId) : 'default';

            // jika queue worker dihandle oleh worker export genaral
        } else if (config('AppConfig.system.jobs.export_handler', 1) == 2) {
            $idxQueue = 1;
            while (Job::where('queue', 'export' . $idxQueue)->exists()) {
                $idxQueue++;
                // berarti semua penuh, maka tambahkan ke yg paling sedikit
                if ($idxQueue > config('AppConfig.system.jobs.export_worker', 2)) {
                    $ret = DB::select(
                        "SELECT * FROM ("
                            . "SELECT COUNT(*) AS 'jm_queue',`queue` FROM `jobs` "
                                . "WHERE `queue` LIKE 'export%' GROUP BY `queue`"
                        . ") AS tmp_table ORDER BY `jm_queue` ASC LIMIT 1"
                    );
                    return isset($ret['queue']) ? $ret['queue'] : 'export1';
                }
            }
            return 'export' . $idxQueue;

            // jika queue worker dihandle oleh export handle per tenant
        } else if (config('AppConfig.system.jobs.export_handler', 1) == 3 && $isPerTenant) {
            $idxQueue = 1;
            while (Job::where('queue', 'tenant' . $tenantId . 'export' . $idxQueue)->exists()) {
                $idxQueue++;
                // berarti semua penuh, maka tambahkan ke yg paling sedikit
                if ($idxQueue > config('AppConfig.system.jobs.export_worker', 2)) {
                    $ret = DB::select(
                        "SELECT * FROM ("
                            . "SELECT COUNT(*) AS 'jm_queue',`queue` FROM `jobs` "
                                . "WHERE `queue` LIKE 'tenant" . $tenantId . "export%' GROUP BY `queue`"
                        . ") AS tmp_table ORDER BY `jm_queue` ASC LIMIT 1"
                    );
                    return isset($ret['queue']) ? $ret['queue'] : ('tenant' . $tenantId . 'export1');
                }
            }
            return 'tenant' . $tenantId . 'export' . $idxQueue;
        }
    }

    /**
     * get data export
     *
     * @param String $cacheKey key / kode unik per export
     * @param Boolean $reload True jika load data cache nya langsung ke cache engine
     *                        False jika load data dari local var
     */
    public function getExport($cacheKey, $reload = false)
    {
        $tmp = Export::where('cache_key', $cacheKey)->first();
        return $tmp ? $this->convertDbToCache($tmp->toArray()) : false;
    }

    /**
     * STEP 1 - yang harus diset pertama kali di controllernya (atau di tempat export ini dicreate/diinisasi)
     *
     * membuat process export baru
     *
     * @param String $cacheKey key / kode unik per export
     * @param String $listingModel     full class namespace model
     * @param Array $listingParams
     *      filter Array *optional
     *      orderBy
     * @param Integer $userId
     * @param Integer $tenantId
     * @param String $driver
     */
    public function createExport(
        $cacheKey,
        $listingModel,
        $listingParams = [],
        $userId = 0,
        $tenantId = 0,
        $driver = 'spout'
    ) {
        $tenantId = $tenantId ? $tenantId : config('tenant.id', 0);

        $exportData = $this->getExport($cacheKey);
        $createNew = true;

        // jika data ada maka pastikan sedang tidak berjalan
        if ($exportData) {
            $createNew = false;
            // jika sedang dalam proses
            if (
                $exportData['status'] == self::EXPORT_STATUS_DISPATCHED ||
                $exportData['status'] == self::EXPORT_STATUS_ON_PROGRESS
            ) {
                $this->error = 'Jobs already exists';
                return false;
                // delete file sebelumnya
            } else {
                // if($exportData['storage']=='s3'){
                Tenant::storage($tenantId)->delete($exportData['relativeFilepath']);
                // }else{
                //     Storage::disk('local')->delete($exportData['relativeFilepath']);
                // }
            }
        }

        // set baru export
        $exportData = $this->initExportStatus();
        $exportData['cacheKey'] = $cacheKey;
        $exportData['listingModel'] = $listingModel;
        $exportData['listingParams'] = $listingParams;
        $exportData['userId'] = $userId;
        $exportData['tenantId'] = $tenantId;

        // tambah detail nya
        if ($createNew) {
            Export::create($this->convertCacheToDb($exportData));
        } else {
            $this->updateExport($cacheKey, $exportData);
        }

        if ($driver != 'spout') {
            $this->setDriver($cacheKey, $driver);
            $exportData['driver'] = $this->_defaultDriver;
        }

        return $exportData;
    }

    /**
     * set export driver - phpspreadsheet , spout
     * Driver bisa diset saat createExport juga
     *
     * @param String $cacheKey key / kode unik per export
     */
    public function setDriver($cacheKey, $driver = 'spout')
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;
        $exportData['driver'] = strtolower($driver) == 'phpspreadsheet' ? 'phpspreadsheet' : 'spout'; //tandai sebagai force cancle
        $this->_defaultDriver = $exportData['driver'];
        $this->updateExport($cacheKey, $exportData);
    }


    /**
     * STEP TERAKHIR - step terakhir di controllernya
     *
     * dispatch export ke queue untuk pertama kali
     * dieksekusi setelah createExport dan set-set config
     *
     * @param String $cacheKey key / kode unik per export
     */
    public function dispatchExport($cacheKey)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        // jika belum diset manual maka ge
        if (empty($exportData['filename']))
            $exportData = $this->setStorage($cacheKey);

        $exportData['jobDispatchTime'] = now()->format('Y-m-d H:i:s');
        $exportData['status'] = self::EXPORT_STATUS_DISPATCHED;
        $exportData['queue'] = $this->nextExportQueue($exportData['tenantId']);
        $this->updateExport($cacheKey, $exportData);

        JExport::dispatch($cacheKey, $exportData['queue'])->onQueue($exportData['queue']);

        return $exportData;
    }

    /**
     * cancel export yg sedang berjalan
     *
     * @param String $cacheKey key / kode unik per export
     */
    public function cancelExport($cacheKey)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;
        $exportData['forceCancle'] = 1; //tandai sebagai force cancle
        $this->updateExport($cacheKey, $exportData);

        return true;
    }

    /**
     * delete log export dan file hasil exportnya
     *
     * @param String $cacheKey key / kode unik per export
     */
    public function deleteExport($cacheKey)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        // if($exportData['storage']=='s3'){
        Tenant::storage($exportData['tenantId'])->delete($exportData['relativeFilepath']);
        // }else{
        //     Storage::disk('local')->delete($exportData['relativeFilepath']);
        // }

        Export::where('cache_key', $cacheKey)->delete();

        return true;
    }

    /**
     * FUNGSI CONFIG SETER SETELAH createExport
     * -------------------------------------------------------------------------
     */

    /**
     * set config storage, jika tidak diset manual maka akan otomatis dieksekusi
     * saat jobs di dispatch (dispatchExport)
     *
     * @param String $cacheKey key / kode unik per export
     * @param Array $config yang akan diassign, berisi :
     *      filename String     nama file
     *      directory String    nama directory untuk grouping file, tidak diawali dan diakhir slash '/'
     */
    public function setStorage($cacheKey, array $config = [])
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $exportData['filename'] = $config['filename'] ?: '';
        $exportData['directory'] = empty($config['directory'])
            ? ''
            : trim(trim($config['directory'], '/'), '\\') . '/';

        $tenantPath = '';

        // jika multitenant maka groupkan per tenant
        // (dan jika tipe S3 storage maka tetap disimpan di local dulu,
        // setelah selesai export baru diupload ke S3 dan dihapus di yg local nya)
        if (config('AppConfig.system.multitenant.active')) {
            // $tenantPath = 'tenant_'.$exportData['tenantId'].'/';
            $exportData['storage'] = Tenant::storageIsS3($exportData['tenantId']) ? 's3' : 'local';
        } else {
            $exportData['storage'] =
                config('filesystems.disk.' . config('filesystems.default') . '.driver', 'local') == 's3'
                ? 's3' : 'local';
        }

        $exportData['relativePath'] = $tenantPath . $this->_exportUploadPath
            . $exportData['directory'] . $exportData['userId'] . '/';
        $exportData['path'] = Storage::disk(Tenant::storageGetDiskLocal($exportData['tenantId']))
            ->path($exportData['relativePath']);

        // pastikan folder tujuan ada
        if (!file_exists($exportData['path'])) {
            mkdir($exportData['path'], 0777, true);
        }

        if (empty($exportData['filename']))
            $exportData['filename'] = strtoupper(preg_replace(
                '/[^a-zA-Z0-9]+/', '_',
                $this->_defaultFilename . '_' . $exportData['inputTime'])
            ) . '.xlsx';

        $exportData['relativeFilepath'] = $exportData['relativePath'] . $exportData['filename'];
        $exportData['filepath'] = $exportData['path'] . $exportData['filename'];

        if ($exportData['storage'] == 's3') {
            $exportData['fileurl'] = Tenant::storage($exportData['tenantId'])
                ->url($exportData['relativeFilepath']);
        } else {
            $exportData['fileurl'] = url(
                Storage::disk(Tenant::storageGetDiskLocal($exportData['tenantId']))
                    ->url($exportData['relativeFilepath'])
            );
        }

        // jika sudah ada maka rename
        $i = 1;
        while (file_exists($exportData['filepath'])) {
            $exportData['filepath'] = $exportData['path'] . $i . '_' . $exportData['filename'];
            $exportData['relativeFilepath'] = $exportData['relativePath'] . $i . '_' . $exportData['filename'];
            $i++;
        }

        $this->updateExport($cacheKey, $exportData);

        return $exportData;
    }


    /**
     * set judul kolomn
     *
     * @param String $cacheKey
     * @param Array $columnCaption isi kolom sesuai urutan dengan format :
     *      [
     *          [
     *              'field_name',
     *              [
     *                  'default' => ISI DEFAULT VALUE,
     *                  'caption' => string label caption column nya
     *                  'type' => string tipe datanya
     *              ]
     *          ],
     *          ...
     *      ]
     * @param Integer $headerRow posisi baris dimana si judul/nama kolom disisipkan, default 1
     */
    public function setColumn($cacheKey, array $columnCaption, $headerRow = 1)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $exportData['template']['headerCaption'] = $columnCaption;
        $exportData['template']['headerCaptionRow'] = $headerRow;

        $this->updateExport($cacheKey, $exportData);
        return true;
    }

    /**
     * set template excel yang digunnakan (jika ingin menggunakan custom template)
     *
     * @param String $cacheKey key / kode unik per export
     * @param String $templatePath full path ke file excel template nya
     * @param Integer $dataStartRow baris ke berapa data pertama kali diinsert di excel
     *
     */
    public function setTemplate($cacheKey, $templatePath, $dataStartRow = 2)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $exportData['template']['filepath'] = $templatePath;
        $exportData['template']['dataStartRow'] = $dataStartRow;

        $this->updateExport($cacheKey, $exportData);
        return true;
    }

    /**
     * set parameter tambahan yang bisa digunakan untuk custom formating nantinya
     *
     * @param String $cacheKey key / kode unik per export
     * @param Array $addsParam
     */
    public function setAddsParam($cacheKey, $addsParam)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $exportData['addsParam'] = $addsParam;

        $this->updateExport($cacheKey, $exportData);
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
     * @param String $cacheKey key / kode unik per export
     */
    public function setCoreMainLooping($cacheKey, string $coreMainLoopingClass, string $coreMainLoopingMethod)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $exportData['template']['coreMainLoopingMethod'] = [
            $coreMainLoopingClass, $coreMainLoopingMethod
        ];

        $this->updateExport($cacheKey, $exportData);
        return true;
    }

    /**
     * set class dan method untuk memformat data per row
     *
     * @param String $cacheKey key / kode unik per export
     * @param String $coreRowFormaterClass      Namaspace class formaternya
     * @param String $coreRowFormaterMethod     Nama method di class formaternya,
     *                                          parameter pada method tersebut adalah :
     *      @param Array
     */
    public function setCoreRowFormater($cacheKey, string $coreRowFormaterClass, string $coreRowFormaterMethod)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $exportData['template']['coreRowFormaterMethod'] = [$coreRowFormaterClass, $coreRowFormaterMethod];

        $this->updateExport($cacheKey, $exportData);
        return true;
    }

    /**
     * set class dan method untuk memformat excel $reader saat setelah beres semua
     *
     * @param String $cacheKey key / kode unik per export
     */
    public function setCoreLastFormater($cacheKey, string $coreLastFormaterClass, string $coreLastFormaterMethod)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $exportData['template']['coreLastFormaterMethod'] = [$coreLastFormaterClass, $coreLastFormaterMethod];

        $this->updateExport($cacheKey, $exportData);
        return true;
    }

    /**
     * CORE - TIDAK DIAKSES / DIGUNAKAN DARI APLIKASI SECARA LANGSUNG
     * -------------------------------------------------------------------------
     */

    /**
     * get default isi data export.
     *
     * @return Array berisi data export default
     */
    protected function initExportStatus()
    {
        $exportData = [];

        $exportData['cacheKey'] = ''; //key unik per export, bisa dibilang ID Export nya

        // excel driver, antara phpspreadsheet atau spout, default spout
        $exportData['driver'] = strtolower($this->_defaultDriver) == 'phpspreadsheet' ? 'phpspreadsheet' : 'spout'; //tandai sebagai force cancle
        $exportData['jobsId'] = 0; //id table jobs
        $exportData['processId'] = 0; //id process
        $exportData['forceCancle'] = 0; //1 jika force cancel

        $exportData['queue'] = ''; //queue dimana si exportnya di proses

        $exportData['addsParam'] = []; //general additional parameter jika diperlukan
        $exportData['listingModel'] = ''; //model sumber data downloadnya, bisa array [class,static method]

        $exportData['listingParams'] = []; //format filter synapse yang digunakan untuk filter data nya
        $exportData['userId'] = 0;
        $exportData['tenantId'] = 0;

        $exportData['log'] = ''; // string log status yang ditampilkan

        $exportData['template'] = [
            //full template file export nya jika custom, kosong jika menggunakan default template
            'filepath' => '',
            // format header caption khusus default template
            'headerCaption' => [],
            'headerCaptionRow' => 1,

            // format data
            'dataStartRow' => 2, //baris pertama data diinsert

            'coreMainLoopingMethod' => [], // [class,static method]
            'coreRowFormaterMethod' => [], // [class,static method]
            'coreLastFormaterMethod' => [], // [class,static method]
        ];

        /**
         * config Storage
         * ---------------------------------------------------------------------
         */
        $exportData['storage'] = 'local'; //tipe storage, saat ini hanya bisa 2, "local" dan "s3"b

        $exportData['filename'] = ''; //nama file export nya beserta extention file nya
        $exportData['directory'] = ''; // additional path, path tambahan untuk pengelompokan jenis export
        // directory ini ditambahkan diakhir relativePath, jadi relativePath adalah default relativePath + directory

        $exportData['relativePath'] = ''; // path relative format laravel (yg bisa digunakan ke storage ke folder ke tempat file export berada
        $exportData['relativeFilepath'] = ''; // $exportData['relativePath'].'/'.$exportData['filename']

        $exportData['path'] = ''; // fullpath folder ke tempate file export berada, jika S3 Storage maka ini adalah TMP local nya
        $exportData['filepath'] = ''; // $exportData['path'].'/'.$exportData['filename'], jika S3 Storage maka ini adalah TMP local nya

        $exportData['fileurl'] = ''; //full url exportnya, jika S3 Storage maka ini adalah TMP nya
        /**
         * ---------------------------------------------------------------------
         */

        $exportData['count'] = 0; //jumlah total record yang harus diproses
        $exportData['processedCount'] = 0; //jumlah record yg sudah diproses

        $exportData['inputTime'] = now()->format('Y-m-d H:i:s'); //waktu export dicreate pertama kali
        $exportData['jobDispatchTime'] = ''; //waktu pertama kali jobs diproses (saat ke status 1)
        $exportData['jobStartTime'] = ''; //waktu jobs pertama pertama kali diproses (saat ke status 2)

        $exportData['isResumeJob'] = false;
        $exportData['resumeJobParam'] = [
            'jobDispatchTime' => '',
            'jobStartTime' => ''
        ];

        $exportData['status'] = 0;
        // status :
        // 0 new process
        // 1 sudah diinput ke jobs
        // 2 jobs sudah di dispatch (run) / sedang berjalan
        // 3 jobs selesai
        // 4 jobs gagal

        return $exportData;
    }

    /**
     * update data export
     *
     * @param String $cacheKey key / kode unik per export
     */
    protected function updateExport($cacheKey, $exportData)
    {
        $exportData = $this->convertCacheToDb($exportData);
        Export::where('cache_key', $cacheKey)->update($exportData);
    }

    /**
     * dieksekusi saat export berhasil dan selesai
     * @param String $cacheKey key / kode unik per export
     */
    protected function setExportDone($cacheKey)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $exportData['log'] .= '<br><b class="text-success">Export Done !</b><br>';
        $exportData['log'] .= '<span class="text-info">Jobs ended at : <b>'
            . now()->format('Y-m-d H:i:s') . '</b></span>';
        $exportData['status'] = self::EXPORT_STATUS_SUCCESS; //2: success

        // jika telah selesai dan tipe storage nya S3 maka pindahkan ke storage
        if ($exportData['storage'] == 's3') {

            $newPath = $exportData['relativePath'];
            if (config('AppConfig.system.multitenant.active')) {
                $newPath = $this->_exportUploadPath . $exportData['directory']
                    . $exportData['userId'] . '/';
            }

            // upload file ke S3 sebagai public
            if (Tenant::storage($exportData['tenantId'])->put(
                $newPath . $exportData['filename'],
                Storage::disk('local')->get($exportData['relativeFilepath']),
                'public'
            )) {
                // delete file di local
                Storage::disk(Tenant::storageGetDiskLocal($exportData['tenantId']))
                    ->delete($exportData['relativeFilepath']);
                // set url nya
                $exportData['relativePath'] = $newPath;
                $exportData['relativeFilepath'] = $newPath . $exportData['filename'];
                $exportData['fileurl'] = Tenant::storage($exportData['tenantId'])
                    ->url($exportData['relativeFilepath']);

                // jika gagal upload maka tandai storage sebagai local
            } else {
                $exportData['storage'] == 'local';
            }
        }

        $this->updateExport($cacheKey, $exportData);
    }

    /**
     * dieksekusi saat export gagal / ada error
     *
     * @param String $cacheKey key / kode unik per export
     */
    protected function setExportFailed($cacheKey)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $exportData['log'] .= '<br><b class="text-danger">Export Failed !</b><br>';
        $exportData['log'] .= '<span class="text-info">Jobs ended at : <b>'
            . now()->format('Y-m-d H:i:s') . '</b></span>';
        $exportData['filename'] = '';
        $exportData['fileurl'] = '';
        $exportData['status'] = self::EXPORT_STATUS_FAILED; //4: failed

        // jika gagal maka tandai storage nya masih di local
        $exportData['storage'] = 'local';

        $this->updateExport($cacheKey, $exportData);
    }

    /**
     * set dari cronjob, jika cronjob ada uncaught error
     *
     * @param String $cacheKey key / kode unik per export
     * @param Throwable $exception instance Exception dari job failed
     */
    public function setExportJobFailed($cacheKey, Throwable $exception)
    {
        $this->setExportFailed($cacheKey);

        $log = "<br><b class='text-danger'>Jobs terminated !</b>\n<hr>\n\nError message :<br>\n";
        $log .= $exception->getMessage();
        $log .= '<hr>';
        $log .= str_replace("\n", '<br>', $exception->getTraceAsString());

        $this->appendExportLog($log);
        // report($exception); //lanjutkan error ke login (meureun)
    }

    public function appendExportLog($cacheKey, string $log = '')
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $exportData['log'] .= $log;
        $this->updateExport($cacheKey, $exportData);
    }

    /**
     * -------------------------------------------------------------------------
     */

    private function _initExportData($exportData, $curQueue)
    {
        // set teknikal
        $exportData['queue'] = $curQueue;
        $exportData['processId'] = getmypid();

        $jobs = Job::where('payload', 'LIKE', '%\"' . $exportData['cacheKey'] . '\\\\\"%')
            ->get()
            ->append(['formated_payload'])
            ->toArray();
        foreach ($jobs as $value) {
            $exportData['jobsId'] = $value['id'];
            break;
        }

        return $exportData;
    }


    private function _checkAndCounter($cacheKey)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false)
            return false;

        if ($exportData['forceCancle'] == 1
            || $exportData['forceCancle'] != 0
            || $exportData['forceCancle'] == '1') {
            $exportData['log'] .= '<br><b class="text-danger">Export Canceled !</b><br>';
            $exportData['log'] .= '<span class="text-info">Jobs ended at : <b>'
                . now()->format('Y-m-d H:i:s') . '</b></span>';
            $exportData['filename'] = '';
            $exportData['fileurl'] = '';
            $exportData['status'] = self::EXPORT_STATUS_FAILED; //4: failed

            // jika gagal maka tandai storage nya masih di local
            $exportData['storage'] = 'local';

            $this->updateExport($cacheKey, $exportData);
            $GLOBALS['FORCE_CANCEL'] = true;
            return false;
        }

        $exportData['log'] .= '. ';
        $exportData['processedCount']++;
        $this->updateExport($cacheKey, $exportData);
        return true;
    }

    /**
     * proses utama yang dieksekusi dari jobs saat jobs nya dijalankan, di proses ini
     * juga si queue di set (saat createExport queue nya hanya default)
     *
     * SECARA DEFAULT MENGGUNAKAN DRIVER SPOUT
     *
     * @param String $cacheKey key / kode unik per export
     */
    public function processExport($cacheKey, $curQueue = 'export1')
    {
        ini_set('memory_limit', '5524M');
        set_time_limit(0);

        $startTime = microtime(true);

        /**
         * init status & var
         */
        $exportData = $this->getExport($cacheKey, true);
        if ($exportData['tenantId'] != 0)
            Tenant::setActiveTenantById($exportData['tenantId']);

        $exportData['status'] = self::EXPORT_STATUS_ON_PROGRESS;
        $exportData = $this->_initExportData($exportData, $curQueue);

        $tenantPath = '';

        $tmpFilename = Storage::disk(Tenant::storageGetDiskLocal($exportData['tenantId']))->path(
            $tenantPath . 'synapse_cache'
            . DIRECTORY_SEPARATOR . 'export_tmp'
            . DIRECTORY_SEPARATOR . $exportData['cacheKey'] . '_' . $exportData['jobsId'] . '_'
                . now()->format('YmdHis') . '.xlsx'
        );

        $newDir = dirname($tmpFilename);
        if (!file_exists($newDir)) {
            mkdir($newDir, 0755, true);
        }

        // $tmpFilename = storage_path('app'.DIRECTORY_SEPARATOR.'synapse_cache'.DIRECTORY_SEPARATOR.'export_tmp'.DIRECTORY_SEPARATOR.$exportData['cacheKey'].'_'.$exportData['jobsId'].'_'.now()->format('YmdHis').'.xlsx');
        $file = fopen($tmpFilename, 'w');
        fclose($file);

        // penanda tipe source datanya, apakah model eloquent atau langsung array
        $isSourceDataModel = true;

        // jika resume dari jobs sebelumnya yang di split
        if ($exportData['isResumeJob']) {

            $exportData['resumeJobParam']['jobStartTime'] = now()->format('Y-m-d H:i:s');
            $this->updateExport($cacheKey, $exportData);

            $this->appendExportLog($cacheKey, '<span class="text-info">Continuing '
                . 'process from previous jobs</span>...<br>');
            $this->appendExportLog($cacheKey, '<span class="text-info">Jobs started at : <b>'
                . now()->format('Y-m-d H:i:s') . '</b></span><br>');

            // we need a reader to read the existing file...
            $reader = ReaderEntityFactory::createReaderFromFile($exportData['filepath']);
            $reader->setShouldFormatDates(true); // this is to be able to copy dates
            $reader->open($exportData['filepath']);

            // ... and a writer to create the new file
            $writer = WriterEntityFactory::createWriterFromFile($tmpFilename);
            $writer->openToFile($tmpFilename);

            if (is_array($exportData['listingModel'])) {
                $data = $exportData['listingModel'][0]::{$exportData['listingModel'][1]}(
                    $exportData,
                    $exportData['listingParams']
                );
            } else {
                $data = new $exportData['listingModel'];
                $data = $this->_filter($data, $exportData['listingParams']['filter']);
            }

            $offset = $exportData['resumeJobParam']['lastTableRow'];
            $limit = $exportData['count'] + 1000;

            $GLOBALS['synapse_export_indexExcelRow'] = $exportData['resumeJobParam']['lastExcelRow'];
            $isFirstRow = false; //flag untuk penanda baris pertama dari data
            $GLOBALS['synapse_export_indexData'] = $exportData['resumeJobParam']['lastTableRow'];

            //jika jobs pertama maka
        } else {
            if (is_array($exportData['listingModel'])) {
                $data = $exportData['listingModel'][0]::{$exportData['listingModel'][1]}(
                    $exportData,
                    $exportData['listingParams']
                );
                // jika array berarti bukan model eloquent
                if (is_array($data))
                    $isSourceDataModel = false;
            } else {
                $data = new $exportData['listingModel'];
                $data = $this->_filter($data, $exportData['listingParams']['filter']);
            }

            $exportData['count'] = $isSourceDataModel ? $data->count() : count($data);
            $exportData['jobStartTime'] = now()->format('Y-m-d H:i:s');

            $this->updateExport($cacheKey, $exportData);

            $this->appendExportLog($cacheKey, '<span class="text-info">Jobs started at : <b>'
                . now()->format('Y-m-d H:i:s') . '</b></span><br>');
            $this->appendExportLog($cacheKey, 'Url will be at : ' . $exportData['fileurl'] . '<br>');

            if ($exportData['template']['filepath']) {
                $tmpFileReader = $exportData['template']['filepath'] ?: resource_path('doc/generalExport.xlsx');

                // we need a reader to read the existing file...
                $reader = ReaderEntityFactory::createReaderFromFile($tmpFileReader);
                $reader->setShouldFormatDates(true); // this is to be able to copy dates
                $reader->open($tmpFileReader);
            } else {
                $reader = null;
            }

            // ... and a writer to create the new file
            $writer = WriterEntityFactory::createWriterFromFile($tmpFilename);
            $writer->openToFile($tmpFilename);

            $GLOBALS['synapse_export_indexExcelRow'] = $exportData['template']['dataStartRow']; //urutan baris excel
            $GLOBALS['synapse_export_indexExcelRow']++; //start row ditambah satu agar style header tidak terbawa, karena nanti first row ini akan didelete juga
            $GLOBALS['synapse_export_indexData'] = 0; //nomor urut data dari 0 dst
            $isFirstRow = true; //flag untuk penanda baris pertama dari data
            $offset = 0;
            $limit = null;
        }

        if ($exportData['template']['filepath']) {
            // let's read the entire spreadsheet...
            foreach ($reader->getSheetIterator() as $sheetIndex => $sheet) {
                // Add sheets in the new file, as we read new sheets in the existing one
                if ($sheetIndex !== 1) {
                    $writer->addNewSheetAndMakeItCurrent();
                }

                foreach ($sheet->getRowIterator() as $row) {
                    // ... and copy each row into the new spreadsheet
                    $writer->addRow($row);
                }
            }
        }

        if ($isSourceDataModel)
            if (!is_array($exportData['listingModel'])
                && !empty($exportData['listingParams']['orderBy'])) {
                if (!is_array($exportData['listingParams']['orderBy'][0]))
                    $exportData['listingParams']['orderBy'] = [$exportData['listingParams']['orderBy']];

                foreach ($exportData['listingParams']['orderBy'] as $oBitem) {
                    $data = $data->orderBy($oBitem[0], $oBitem[1]);
                }
            }

        $border = (new BorderBuilder())
            ->setBorderTop(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
            ->setBorderRight(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
            ->setBorderBottom(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
            ->setBorderLeft(Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID)
            ->build();

        $styleBorder = (new StyleBuilder())
            ->setBorder($border)
            ->build();

        /**
         * proses export
         */
        $GLOBALS['synapse_export_isBreaking'] = false;
        $GLOBALS['first_row'] = true;
        $GLOBALS['FORCE_CANCEL'] = false;
        if ($isSourceDataModel) {
            $this->chunkWithLimit($data, 100, $offset, $limit, function ($chunkedData) use (
                $cacheKey,
                &$isFirstRow,
                &$reader,
                &$writer,
                $startTime,
                $exportData,
                $border,
                $styleBorder,
                $tmpFilename
            ) {
                return $this->chunkWithLimit_process(
                    $chunkedData,
                    $cacheKey,
                    $isFirstRow,
                    $reader,
                    $writer,
                    $startTime,
                    $exportData,
                    $border,
                    $styleBorder,
                    $tmpFilename
                );
            });
        } else {
            $this->processExportArray(
                $data,
                $cacheKey,
                $isFirstRow,
                $reader,
                $writer,
                $startTime,
                $exportData,
                $border,
                $styleBorder,
                $tmpFilename
            );
        }

        if ($GLOBALS['FORCE_CANCEL']) return false;

        if ($GLOBALS['synapse_export_isBreaking']) return true;

        $exportData = $this->getExport($cacheKey);
        $exportData['count'] = $GLOBALS['synapse_export_indexData'];
        $this->updateExport($cacheKey, $exportData);

        $this->appendExportLog($cacheKey, '<br>Save file to : ' . $exportData['filename'] . '<br>');

        if ($reader)
            $reader->close();

        $writer->close();

        // unlink($exportData['filepath']);
        rename($tmpFilename, $exportData['filepath']);

        if (!empty($exportData['template']['coreLastFormaterMethod'])) {
            $coreFormatterMethod = $exportData['template']['coreLastFormaterMethod'];
            $coreFormatterMethod[0]::{$coreFormatterMethod[1]}(
                $exportData,
                $exportData['filepath']
            );
        }


        //pastikan semua selesai dan memory di-free-kan kembali
        $reader = null;
        $writer = null;
        $data = null;
        unset($reader, $data);

        //ubah status jadi ok
        $this->setExportDone($cacheKey);

        return true;
    }

    /**
     * fungsi chunk hasil query dengan limit, default eloquent laravel tidak bisa
     * menggabungkan fitur chunk dan limit, jadi bibuat work arround nya
     */
    private function chunkWithLimit($model, $count, $offset = 0, $remaining = null, callable $callback)
    {
        do {
            if (!is_null($remaining)) {
                $limit = min($count, $remaining);
            } else {
                $limit = $count;
            }

            $results = $model->skip($offset)->take($limit)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if (call_user_func($callback, $results) === false) {
                return false;
            }

            $offset += $countResults;

            if (!is_null($remaining)) {
                $remaining -= $countResults;
                if ($remaining == 0) {
                    break;
                }
            }
        } while ($countResults == $limit);

        return true;
    }

    private function chunkWithLimit_process(
        $chunkedData,
        $cacheKey,
        &$isFirstRow,
        &$reader,
        &$writer,
        $startTime,
        $exportData,
        $border,
        $styleBorder,
        $tmpFilename
    ) {

        if (!empty($exportData['listingParams']['filter']['append']))
            $chunkedData = $chunkedData->append($exportData['listingParams']['filter']['append']);

        $chunkedData = $chunkedData->toArray();

        usleep(100);

        foreach ($chunkedData as $dataRow) {
            // jika main looping langsung di bypass
            if (!empty($exportData['template']['coreMainLoopingMethod'])) {
                $mainLoopMethod = $exportData['template']['coreMainLoopingMethod'];
                $mainLoopMethod[0]::{$mainLoopMethod[1]}(
                    $exportData,
                    $reader,
                    $writer,
                    $dataRow
                );
                continue;
            }

            //jika tanpa template dan row 1 maka simpan nama2 kolomnya, untuk dijadikan header caption
            if ($isFirstRow && empty($exportData['template']['filepath'])) {
                $headerColumn = $this->formatExportExcelHeader($cacheKey, $dataRow);

                //kolom terakhir header
                // $countHeader = count($headerColumn);
                $styleHeading = (new StyleBuilder())
                    ->setFontBold()
                    ->setBorder($border)
                    ->setBackgroundColor('CCCCCC')
                    ->build();

                $writer->addRow(
                    WriterEntityFactory::createRowFromArray($headerColumn, $styleHeading)
                );

                $isFirstRow = false; //tandai flag first row agar tidak masuk ke sini lg di row selanjutnya
            }

            // format record sesuai data yang diimport sekarang
            $insertRow = $this->formatExportExcelRow(
                $cacheKey,
                $dataRow,
                $GLOBALS['synapse_export_indexExcelRow'],
                $GLOBALS['synapse_export_indexData']
            );

            // jika menyertakan fungsi callback untuk format dataRow maka eksekusi
            if (!empty($exportData['template']['coreRowFormaterMethod'])) {
                $rowFormatterMethod = $exportData['template']['coreRowFormaterMethod'];
                $insertRow = $rowFormatterMethod[0]::{$rowFormatterMethod[1]}(
                    $exportData, // data export cache
                    $insertRow, // record data yang sudah diformat
                    $dataRow, // record data yang diexport, diambil dari database
                    $GLOBALS['synapse_export_indexExcelRow'], // index/nomor urut baris excel yang saat ini diinsert
                    $GLOBALS['synapse_export_indexData'] + 1 // index/nomor urut data yang saat ini sedang diinsert
                );

                // jika false berarti diskip
                if ($insertRow == false)
                    continue;
            }
            // $reader = Excel::insertRow($reader, $GLOBALS['synapse_export_indexExcelRow'], $insertRow);

            $writer->addRow(
                WriterEntityFactory::createRowFromArray($insertRow, $styleBorder)
            );

            $GLOBALS['synapse_export_indexExcelRow']++;
            $GLOBALS['synapse_export_indexData']++;

            // jika false berarti di cancel
            if ($this->_checkAndCounter($cacheKey) == false) {
                return false;
            }
        }

        // DI SPOUT TIDAK SUPPORT BREAK PROCESS
        // //break proses setiap kurang dari setengah jam
        // if((microtime(true)-$startTime)>=1800){
        // // if((microtime(true)-$startTime)>=5){

        //     // $this->appendExportLog($cacheKey,'<br><span class="text-info">Break on last id </span>'.$lastId.' ('.$GLOBALS['synapse_export_indexData'].')<br>');
        //     $chunkedData = null;
        //     unset($chunkedData);
        //     $this->breakToNextExport($cacheKey, $tmpFilename, $reader, $writer, $GLOBALS['synapse_export_indexExcelRow'],$GLOBALS['synapse_export_indexData']);
        //     $GLOBALS['synapse_export_isBreaking'] = true;
        //     return false;
        // }
        return true;
    }

    private function processExportArray(
        $data,
        $cacheKey,
        &$isFirstRow,
        &$reader,
        &$writer,
        $startTime,
        $exportData,
        $border,
        $styleBorder,
        $tmpFilename
    ) {
        foreach ($data as $dataRow) {
            // jika main looping langsung di bypass
            if (!empty($exportData['template']['coreMainLoopingMethod'])) {
                $mainLoopMethod = $exportData['template']['coreMainLoopingMethod'];
                $mainLoopMethod[0]::{$mainLoopMethod[1]}(
                    $exportData,
                    $reader,
                    $writer,
                    $dataRow
                );
                continue;
            }

            //jika tanpa template dan row 1 maka simpan nama2 kolomnya, untuk dijadikan header caption
            if ($isFirstRow && empty($exportData['template']['filepath'])) {
                $headerColumn = $this->formatExportExcelHeader($cacheKey, $dataRow);

                //kolom terakhir header
                // $countHeader = count($headerColumn);
                $styleHeading = (new StyleBuilder())
                    ->setFontBold()
                    ->setBorder($border)
                    ->setBackgroundColor('CCCCCC')
                    ->build();

                $writer->addRow(
                    WriterEntityFactory::createRowFromArray($headerColumn, $styleHeading)
                );

                $isFirstRow = false; //tandai flag first row agar tidak masuk ke sini lg di row selanjutnya
            }

            // format record sesuai data yang diimport sekarang
            $insertRow = $this->formatExportExcelRow(
                $cacheKey,
                $dataRow,
                $GLOBALS['synapse_export_indexExcelRow'],
                $GLOBALS['synapse_export_indexData']
            );

            // jika menyertakan fungsi callback untuk format dataRow maka eksekusi
            if (!empty($exportData['template']['coreRowFormaterMethod'])) {
                $rowFormatterMethod = $exportData['template']['coreRowFormaterMethod'];
                $insertRow = $rowFormatterMethod[0]::{$rowFormatterMethod[1]}(
                    $exportData, // data export cache
                    $insertRow, // record data yang sudah diformat
                    $dataRow, // record data yang diexport, diambil dari database
                    $GLOBALS['synapse_export_indexExcelRow'], // index/nomor urut baris excel yang saat ini diinsert
                    $GLOBALS['synapse_export_indexData'] + 1 // index/nomor urut data yang saat ini sedang diinsert
                );

                // jika false berarti diskip
                if ($insertRow == false)
                    continue;
            }
            // $reader = Excel::insertRow($reader, $GLOBALS['synapse_export_indexExcelRow'], $insertRow);

            $writer->addRow(
                WriterEntityFactory::createRowFromArray($insertRow, $styleBorder)
            );

            $GLOBALS['synapse_export_indexExcelRow']++;
            $GLOBALS['synapse_export_indexData']++;

            // jika false berarti di cancel
            if ($this->_checkAndCounter($cacheKey) == false) {
                return false;
            }
        }
    }

    /**
     * Dieksekusi otomatis saat export tidak menggunakan file template (jadi membutuhkan judul kolom).
     * Berfungsi untuk men-generate nama-nama kolom dari data yang ada (dari record pertama).
     * Nama2 kolom digenerate dari config headerCaption atau dari nama2 field databasenya.
     *
     * @param String $cacheKey
     * @param Array $row1 record data pertama yang akan diexport
     * @return Array list nama/judul column header, format :
     *      [
     *          'Caption Column 1',
     *          'Caption Column 2',
     *          'Caption Column 3',
     *          ....
     *      ]
     */
    protected function formatExportExcelHeader($cacheKey, array $row1 = [])
    {
        $exportData = $this->getExport($cacheKey);
        $headerColumn = [];
        //jika ada format column maka gunakan format column
        if (!empty($exportData['template']['headerCaption'])) {
            $i = 0;
            foreach ($exportData['template']['headerCaption'] as $key => $format) {
                $i++;
                $headerColumn[] =
                    empty($format['caption']) ?
                    ('column ' . $key) :
                    $format['caption'];
            }
        } else {
            $i = 0;
            foreach ($row1 as $fieldName => $fieldValue) {
                $i++;
                $headerColumn[] = str_replace('_', ' ', $fieldName);
            }
        }
        return $headerColumn;
    }

    /**
     * untuk nambah pemformatan setelah formating default dieksekusi
     *
     * @param String $cacheKey key / kode unik per export
     * @param array $row array row database (dari model)
     *
     * @return array
     */
    protected function formatExportExcelRow($cacheKey, array $row = [], int $indexExcelRow, int $indexData)
    {
        $exportData = $this->getExport($cacheKey);
        $insertRow = [];
        $i = 0;
        //jika ada format column maka gunakan format column
        if (!empty($exportData['template']['headerCaption'])) {
            foreach ($exportData['template']['headerCaption'] as $key => $format) {
                $i++;
                $insertRow[] =
                    empty($row[$key]) && isset($format['default']) ?
                    $format['default'] :
                    $this->exportFormatRowValue($row[$key], $format[1]);
            }
        } else {
            foreach ($row as $fieldValue) {
                $i++;
                $insertRow[] = is_array($fieldValue) ? '' : $fieldValue;
            }
        }

        return $insertRow;
    }

    /**
     * format value cell sesuai config format columnya
     *
     * @param mixed value per cell/field dari database
     * @param array format, format dari konfig column, format :
     *  [
     *      'caption'=>'CAPTION COLUMNNYA',
     *      'default' => DEFAULT VALUE YANG DIGUNAKAN JIKA VALUE KOSONG ATAU JIKA TIDAK SESUAI FORMAT/TYPE
     *      'type' => 'type' ---> string, number, date, datetime, auto (default)
     *      'format' =>  ''--> format tambahan dari type, misal type date isi format 'Y-m-d'
     *  ]
     */
    protected function exportFormatRowValue($value, array $format = [])
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
                    $value = (int) $value;
                    break;
                case 'float':
                    $value = (float) $value;
                    break;
                default:
                    # code...
                    break;
            }
        }

        return $value;
    }

    /**
     *
     * @param String $cacheKey key / kode unik per export
     */
    public function exportIncrementProcessedCount($cacheKey)
    {
        $exportData = $this->getExport($cacheKey);
        $exportData['processedCount']++;
        $this->updateExport($cacheKey, $exportData);
    }


    /**
     * saat jobs dipecah ke jobs selanjurnya
     *
     * @param String $cacheKey key / kode unik per export
     */
    private function breakToNextExport(
        $cacheKey,
        $tmpFilename,
        &$reader,
        &$writer,
        $lastExcelRow = 1,
        $lastTableRow = 1
    ) {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $this->appendExportLog(
            $cacheKey,
            '<br><span class="text-info">Break process to the next job, please wait</span>...<br>'
        );

        if ($reader)
            $reader->close();

        $writer->close();

        // unlink($exportData['filepath']);
        rename($tmpFilename, $exportData['filepath']);

        $reader = null;
        $writer = null;
        unset($writer, $reader);

        $exportData['isResumeJob'] = true;
        $exportData['resumeJobParam']['lastExcelRow'] = $lastExcelRow;
        $exportData['resumeJobParam']['lastTableRow'] = $lastTableRow;
        $exportData['resumeJobParam']['jobDispatchTime'] = now()->format('Y-m-d H:i:s');
        $exportData['resumeJobParam']['jobStartTime'] = '';

        $this->updateExport($cacheKey, $exportData);
        $queueName = $this->nextExportQueue($exportData['tenantId']);
        JExport::dispatch($cacheKey, $queueName)->onQueue($queueName);
    }
}
