<?php

namespace App\Services\export;

use App\Facades\Excel;

use App\Jobs\Export as JExport;

// use App\Services\export\BaseExport;

class ExportSpreadsheet extends BaseExport
{
    protected $_defaultDriver = 'phpspreadsheet';

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

        // jika resume dari jobs sebelumnya yang di split
        if ($exportData['isResumeJob']) {

            $exportData['resumeJobParam']['jobStartTime'] = now()->format('Y-m-d H:i:s');
            $this->updateExport($cacheKey, $exportData);

            $this->appendExportLog($cacheKey, '<span class="text-info">Continueing '
                . 'process from previous jobs</span>...<br>');
            $this->appendExportLog($cacheKey, '<span class="text-info">Jobs started at : <b>'
                . now()->format('Y-m-d H:i:s') . '</b></span><br>');

            $reader = Excel::load($exportData['filepath'], 'Xlsx', false);
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

            $deleteRow = $exportData['template']['dataStartRow']; //row yg harus didelete, kenapa didelete untuk memastikan style header tidak terbawa
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
            $reader = Excel::load(
                $exportData['template']['filepath'] ?: resource_path('doc/generalExport.xlsx'),
                'Xlsx',
                $exportData['template']['filepath'] ? false : true
            );

            $GLOBALS['synapse_export_indexExcelRow'] = $exportData['template']['dataStartRow']; //urutan baris excel
            $deleteRow = $GLOBALS['synapse_export_indexExcelRow']; //row yg harus didelete, kenapa didelete untuk memastikan style header tidak terbawa
            $GLOBALS['synapse_export_indexExcelRow']++; //start row ditambah satu agar style header tidak terbawa, karena nanti first row ini akan didelete juga
            $GLOBALS['synapse_export_indexData'] = 1; //nomor urut data dari 1 dst
            $isFirstRow = true; //flag untuk penanda baris pertama dari data
            $offset = 0;
            $limit = null;
        }

        if (!is_array($exportData['listingModel']) && !empty($exportData['listingParams']['orderBy'])) {

            if (!is_array($exportData['listingParams']['orderBy'][0]))
                $exportData['listingParams']['orderBy'] = [$exportData['listingParams']['orderBy']];

            foreach ($exportData['listingParams']['orderBy'] as $oBitem) {
                $data = $data->orderBy($oBitem[0], $oBitem[1]);
            }
        }

        /**
         * proses export
         */

        $reader->setActiveSheetIndex(0);
        $GLOBALS['synapse_export_isBreaking'] = false;
        $this->chunkWithLimit($data, 100, $offset, $limit, function ($chunkedData) use (
            $cacheKey,
            $isFirstRow,
            &$reader,
            $startTime,
            $exportData
        ) {

            $chunkedData = $chunkedData->toArray();

            usleep(200);

            foreach ($chunkedData as $dataRow) {
                if (!empty($exportData['template']['coreMainLoopingMethod'])) {
                    $exportLoopModel = $exportData['template']['coreMainLoopingMethod'][0];
                    $exportLoopMethod =$exportData['template']['coreMainLoopingMethod'][1];
                    $exportLoopModel::{$exportLoopMethod}(
                        $exportData,
                        $reader,
                        $dataRow
                    );
                    continue;
                }

                // jika false berarti di cancel
                if ($this->_checkAndCounter($cacheKey) == false) {
                    return false;
                }

                $this->exportIncrementProcessedCount($cacheKey);

                //jika tanpa template dan row 1 maka simpan nama2 kolomnya, untuk dijadikan header caption
                if ($isFirstRow && empty($exportData['template']['filepath'])) {
                    $headerColumn = $this->formatExportExcelHeader($cacheKey, $dataRow);

                    //kolom terakhir header
                    $countHeader = count($headerColumn);
                    $reader = Excel::setCell($reader, $headerColumn);
                    $reader = Excel::setBorder($reader, 'A1:' . Excel::excol($countHeader) . '1');
                    $reader = Excel::setFontBold($reader, 'A1:' . Excel::excol($countHeader) . '1');
                    $reader = Excel::setBackground($reader, 'A1:' . Excel::excol($countHeader) . '1', 'CCCCCC');
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
                        $GLOBALS['synapse_export_indexData']
                    );
                }

                $reader = Excel::insertRow($reader, $GLOBALS['synapse_export_indexExcelRow'], $insertRow);
                $GLOBALS['synapse_export_indexExcelRow']++;
                $GLOBALS['synapse_export_indexData']++;
            }

            //break proses setiap kurang dari setengah jam
            if ((microtime(true) - $startTime) >= 1800) {
                $chunkedData = null;
                unset($chunkedData);
                $this->breakToNextExport(
                    $cacheKey,
                    $reader,
                    $GLOBALS['synapse_export_indexExcelRow'],
                    $GLOBALS['synapse_export_indexData']
                );
                $GLOBALS['synapse_export_isBreaking'] = true;
                return false;
            }
            usleep(500);
        });

        $exportData = $this->getExport($cacheKey);
        if ($exportData == false || $exportData['forceCancle'] == 1) return false;

        if ($GLOBALS['synapse_export_isBreaking']) return true;

        if ($deleteRow) $reader->getActiveSheet()->removeRow($deleteRow);

        if (!empty($exportData['template']['coreLastFormaterMethod'])) {
            $exportLastModel = $exportData['template']['coreLastFormaterMethod'][0];
            $exportLastMethod = $exportData['template']['coreLastFormaterMethod'][1];
            $exportLastModel::{$exportLastMethod}($exportData, $reader);
        }

        $exportData = $this->getExport($cacheKey);
        $exportData['count'] = $GLOBALS['synapse_export_indexData'] - 1;
        $this->updateExport($cacheKey, $exportData);

        $this->appendExportLog($cacheKey, '<br>Save file to : ' . $exportData['filename'] . '<br>');

        Excel::save($reader, $exportData['filepath']);

        //pastikan semua selesai dan memory di-free-kan kembali
        $reader->disconnectWorksheets(); // Good to disconnect
        $reader->garbageCollect(); // Add this too
        $reader = null;
        $data = null;
        unset($reader, $data);

        //ubah status jadi ok
        $this->setExportDone($cacheKey);

        return true;
    }


    /**
     * format header phpspreadsheet
     */
    protected function formatExportExcelHeader($cacheKey, array $row1 = [])
    {
        $exportData = $this->getExport($cacheKey);
        $headerColumn = [];
        //jika ada format column maka gunakan format column
        if (!empty($exportData['template']['headerCaption'])) {
            $i = 0;
            foreach ($exportData['template']['headerCaption'] as $format) {
                $i++;
                $headerColumn[Excel::excol($i) . '1'] = empty($format[1]['caption'])
                    ? str_replace('_', ' ', $format[0])
                    : $format[1]['caption'];
            }
        } else {
            $i = 0;
            foreach ($row1 as $fieldName => $fieldValue) {
                $i++;
                $headerColumn[Excel::excol($i) . '1'] = str_replace('_', ' ', $fieldName);
            }
        }
        return $headerColumn;
    }

    /**
     * phpspreadsheet
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
                $insertRow[Excel::excol($i)] =
                    empty($row[$format[0]]) && isset($format[1]['default']) ?
                    $format[1]['default'] :
                    $this->exportFormatRowValue($row[$format[0]], $format[1]);
            }
        } else {
            foreach ($row as $fieldValue) {
                $i++;
                $insertRow[Excel::excol($i)] = is_array($fieldValue) ? '' : $fieldValue;
            }
        }

        return $insertRow;
    }


    /**
     * saat jobs dipecah ke jobs selanjurnya
     */
    private function breakToNextExport($cacheKey, &$reader, $lastExcelRow = 1, $lastTableRow = 1)
    {
        $exportData = $this->getExport($cacheKey);
        if ($exportData == false) return false;

        $this->appendExportLog(
            $cacheKey,
            '<br><span class="text-info">Break process to the next job, please wait</span>...<br>'
        );

        Excel::save($reader, $exportData['filepath']);

        //pastikan semua selesai dan memory di-free-kan kembali
        $reader->disconnectWorksheets(); // Good to disconnect
        $reader->garbageCollect(); // Add this too
        $reader = null;
        unset($objWriter, $reader);

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
