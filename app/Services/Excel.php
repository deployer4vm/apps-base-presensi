<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
// use PhpOffice\PhpSpreadsheet\Writer\WXlsx;
// use PhpOffice\PhpSpreadsheet\Reader\RXlsx;

class Excel
{
    protected $columnHeader = [];
    public $TBS;
    public $letterMap = array(
        1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F', 7 => 'G',
        8 => 'H', 9 => 'I', 10 => 'J', 11 => 'K', 12 => 'L', 13 => 'M', 14 => 'N',
        15 => 'O', 16 => 'P', 17 => 'Q', 18 => 'R', 19 => 'S', 20 => 'T', 21 => 'U',
        22 => 'V', 23 => 'W', 24 => 'X', 25 => 'Y', 26 => 'Z'
    );

    /**
     * excel colom, ubah angka kolom ke kolom excel
     */
    public function excol(int $int = 0)
    {
        $hasil = array();

        while ($s1 = floor($int / 26)) {
            $s2 = $int % 26;
            if ($s1 >= 1) {
                if (isset($this->letterMap[$s2])) {
                    array_unshift($hasil, $this->letterMap[$s2]);
                    $int = $s1;
                } else {
                    array_unshift($hasil, 'Z');
                    $int = $s1 - 1;
                }
            } else {
                $int = 26;
                break;
            }
        }
        if (isset($this->letterMap[$int])) array_unshift($hasil, $this->letterMap[$int]);

        $hasil = implode('', $hasil);
        return $hasil;
    }

    /**
     * GRUP EXCEL FORMATING & STYLING
     */

    public function setBorder(&$reader, $cell)
    {
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];

        $reader->getActiveSheet()->getStyle($cell)->applyFromArray($styleArray);
        return $reader;
    }

    public function setFont(&$reader, $cell, array $style = [])
    {
        $styleArray = [
            'font' => $style
        ];

        $reader->getActiveSheet()->getStyle($cell)->applyFromArray($styleArray);
        return $reader;
    }

    public function setFontBold(&$reader, $cell)
    {
        $styleArray = ['font' => ['bold' => true]];
        $reader->getActiveSheet()->getStyle($cell)->applyFromArray($styleArray);
        return $reader;
    }

    public function setFontNormal(&$reader, $cell)
    {
        $styleArray = ['font' => ['bold' => false]];
        $reader->getActiveSheet()->getStyle($cell)->applyFromArray($styleArray);
        return $reader;
    }

    public function setBackground(&$reader, $cell, $bgcolor = 'ffffff')
    {
        $reader->getActiveSheet()
            ->getStyle($cell)
            ->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB($bgcolor);
        return $reader;
    }

    /**
     * GRUP EXCEL READ & WRITE
     */

    /**
     * @param string $template path dokumen
     * @param string $format format excel, "Xls" atau "Xlsx"
     * @param boolean $mainAppDoc true jika path MainApp/resources/doc/*
     */
    public function load($template, string $format = 'Xls', bool $mainAppDoc = true)
    {
        $format = ucfirst(strtolower($format)) == 'Xls' ? 'Xls' : 'Xlsx';
        if (empty($template))
            return $this->create();

        if ($mainAppDoc) {
            $template = app_path('MainApp/resources/doc/' . $template);
        }
        $reader = IOFactory::createReader($format);
        $reader = $reader->load($template); //::createReader("Xlsx")

        return $reader;
    }

    /**
     * create new spreadsheet
     */
    public function create()
    {
        return new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    }

    /**
     * read semua row cell di 1 worksheet aktif
     *
     * @param Reader Object $reader object PhpSpreadsheet reader
     * @param integer $startRow baris dimulai direadnya, mulai 1
     * @param integer $perRowSleep usleep per row looping
     *
     * @return array list data dengan format
     *      [
     *          ["A"=>"cell value","B"=>"cell value",...],
     *          [KOLOM_NAME=>CELL VALUE,...],
     *          ...
     *      ]
     */
    public function readRow(
        &$reader,
        int $startRow = 1,
        int $perRowSleep = 0,
        callable $loppingFunc = null
    ): array {
        if ($startRow <= 1) $startRow = 1;
        $result = [];
        $i = 0;
        foreach ($reader->getActiveSheet()->getRowIterator() as $key =>  $row) {
            //jika baris pertama maka simpan sebagai nama column
            if ($i == 0) {
                $this->setColumnHeader($this->_readRow($row));
            }
            if ($key >= $startRow) {
                // hanya ambil data yang tidak
                if (!$this->isRowEmpty($row)) {
                    $i++;
                    $result[$i] = $this->_readRow($row);
                    if ($loppingFunc && is_callable($loppingFunc)) {
                        $loppingFunc($result[$i], $i);
                    }
                    if ($perRowSleep) usleep($perRowSleep);
                }
            }
        }
        $loppingFunc = null;
        return $result;
    }

    /**
     * Check if row is empty
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\RowIterator $rowIterator
     * @return boolean
     */
    public static function isRowEmpty($rowIterator)
    {
        $cellIterator = $rowIterator->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE);

        foreach ($cellIterator as $cell) {
            if ($cell->getValue()) {
                return false;
            }
        }

        return true;
    }

    /**
     * readrow iterator to array
     */
    private function _readRow($rowIterator)
    {
        $cellIterator = $rowIterator->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE); // This loops through all cells,
        //    even if a cell value is not set.
        // By default, only cells that have a value
        //    set will be iterated.
        $result = [];
        foreach ($cellIterator as $key2 => $cell) {
            $result[$key2] = $cell->getValue();
        }
        $rowIterator = null;
        unset($rowIterator, $cell, $key2);
        return $result;
    }

    public function setColumnHeader(array $row = [])
    {
        $this->columnHeader = $row;
    }

    public function getColumnHeader()
    {
        return $this->columnHeader;
    }

    /**
     * write data ke berdasarkan cell nya
     */
    public function setCell(&$reader, $data)
    {

        foreach ($data as $key => $value) {
            $option = ['quote' => 'auto'];

            //jika array berarti menggunakan format sendiri
            if (is_array($value)) {
                $option['type'] = $value['option']['type'] ?? 'default';
                if (isset($value['option']['quote']))
                    $option['quote'] = $value['option']['quote'] ? 'yes' : 'no';
                $value = $value['value'];
            } else {
                $option['type'] = is_string($value) ? 'string' : 'default';
            }

            if ($option['type'] == 'string') {
                //jika formula
                if (strpos($value, '=') === 0) {
                    $reader->getActiveSheet()->setCellValueExplicit(
                        $key,
                        $value,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA
                    );
                } else {
                    $this->setCellString($reader, $key, $value, $option['quote']);
                }
            } else if ($option['type'] == 'number') {
                $reader->getActiveSheet()->setCellValueExplicit(
                    $key,
                    $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                );
            } else if ($option['type'] == 'percentage') {
                $digit = '';
                if (!isset($option['digit'])) $option['digit'] = 0;
                if ($option['digit'] > 0)
                    $digit = '.' . str_repeat('0', $option['digit']);
                $reader->getActiveSheet()->setCellValue($key, $value);
                $reader->getActiveSheet()
                    ->getStyle($key)
                    ->getNumberFormat()
                    ->setFormatCode('0' . $digit . '%;[Red]-0' . $digit . '%');
                // ->applyFromArray([
                //     "code" => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00
                // ]);
            } else if ($option['type'] == 'currency') {

                if (!isset($option['prefix'])) $option['prefix'] = '';
                if (!isset($option['sufix'])) $option['sufix'] = '';

                if ($option['prefix']) {
                    $format = '"' . $option['prefix'] . '"#,##0.00_-';
                } else {
                    $format = '#,##0.00 "' . $option['sufix'] . '"';
                }
                $reader->getActiveSheet()->setCellValue($key, $value);
                $reader->getActiveSheet()
                    ->getStyle($key)
                    ->getNumberFormat()
                    ->setFormatCode($format);
            } else {
                $reader->getActiveSheet()->setCellValue($key, $value);
            }
        }

        //untuk memastikan memory langsung free tanpa nunggu gc
        $data = null;
        unset($data);

        return $reader;
    }

    private function setCellString(&$reader, $key, $value, string $quote = 'auto')
    {
        //jika value diawali dengan - atau angka maka kasih quote
        if (
            $quote != 'no'
            && (
                $quote == 'yes'
                || strpos($value, '-') === 0
                || preg_match('/^\d/', $value) === 1
            )
        ) {
            $value = "'" . $value;
        }

        $reader->getActiveSheet()->setCellValueExplicit(
            $key,
            $value,
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
        // $reader->getActiveSheet()->getStyle($key)->setQuotePrefix(true);
    }

    public function insertRow(&$reader, $row, $templateVar)
    {
        $reader->getActiveSheet()->insertNewRowBefore($row, 1);
        $newvar = [];
        foreach ($templateVar as $key => $value) {
            $newvar[$key . $row] = $value;
        }
        return $this->setCell($reader, $newvar);
    }

    public function download(&$reader)
    {
        $writer = IOFactory::createWriter($reader, 'Xlsx');
        $writer->save('php://output'); // download file
        //untuk memastikan memory langsung free tanpa nunggu gc
        $writer = null;
        unset($writer);
    }

    public function save(&$reader, $filename)
    {
        $writer = IOFactory::createWriter($reader, 'Xlsx');
        $writer->save($filename); // save file
        //untuk memastikan memory langsung free tanpa nunggu gc
        $writer = null;
        unset($writer);
    }
}
