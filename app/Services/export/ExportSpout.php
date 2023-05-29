<?php

namespace App\Services\export;

use Exception;
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


use App\Models\Job;

use App\Jobs\Export as JExport;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

// use App\Services\export\BaseExport;

class ExportSpout extends BaseExport
{
    protected $_defaultDriver = 'spout';
    /**
     * proses utama yang dieksekusi dari jobs
     */
    public function processExport($cacheKey, $curQueue = 'export1')
    {
        ini_set('memory_limit', '5524M');
        set_time_limit(0);

        $startTime = microtime(true);

        /**
         * init status & var
         */
        $exportData = $this->getExport($cacheKey);
        $exportData['status'] = self::EXPORT_STATUS_ON_PROGRESS;
        $exportData = $this->_initExportData($exportData, $curQueue);

        $tenantPath = '';
        // if(config('AppConfig.system.multitenant.active'))
        //     $tenantPath = 'tenant_'.$exportData['tenantId'].'/';

        $tmpFilename = Storage::disk(Tenant::storageGetDiskLocal($exportData['tenantId']))->path(
            $tenantPath . 'synapse_cache'
                . DS . 'export_tmp'
                . DS . $exportData['cacheKey'] . '_' . $exportData['jobsId'] . '_'
                . now()->format('YmdHis') . '.xlsx'
        );

        $newDir = dirname($tmpFilename);
        if (!file_exists($newDir)) {
            mkdir($newDir, 0755, true);
        }

        // $tmpFilename = storage_path('app'.DS.'synapse_cache'.DS.'export_tmp'.DS.$exportData['cacheKey'].'_'.$exportData['jobsId'].'_'.now()->format('YmdHis').'.xlsx');
        $file = fopen($tmpFilename, 'w');
        fclose($file);

        // jika resume dari jobs sebelumnya yang di split
        if ($exportData['isResumeJob']) {

            $exportData['resumeJobParam']['jobStartTime'] = now()->format('Y-m-d H:i:s');
            $this->updateExport($cacheKey, $exportData);

            $this->appendExportLog($cacheKey, '<span class="text-info">Continueing '
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
                    $exportData['listingParams']
                );
            } else {
                $data = new $exportData['listingModel'];
                $data = $this->_filter($data, $exportData['listingParams']['filter']);
            }

            $exportData['count'] = $data->count();
            $exportData['jobStartTime'] = now()->format('Y-m-d H:i:s');

            $this->updateExport($cacheKey, $exportData);

            $this->appendExportLog($cacheKey, '<span class="text-info">Jobs started at : <b>'
                . now()->format('Y-m-d H:i:s') . '</b></span><br>');
            $this->appendExportLog($cacheKey, 'Url will be at : ' . $exportData['fileurl'] . '<br>');


            $tmpFileReader = $exportData['template']['filepath'] ?: resource_path('doc/generalExport.xlsx');

            // we need a reader to read the existing file...
            $reader = ReaderEntityFactory::createReaderFromFile($tmpFileReader);
            $reader->setShouldFormatDates(true); // this is to be able to copy dates
            $reader->open($tmpFileReader);

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

        if (!is_array($exportData['listingModel']) && !empty($exportData['listingParams']['orderBy'])) {

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
        $this->chunkWithLimit($data, 100, $offset, $limit, function ($chunkedData) use (
            $cacheKey,
            $isFirstRow,
            &$reader,
            &$writer,
            $startTime,
            $exportData,
            $border,
            $styleBorder,
            $tmpFilename
        ) {

            $chunkedData = $chunkedData->toArray();

            usleep(100);

            foreach ($chunkedData as $dataRow) {
                if (!empty($exportData['template']['coreMainLoopingMethod'])) {
                    $exportLoopModel = $exportData['template']['coreMainLoopingMethod'][0];
                    $exportLoopMethod = $exportData['template']['coreMainLoopingMethod'][1];
                    $exportLoopModel::{$exportLoopMethod}(
                        $exportData,
                        $reader,
                        $writer,
                        $dataRow
                    );
                    continue;
                }

                // jika false berarti di cancel
                if ($this->_checkAndCounter($cacheKey) == false) {
                    return false;
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

                if (!empty($exportData['template']['coreRowFormaterMethod'])) {
                    $exportFormatterModel = $exportData['template']['coreRowFormaterMethod'][0];
                    $exportFormatterMethod = $exportData['template']['coreRowFormaterMethod'][1];
                    $insertRow = $exportFormatterModel::{$exportFormatterMethod}(
                        $exportData,
                        $insertRow,
                        $dataRow,
                        $GLOBALS['synapse_export_indexExcelRow'],
                        $GLOBALS['synapse_export_indexData'] + 1
                    );
                }

                // $reader = Excel::insertRow($reader, $GLOBALS['synapse_export_indexExcelRow'], $insertRow);

                $writer->addRow(
                    WriterEntityFactory::createRowFromArray($insertRow, $styleBorder)
                );

                $GLOBALS['synapse_export_indexExcelRow']++;
                $GLOBALS['synapse_export_indexData']++;
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
        });

        if ($GLOBALS['FORCE_CANCEL']) return false;

        if ($GLOBALS['synapse_export_isBreaking']) return true;

        $exportData = $this->getExport($cacheKey);
        $exportData['count'] = $GLOBALS['synapse_export_indexData'];
        $this->updateExport($cacheKey, $exportData);

        $this->appendExportLog($cacheKey, '<br>Save file to : ' . $exportData['filename'] . '<br>');

        $reader->close();
        $writer->close();

        // unlink($exportData['filepath']);
        // move dari cache ke file export nya
        rename($tmpFilename, $exportData['filepath']);

        if (!empty($exportData['template']['coreLastFormaterMethod'])) {
            $exportLastFormatterModel = $exportData['template']['coreLastFormaterMethod'][0];
            $exportLastFormatterMethod = $exportData['template']['coreLastFormaterMethod'][1];
            $exportLastFormatterModel::{$exportLastFormatterMethod}(
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

    protected function formatExportExcelHeader($cacheKey, array $row1 = [])
    {
        $exportData = $this->getExport($cacheKey);
        $headerColumn = [];
        //jika ada format column maka gunakan format column
        if (!empty($exportData['template']['headerCaption'])) {
            $i = 0;
            foreach ($exportData['template']['headerCaption'] as $format) {
                $i++;
                $headerColumn[] = empty($format[1]['caption'])
                    ? str_replace('_', ' ', $format[0])
                    : $format[1]['caption'];
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
     * @param array $row array row database (dari model)
     *
     * @return array
     */
    protected function formatExportExcelRow(
        $cacheKey,
        array $row = [],
        int $indexExcelRow,
        int $indexData
    ) {
        $exportData = $this->getExport($cacheKey);
        $insertRow = [];
        $i = 0;
        //jika ada format column maka gunakan format column
        if (!empty($exportData['template']['headerCaption'])) {
            foreach ($exportData['template']['headerCaption'] as $format) {
                $i++;
                $insertRow[] =
                    empty($row[$format[0]]) && isset($format[1]['default']) ?
                    $format[1]['default'] :
                    $this->exportFormatRowValue($row[$format[0]], $format[1]);
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
     * saat jobs dipecah ke jobs selanjurnya
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
        $queueName = $this->nextExportQueue();

        if ($this->isExportJobsPerTenant($cacheKey)) {
            JExport::dispatch($cacheKey, 'tenant' . $exportData['tenantId'] . $queueName)
                ->onQueue('tenant' . $exportData['tenantId'] . $queueName);
        } else {
            JExport::dispatch($cacheKey, $queueName)->onQueue($queueName);;
        }
    }
}
