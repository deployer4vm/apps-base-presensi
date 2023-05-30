<?php

namespace App\Base\Traits;

use Exception;
use Throwable;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

use App\Jobs\ResImport;
use App\Facades\Tenant;
use App\Facades\Excel;
use Carbon\Carbon;

/**
 * Excel Import Trait - fungsi-fungsi untuk handling import
 */
trait ResImportTrait
{

    // use ResCacheTrait;

    static $IMPORT_STATUS_READY = 0; //sedang tidak ada proses import
    static $IMPORT_STATUS_JUST_UPLOADED = 1; //sudah ada file upload
    static $IMPORT_STATUS_ON_PROGRESS = 2; //import sedang dalam proses
    static $IMPORT_STATUS_SUCCESS = 3; //import berhasil
    static $IMPORT_STATUS_FAILED = 4; //import gagal
    static $IMPORT_STATUS_ON_APPROVE_PROGRESS = 5; //sedang proses approve
    static $IMPORT_STATUS_ON_CANCEL_PROGRESS = 6; //sedang proses cancel

    private $_importFunctionInitialize = false;

    private $_importGroup = '';
    private $_importModelHeader = null; //instanse eloquent model untuk header table
    private $_importModelDetail = null; //instanse eloquent model untuk detail table
    private $_importModelDetailFK = '';
    private $_importAddsJobsParam = []; //parameter tambahan ke jobs parameter
    private $_importStartRow = 2; //start read dari baris berapa
    private $_importUseJobs = true; //sementara belum ada opsi pake jobs atau tidak, HARUS pake jobs
    private $_importUploadPath = 'import/'; //path ke upload relative dari public_path
    private $_allowedMimeType = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel'
    ]; // list tipe mime yang diperbolehkan untuk diupload
    private $_allowedExt = [
        'xlsx',
        'xls'
    ]; // list extensi yang diperbolehkan untuk diupload

    private $_importDefaultColumn = []; //daftar field yang diimport, jika array kosong maka semua field diimport
    private $_importColumn = []; //daftar field yang diimport, jika array kosong maka semua field diimport
    private $_resumeParams = [];

    private $_tenantId = 0;

    /**
     * Initialize Import Base Data
     *
     * @param string $group Import Group Name (for jobs)
     * @param Illuminate\Database\Eloquent\Model $modelHeader Should be an Eloquent Model instance
     * @param Illuminate\Database\Eloquent\Model $modelDetail Should be an Eloquent Model instance
     * @param array $addsJobsParam Additional parameter for jobs
     * @return void
     */
    public function initImport(
        string $group = '',
        $modelHeader = null,
        $modelDetail = null,
        array $addsJobsParam = []
    ) {
        $this->_importGroup = $group;

        $this->setImportModel($modelHeader, $modelDetail);
        $this->setImportJobsParam($addsJobsParam);

        $this->_importFunctionInitialize = true;
    }

    /**
     * import jobs param
     * -----
     */
    /**
     * Get Additional Job Parameters
     *
     * @return array
     */
    public function getImportJobsParam()
    {
        return $this->_importAddsJobsParam;
    }

    /**
     * Set Additional Jobs Parameter
     *
     * @param array $addsJobsParam
     * @return void
     */
    public function setImportJobsParam(array $addsJobsParam = [])
    {
        $this->_importAddsJobsParam = $addsJobsParam;
    }

    /**
     * import model
     * -----
     */
    /**
     * Get Header Model
     *
     * @return Illuminate\Database\Eloquent\Model Header Model instance
     */
    public function getImportModelHeader()
    {
        return $this->_importModelHeader;
    }

    /**
     * Get Detail Model
     *
     * @return Illuminate\Database\Eloquent\Model Detail Model instance
     */
    public function getImportModelDetail()
    {
        return $this->_importModelDetail;
    }

    /**
     * Set Models
     *
     * @param Illuminate\Database\Eloquent\Model $modelHeader Should be an Eloquent Model instance for Header
     * @param Illuminate\Database\Eloquent\Model $modelDetail Should be an Eloquent Model instance for Detail/Body
     * @return void
     */
    public function setImportModel($modelHeader, $modelDetail)
    {
        if ($modelHeader)
            $this->setImportDetailForeignKey($modelHeader->getModel()->getTable() . '_id');
        $this->_importModelHeader = $modelHeader;
        $this->_importModelDetail = $modelDetail;
    }

    /**
     * Set the FK field name for Detail Model
     *
     * @param string $key
     * @return void
     */
    public function setImportDetailForeignKey(string $key)
    {
        $this->_importModelDetailFK = $key;
    }

    /**
     * Get FK field name for Detail Model
     *
     * @return void
     */
    public function getImportDetailForeignKey()
    {
        return $this->_importModelDetailFK;
    }

    /**
     * import upload path
     * -----
     */
    /**
     * Get Upload Path
     *
     * @return string
     */
    public function getImportUploadPath()
    {
        return $this->_importUploadPath;
    }

    /**
     * Set Upload Path
     *
     * @param string $uploadPath
     * @return void
     */
    public function setImportUploadPath(string $uploadPath = '')
    {
        $this->_importUploadPath = trim(trim($uploadPath, '/'), '\\') . '/';
    }

    /**
     * allowed mime type
     * -----
     */
    /**
     * Get Allowed MIME Types
     *
     * @return array
     */
    public function getAllowedMimeType()
    {
        return $this->_allowedMimeType;
    }

    /**
     * Set the Allowed MIME Types
     *
     * @param array $allowedMimeType
     * @return void
     */
    public function setAllowedMimeType(array $allowedMimeType = [])
    {
        $this->_allowedMimeType = $allowedMimeType;
    }

    /**
     * allowed file extention
     * -----
     */
    /**
     * Get Allowed File Extensions
     *
     * @return array
     */
    public function getAllowedExt()
    {
        return $this->_allowedExt;
    }

    /**
     * Set the Allowed File Extensions
     *
     * @param array $allowedExt
     * @return void
     */
    public function setAllowedExt(array $allowedExt = [])
    {
        $this->_allowedExt = $allowedExt;
    }

    /**
     * import start row
     * -----
     */
    /**
     * Get the Starting Row
     *
     * @return integer
     */
    public function getImportStartRow()
    {
        return $this->_importStartRow;
    }

    /**
     * Set the starting row
     *
     * @param integer $startRow
     * @return void
     */
    public function setImportStartRow(int $startRow = 2)
    {
        $this->_importStartRow = $startRow;
    }

    /**
     * Import column, list field yg di-Import
     * -----
     */
    /**
     * Get Array of Column to be imported
     *
     * @return array
     */
    public function getImportColumn()
    {
        return $this->_importColumn;
    }

    /**
     * Set List of Column to be imported
     *
     * @param array $columnImport format :
     *  [
     *      'EXCEL_COLUMN' =>
     *          ['field_name', [
     *              'caption'=>'CAPTION COLUMNNYA',
     *              'default' => DEFAULT VALUE YANG DIGUNAKAN JIKA VALUE KOSONG ATAU JIKA TIDAK SESUAI FORMAT/TYPE
     *              'type' => 'type' ---> string, number, date, auto (default)
     *              'format' =>  --> format tambahan dari type, misal type date isi format 'Y-m-d'
     *              ]
     *          ],
     *      'EXCEL_COLUMN_SELANJUTNYA' =>
     *          [..field selanjutny]
     *  ]
     * @return void
     */
    public function setImportColumn(array $columnImport = [])
    {
        $this->_importColumn = $columnImport;
    }

    /**
     * Set Defailt Columns
     *
     * @param array $columnImport
     * @return void
     */
    public function setImportDefaultColumn(array $columnImport = [])
    {
        $this->_importDefaultColumn = $columnImport;
    }

    /**
     * Get Default Columns
     *
     * @return array
     */
    public function getImportDefaultColumn()
    {
        return $this->_importDefaultColumn;
    }

    /**
     * Set Tenant ID
     *
     * @param integer $tenantId
     * @return void
     */
    public final function setImportTenantId($tenantId = 0)
    {
        $this->_tenantId = $tenantId;

        // jika dijobs dan pertenant tapi tenant nya ga ke detek maka set tenant
        if ($tenantId != 0 && config('tenant.id', 0) == 0) {
            Tenant::setActiveTenantById($tenantId);
        }
    }

    /**
     * OVERRIDEABLE
     * fungsi untuk di overload di parent repo yg menggunakan import trait ini (jika diperlukan)
     * method ini di eksekusi di job
     *
     * @param array $addsJobsParam Parameter tambahan untuk jobs
     * @return void
     */
    public function initImportOnJob(array $addsJobsParam = [])
    {
        //jika ternyata tidak mengoverload method ini,
        //maka saat method ini dieksekusi di job, cek apakah initImport sudah diproses, jika belum maka eksekusi
        if (!$this->_importFunctionInitialize) {
            $this->initImport(
                $this->_importGroup,
                $this->_importModelHeader,
                $this->_importModelDetail,
                $addsJobsParam
            );
            $this->_importFunctionInitialize = true;
        }
    }


    /**
     * upload file excel import dan
     *
     * @param Request $inputFile request input file
     * @param int $startRow start row ke berapa data mulai diread
     * @param array $addsData data tambahan untuk diinsert ke model header
     * @param string $transactionDate tanggal transaksi saat ini
     *
     * @return false|array record header
     */
    public function startImport(
        $inputFile,
        int $startRow = 2,
        array $addsData = [],
        string $transactionDate = '',
        array $filterParams = []
    ) {
        if (!$this->_importFunctionInitialize) {
            return false;
        }

        $this->setImportTenantId($this->_tenantId ?: config('tenant.id'));
        $this->setImportStartRow($startRow);
        //generate path file import akan diupload
        $path = $this->getImportUploadPath()
            . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $this->_importGroup));
        //simpan nama file aslinya
        $fileName = $inputFile->getClientOriginalName();

        $mimeType = $inputFile->getMimeType();
        $fileExtention = $inputFile->extension();

        // pastikan extensi yang diallow saja
        if (!empty($this->_allowedExt))
            if (!in_array($fileExtention, $this->_allowedExt)) {
                $this->error = __(
                    'validation.mimes',
                    [
                        'attribute' => 'import file',
                        'values' => ' [' . implode(', ', $this->_allowedExt) . ']'
                    ]
                );
                return false;
            }

        // pastikan mimetype yang diallow saja
        if (!empty($this->_allowedMimeType))
            if (!in_array($mimeType, $this->_allowedMimeType)) {
                $this->error = __(
                    'validation.mimetypes',
                    [
                        'attribute' => 'import file',
                        'values' => ' [' . implode(', ', $this->_allowedExt) . ']'
                    ]
                );
                return false;
            }

        //upload file import nya
        $filePath = $inputFile->store($path);
        //proses jika upload berhasil
        if ($filePath) {
            try {

                $this->setImportStartProcess([
                    'filenamePath' => $filePath,
                    'filename' => $fileName,
                    'transactionDate' => $transactionDate ?: now()->format('Y-m-d')
                ]);

                $config = $this->getImportStatus();

                if ($this->_importModelHeader) {
                    $addsData['import_filepath'] = $filePath;
                    $addsData['import_filename'] = $fileName;
                    $addsData['import_log'] = '';
                    $addsData['import_status'] = 0;
                    $addsData['is_import'] = 1;
                    $addsData['created_at'] = now()->format('Y-m-d');
                    $result = $this->_importModelHeader->create($addsData);
                    if (!$result) {
                        $this->error = 'Insert error.';
                        return false;
                    }
                    $config['addsData'] = $addsData;
                }

                $config['filterParams'] = $filterParams;
                $this->saveImportStatus($config);
                Log::info(['import saved', $config]);

                // mulai jobs untuk proses import nya
                if ($this->isImportJobsPerTenant()) {
                    ResImport::dispatch(
                        self::class,
                        $this->getImportStartRow(),
                        $this->_importAddsJobsParam
                    )->onQueue('tenant' . $this->_tenantId);
                } else {
                    ResImport::dispatch(
                        self::class,
                        $this->getImportStartRow(),
                        $this->_importAddsJobsParam
                    );
                }

                return $config;
            } catch (Exception $e) {
                $this->error = $e->getMessage();
                Log::error('Job Import::startImport() ERROR');
                Log::error($e->getTraceAsString());
            }
        } else {
            $this->error = 'File import tidak terdeteksi.';
        }
        return false;
    }

    /**
     * Check if Job is executed per Tenant
     *
     * @return boolean
     */
    public function isImportJobsPerTenant()
    {
        return config('AppConfig.system.jobs.multitenant_add', false) && !empty($this->perTenant)
            && $this->_tenantId > 0
            ? true : false;
    }

    /**
     * set params import, sebagai penanda bahwa jobs ini adalah kelanjutan dari jobs sebelumnya
     * (jika si $resumeParams nya tidak kosong)
     *
     * @param array $resumeParams Parameter Jobs saat resume jobs
     */
    public function setImportAsResume(array $resumeParams = [])
    {
        $this->_resumeParams = $resumeParams;
        if (!empty($this->_resumeParams)) $this->onImportResume();
    }

    /**
     * Get Resume Jobs Parameter
     *
     * @return array
     */
    public function getImportResumeParam()
    {
        return $this->_resumeParams;
    }

    /**
     * untuk diOVERRIDE
     * dieksekusi saat pertama kali import diresume
     */
    public function onImportResume()
    {
    }

    /**
     * proses file import yang sudah diupload
     *
     * @return bool false jika gagal
     */
    public function importProcess()
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $startTime = microtime(true);

        if (!$this->_importFunctionInitialize) {
            return false;
        }

        $config = $this->getImportStatus();
        $header = [];
        if ($this->_importModelHeader) {
            // Log::info($this->_importModelHeader->toSql());
            $header = $this->_importModelHeader
                ->where('import_status', 0)
                ->where('is_import', 1)
                ->first();
            Log::info(['import $header', $this->_importAddsJobsParam, $header]);
            if (!$header) {
                $this->appendImportLog('<b class="text-danger">Import file not found!</b><br>');
                $this->setImportFailed();

                $this->error = 'Tidak ada file import yang sudah diupload';
                return false;
            }
            $header = $header->toArray();
        } else {
            $header = $config['addsData'];
        }

        /**
         * proses import
         */

        //jika jobs pertama maka
        if (empty($this->_resumeParams)) {

            $this->appendExportLog('<span class="text-info">Jobs started at : <b>'
                . now()->format('Y-m-d H:i:s')
                . '</b></span>...<br>');
            $this->appendImportLog('Load file <b>' . $config['filename'] . '</b><br>');

            $reader = Excel::load(
                Tenant::storage($this->_tenantId)->path($config['filenamePath']),
                'xlsx',
                false
            );
            $reader->setActiveSheetIndex(0);

            $startRecord = $this->getImportStartRow();

            $this->appendImportLog('Reading excel data, please wait...<br>');

            //read data di file excel
            $data = Excel::readRow($reader, $startRecord, 500, [$this, 'importReadExcelCall']);

            $config['count'] = count($data);
            $config['log'] .= '<br><b class="text-info">Read complete !</b><br/>';
            $config['log'] .= 'Data count : <b>' . $config['count'] . '</b><br/>...<br/>';
            $config['log'] .= 'Start importing to database, please wait...<br/>';
            $this->saveImportStatus($config);
            $row = 1;
        } else {
            $this->appendImportLog('<span class="text-info">Continueing process '
                . 'from previous jobs</span>...<br>');
            $this->appendExportLog('<span class="text-info">Jobs started at : <b>'
                . now()->format('Y-m-d H:i:s')
                . '</b></span>...<br>');

            $this->appendImportLog('Reload file <b>' . $config['filename'] . '</b><br>');

            $reader = Excel::load(
                Tenant::storage($this->_tenantId)->path($config['filenamePath']),
                'xlsx',
                false
            );
            $reader->setActiveSheetIndex(0);

            $this->appendImportLog('Re-Reading excel data, please wait...<br>');

            $startRecord = $this->getImportStartRow() + $this->_resumeParams['lastExcelRow'];

            //read data di file excel
            $data = Excel::readRow($reader, $startRecord, 500, [$this, 'importReadExcelCall']);

            $row = $this->_resumeParams['lastExcelRow'] + 1;
        }

        /**
         * ubah format column header caption dari lib Excel ke format column import
         */
        $defaultHeaderColumn = array_map(function ($v) {
            return [strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $v)), []];
        }, Excel::getColumnHeader());
        $this->setImportDefaultColumn($defaultHeaderColumn);

        /**
         * Mulai pross insert ke table detail
         */
        foreach ($data as $col => $val) {

            //convert row excel ke struktur insert sesuai fungsi yang
            $insertRow = $this->formatImportExcelRowAfter(
                $this->formatImportExcelRow($val, $header, $row),
                $val,
                $header,
                $row
            );

            //jika kosong berarti error, maka skip
            if (empty($insertRow)) {
                $row++;
                continue;
            }

            $insertRow['created_at'] = now();
            $result = $this->_importModelDetail->create($insertRow);

            //cek apakah insert berhasil
            if ($result) {
                $this->appendImportLog('. ');
            } else {
                $this->appendImportLog('<b class="text-danger">. [FAIL : ' . $row . '] SKIPPED</b> ');
            }

            $this->importIncrementProcessedCount();
            //break proses setiap kurang dari 1 jam
            if ((microtime(true) - $startTime) >= 3500) {
                $data = null;
                unset($data);
                $this->onBreakToNextImport();
                $this->breakToNextImport($reader, $row);
                return true;
            }
            $row++;
            usleep(1000);
        }

        //pastikan semua selesai dan memory di-free-kan kembali
        $reader->disconnectWorksheets(); // Good to disconnect
        $reader->garbageCollect(); // Add this too
        $reader = null;
        $data = null;
        unset($reader, $data);

        $this->importProcessAfter();

        //ubah status jadi ok
        $this->setImportFinish();
        return true;
    }

    /**
     * untuk di OVERRICE
     * dieksekusi sebelum jobs akan dipecah ke next job
     */
    public function onBreakToNextImport()
    {
    }

    /**
     * diset
     */
    private function breakToNextImport(&$reader, $lastExcelRow = 1)
    {
        $this->appendImportLog('<br><span class="text-info">Break process to the '
            . 'next job, please wait</span>...<br>');

        //pastikan semua selesai dan memory di-free-kan kembali
        $reader->disconnectWorksheets(); // Good to disconnect
        $reader->garbageCollect(); // Add this too
        $reader = null;
        unset($reader);

        $resumParams = $this->getImportResumeParam();
        $resumParams['lastExcelRow'] = $lastExcelRow;

        if ($this->isImportJobsPerTenant()) {
            ResImport::dispatch(
                self::class,
                $this->getImportStartRow(),
                $this->_importAddsJobsParam,
                $resumParams,
                $this->_tenantId
            )->onQueue('tenant' . $this->_tenantId);
        } else {
            ResImport::dispatch(
                self::class,
                $this->getImportStartRow(),
                $this->_importAddsJobsParam,
                $resumParams
            );
        }
    }

    /**
     * untuk di OVERRIDE
     * Untukprocess lanjutan setelah insert semua ke database sebelum finish
     */
    public function importProcessAfter()
    {
    }

    /**
     * call back per row excel yg diread, jika diperlukan, defaultnya tambah log titik sebagai tanda progress.
     *
     * @param array $row array row excel
     * @param int $rowNumber data ke berepa yg sedang diproses
     *
     */
    public function importReadExcelCall(array $row = [], int $rowNumber)
    {
        $this->appendImportLog('. ');
    }

    /**
     * memformat row excel menjadi array insert database
     *
     * @param array $row array row excel
     * @param array $header array row database data header atau addsData dari status
     * @param int $rowNumber nomor urut baris/data saat ini yg sedang diproses
     *
     * @return array
     */
    public function formatImportExcelRow(array $row = [], array $header = [], int $rowNumber)
    {
        $insertRow = [];
        $i = 0;
        //ambil format maping kolom excel ke field database beserta konfig formatnya
        $importColumn = $this->_importColumn ?: $this->getImportDefaultColumn();

        foreach ($importColumn as $column => $format) {
            $i++;
            //jika required
            if (isset($format[1]['required']) && empty($row[$column])) {
                $this->appendImportLog('<b class="text-danger">. [row : ' . $rowNumber
                    . ', column : ' . $column
                    . ' is required] SKIPPED</b>');
                $insertRow = [];
                break;
            } else {
                $insertRow[$format[0]] =
                    empty($row[$column]) && isset($format[1]['default'])
                    ? $format[1]['default']
                    : $this->importFormatRowValue($row[$column], $format[1]);
            }
        }

        $insertRow['is_import'] = 1;

        //tambahkan field foreign key ke table header dari table detail
        if ($this->_importModelHeader)
            $insertRow[$this->getImportDetailForeignKey()] = $header['id'] ?? 0;
        // if($rowNumber<5)Log::info($insertRow);
        return $insertRow;
    }

    /**
     * Untuk nambah pemformatan setelah formating default dieksekusi.
     * method untuk di overide, untuk menyesuaikan kolom mana diinsert ke field mana
     *
     * @param array $insertRow array row yang sudah diformat oleh formatImportExcelRow
     * @param array $row array row dari excel
     * @param array $header array row database data header
     * @param int $rowNumber nomor urut baris/data saat ini yg sedang diproses
     *
     * @return array
     */
    public function formatImportExcelRowAfter(
        array $insertRow = [],
        array $row = [],
        array $header = [],
        int $rowNumber
    ) {
        return $insertRow;
    }

    /**
     * memformat value cell sesuai config format columnya
     *
     * @param mixed value per cell/field dari database
     * @param array format, format dari konfig column, format :
     *  [
     *
     *      'required'=>true, --> sertakan jika field ini harus terisi
     *      'default' => DEFAULT VALUE YANG DIGUNAKAN JIKA VALUE KOSONG ATAU JIKA TIDAK SESUAI FORMAT/TYPE
     *      'type' => 'type' ---> string, number, date, datetime, auto (default)
     *      'format' =>  ''--> format tambahan dari type, misal type date isi format 'Y-m-d'
     *  ]
     * @return mixed Formatted Value
     */
    protected function importFormatRowValue($value, array $format = [])
    {
        if (isset($format['type'])) {
            switch ($format['type']) {
                case 'string':
                    $value = (string) $value;
                    break;
                case 'date':
                    $format['format'] = $format['format'] ?: 'Y-m-d';
                    // $value = (new Carbon($value))->format($format['format']);
                    $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                        ->format($format['format']);
                    break;
                case 'datetime':
                    $format['format'] = $format['format'] ?: 'Y-m-d h:m:s';
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
     * tandai proses import sudah mulai
     *
     * @param array $fileRecord array filename, filepath & transactionDate
     *
     * @return void
     */
    protected function setImportStartProcess(array $fileRecord)
    {
        if (!$this->_importFunctionInitialize) {
            return;
        }

        $config = $this->getInitImportStatus();
        $config['filename'] = $fileRecord['filename'];
        $config['filenamePath'] = $fileRecord['filenamePath'];
        $config['transactionDate'] = $fileRecord['transactionDate'];
        $config['log'] = '<b class="text-success">Start - import !</b><br>';
        $config['status'] = self::$IMPORT_STATUS_ON_PROGRESS; //1: onprogress

        $this->saveImportStatus($config);
    }

    /**
     * set proses import selesai dan berhasil (tapi belum approve)
     *
     * @return void
     */
    protected function setImportFinish()
    {
        $config = $this->getImportStatus();
        $config['log'] .= '<br><b class="text-success">Import Finished !</b><br>';
        $config['log'] .= '<span class="text-info">Jobs ended at : <b>' . now()->format('Y-m-d H:i:s') . '</b></span>';
        $config['status'] = self::$IMPORT_STATUS_SUCCESS; //3: success
        $this->saveImportStatus($config);
    }

    /**
     * set proses import selesai tapi gagal
     *
     * @return void
     */
    protected function setImportFailed()
    {
        if (!$this->_importFunctionInitialize) {
            return;
        }

        $config = $this->getImportStatus();
        Tenant::storage($this->_tenantId)->delete($config['filenamePath']);
        $config['log'] .= '<br><b class="text-danger">Import Failed !</b><br>';
        $config['log'] .= '<span class="text-info">Jobs ended at : <b>' . now()->format('Y-m-d H:i:s') . '</b></span>';
        $config['filename'] = '';
        $config['filenamePath'] = '';
        $config['status'] = self::$IMPORT_STATUS_FAILED; //4: failed
        $this->saveImportStatus($config);
    }

    /**
     * set dari cronjob, jika cronjob ada uncaught error
     *
     * @return void
     */
    public function setImportJobFailed(Throwable $exception, string $log = '')
    {
        if (!$this->_importFunctionInitialize) {
            return;
        }

        $this->setImportFailed();

        $log = "<br><b class='text-danger'>Jobs terminated !</b>\n<hr>\n\nError message :<br>\n";
        $log .= $exception->getMessage();
        $log .= '<hr>';
        $log .= str_replace("\n", '<br>', $exception->getTraceAsString());

        $this->appendImportLog($log);
        report($exception); //lanjutkan error ke login (meureun)
    }

    /**
     * BLOCK APPROVE PROCESS
     */
    /**
     * set saat meng-approve data2 yg sudah diimport.
     * Override method ini untuk memanipulasi proses approve
     *
     * @return false|array false saat gagal approve
     */
    public function importApprove()
    {
        $header = [];
        if ($this->_importModelHeader)
            $header = $this->_importModelHeader->where('import_status', 0)->where('is_import', 1)->first()->toArray();
        $this->importApproveProcess($header);
        return $this->setImportApproveDone();
    }

    /**
     * UNTUK DI OVERRIDE
     */
    public function importApproveProcess(array $headerRecord = [])
    {
    }

    /**
     * tandai proses sebagai "approve process on progress". Digunakan untuk proses approve yg
     * memerlukan proses tambahan yg cukup lama, misal via jobs,
     *
     * @return array
     */
    public function setImportApprove()
    {
        $config = $this->getImportStatus();
        $config['log'] .= '<br><b class="text-info">Import Approved !</b><br>';
        $config['status'] = self::$IMPORT_STATUS_ON_APPROVE_PROGRESS;
        $this->saveImportStatus($config);
        return $config;
    }


    /**
     * tandai proses sebagai telah beres di approve, jadi bisa melakukan import yg lain
     *
     * @return array
     */
    public function setImportApproveDone()
    {
        $config = $this->getImportStatus();
        Log::info('Import Approve ' . $this->_importGroup . ' DONE : ' . $config['log']);
        if ($this->_importModelHeader) {
            $importHeader = ['import_status' => 1, 'import_log' => $config['log']];
            $this->_importModelHeader->where('import_status', 0)->where('is_import', 1)->update($importHeader);
        }
        $this->_importModelDetail->where('import_status', 0)->where('is_import', 1)->update(['import_status' => 1]);

        $this->saveImportStatus($this->getInitImportStatus());
        return $this->getImportStatus();
    }

    /**
     * BLOCK CANCEL PROCESS
     */

    /**
     * saat proses import yang sudah selesai di cancel
     *
     * @return false|array false jika gagal
     */
    public function ImportCancel()
    {
        $header = [];
        if ($this->_importModelHeader)
            $header = $this->_importModelHeader->where('import_status', 0)->where('is_import', 1)->first()->toArray();
        $this->setImportCancelProcess($header);
        return $this->setImportCancelDone();
    }

    public function setImportCancelProcess(array $headerRecord = [])
    {
    }
    /**
     * tandai proses sebagai "cancel process on progress". Digunakan untuk proses cancel yg
     * memerlukan proses tambahan yg cukup lama, misal via jobs,
     *
     * @return array
     */
    public function setImportCancel()
    {
        $config = $this->getImportStatus();
        $config['log'] .= '<br><b class="text-danger">Import Canceled !</b><br>';
        $config['status'] = self::$IMPORT_STATUS_ON_CANCEL_PROGRESS;
        $this->saveImportStatus($config);
        return $config;
    }
    /**
     * saat proses import yagn sudah selesai di cancel
     *
     * @return array
     */
    public function setImportCancelDone()
    {
        $config = $this->getImportStatus();
        Log::info('Import Canceled ' . $this->_importGroup . ' DONE : ' . $config['log']);

        if ($this->_importModelHeader)
            $this->_importModelHeader->where('import_status', 0)->where('is_import', 1)->delete();

        if ($this->_importModelDetail)
            $this->_importModelDetail->where('import_status', 0)->where('is_import', 1)->delete();

        Tenant::storage($this->_tenantId)->delete($config['filenamePath']);

        $this->saveImportStatus($this->getInitImportStatus());
        return $this->getImportStatus();
    }

    /**
     * cek apakah sekarang dalam konsisi bisa tambah import baru
     *
     * @return boolean true jika bisa tidak ada import yg sedang diproses, false jika sedang ada proses import
     */
    public function canNewImport()
    {
        return $this->_importModelDetail->where('import_status', 0)
            ->where('is_import', 1)->exists() ? false : true;
    }

    /**
     * cek apakah ada file import yg sudah diupload dan bisa untuk diproses, jika sedang diproses maka akan false
     *
     * @return boolean true jika bisa diproses, false jika sedang tidak bisa diproses
     */
    public function isImportReady()
    {
        return $this->getImportStatus()['status'] == self::$IMPORT_STATUS_READY
            && !$this->canNewImport();
    }

    /**
     * Get Status Import
     *
     * @return false|array false jika gagal
     */
    public function getImportStatus()
    {
        if (!$this->_importFunctionInitialize) {
            return false;
        }

        if (!($config = $this->_getCache('import', $this->_importGroup))) {
            $config = $this->getInitImportStatus();
        }
        // $config['status'] = self::$IMPORT_STATUS_SUCCESS;//3: success
        $config['import_group'] = $this->_importGroup;
        $this->saveImportStatus($config);
        return $config;
    }

    /**
     * Get Default Status array
     *
     * @return array
     */
    protected function getInitImportStatus()
    {
        $config = [];
        $config['status'] = self::$IMPORT_STATUS_READY;
        $config['log'] = '';
        $config['filename'] = '';
        $config['filenamePath'] = '';
        $config['count'] = 0;
        $config['processedCount'] = 0;
        $config['transactionDate'] = now()->format('Y-m-d');
        $config['addsData'] = [];
        $config['filterParams'] = [];

        return $config;
    }

    /**
     * Increase processed countg
     *
     * @return void
     */
    protected function importIncrementProcessedCount()
    {
        $config = $this->getImportStatus();
        $config['processedCount']++;
        $this->saveImportStatus($config);
    }

    /**
     * Append Import Log
     *
     * @param string $log
     * @return void
     */
    protected function appendImportLog(string $log = '')
    {
        if (!$this->_importFunctionInitialize) {
            return;
        }

        $config = $this->getImportStatus();
        $config['log'] .= $log;
        $this->saveImportStatus($config);
    }

    /**
     * Save Import Status to cache
     *
     * @param array $config
     * @return void
     */
    protected function saveImportStatus($config)
    {
        if (!$this->_importFunctionInitialize) {
            return;
        }

        $this->_saveCache('import', $this->_importGroup, $config);
    }

    /**
     * Convert Excel Timestamp Value ke DateTime Object
     *
     * @param float|int $value
     * @param array $config Array Status
     * @return void
     */
    protected function excelToDateTimeObject($value, $config)
    {
        try {
            $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
            return $value->format('Y-m-d');
        } catch (Exception $e) {
            try {
                $value = Carbon::createFromFormat('d/m/Y', $value);
                return $value->format('Y-m-d');
            } catch (Exception $e2) {
                report($e2);
                $config['log'] .= "<b class='text-danger'>Warning > </b> Format tanggal "
                    . $value . " tidak terdeteksi, gunakan format <b>YYYY-MM-DD</b><br>\n";
                $config['log'] .= "<b class='text-danger'>Jobs terminated!</b><br>\n "
                    . "Silahkan batalkan proses import dan perbaiki format tanggal yang keliru, "
                    . "lalu ulangi proses import.";
                $config['filename'] = '';
                $config['status'] = 3; //3: failed
                $this->saveImportStatus($config);
                Tenant::storage($this->_tenantId)->delete($config['filename']);
                die();
            }
        }
    }

    /**
     * Get list item-item yang sedang proses import
     *
     * @param array $filter
     * @param integer $offset
     * @param integer $limit
     * @param array $orderBy
     * @return void
     */
    public function listImport(
        array $filter = [],
        int $offset = 0, int
        $limit = 0, array
        $orderBy = []
    ) {
        return $this->_list(
            $this->_importModelDetail
                ->where('import_status', 0)
                ->where('is_import', 1),
            $filter,
            $offset,
            $limit,
            $orderBy
        );
    }
}
