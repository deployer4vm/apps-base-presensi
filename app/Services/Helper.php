<?php

namespace App\Services;

use Carbon\Carbon;

class Helper
{
    /**
     * Indonesian Months
     *
     * @var array
     */
    protected $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];

    /**
     * Indonesian Days
     *
     * @var array
     */
    protected $hari = [
        'Minggu',
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu'
    ];

    /**
     * Indonesian Month Date Format
     *
     * @param string $dateString
     * @return string
     */
    public function indDateFormat($dateString)
    {
        $date = new Carbon($dateString);
        return $date->day . ' ' . ($this->bulan[$date->month] ?? '') . ' ' . $date->year;
    }

    /**
     * Indonesian Days
     *
     * @param string $dateString
     * @return string
     */
    public function indDay($dateString)
    {
        $date = new Carbon($dateString);
        return $this->hari[$date->dayOfWeek] ?? '';
    }

    /**
     * Indonesian Number Spelling
     */
     protected $bilangan = [
        "",
        "Satu",
        "Dua",
        "Tiga",
        "Empat",
        "Lima",
        "Enam",
        "Tujuh",
        "Delapan",
        "Sembilan",
        "Sepuluh",
        "Sebelas"
    ];

    /**
     * Indonesian Number to Text
     *
     * @param integer $nilai
     * @return string
     */
    public function terbilang($nilai)
    {
        $nilai = abs($nilai);
        $huruf = $this->bilangan;
        $temp = "";
        if ($nilai < 12) {
            $temp = " " . $huruf[$nilai];
        } else if ($nilai < 20) {
            $temp = $this->terbilang($nilai - 10) . " Belas";
        } else if ($nilai < 100) {
            $temp = $this->terbilang($nilai / 10) . " Puluh" . $this->terbilang($nilai % 10);
        } else if ($nilai < 200) {
            $temp = " Seratus" . $this->terbilang($nilai - 100);
        } else if ($nilai < 1000) {
            $temp = $this->terbilang($nilai / 100) . " Ratus" . $this->terbilang($nilai % 100);
        } else if ($nilai < 2000) {
            $temp = " Seribu" . $this->terbilang($nilai - 1000);
        } else if ($nilai < 1000000) {
            $temp = $this->terbilang($nilai / 1000) . " Ribu" . $this->terbilang($nilai % 1000);
        } else if ($nilai < 1000000000) {
            $temp = $this->terbilang($nilai / 1000000) . " Juta"
                . $this->terbilang($nilai % 1000000);
        } else if ($nilai < 1000000000000) {
            $temp = $this->terbilang($nilai / 1000000000) . " Milyar"
                . $this->terbilang(fmod($nilai, 1000000000));
        } else if ($nilai < 1000000000000000) {
            $temp = $this->terbilang($nilai / 1000000000000) . " Trilyun"
                . $this->terbilang(fmod($nilai, 1000000000000));
        }
        return $temp;
    }


    //menambahkan titik untuk ribuan, misal 2000000 jadi 2.000.000
    /**
     * Add . (dot) separator to thousand numeric
     *
     * @param string $str
     * @return string
     */
    public static function agregat($str = '')
    {
        $str = strrev($str);
        $str = preg_replace('/(\d\d\d)(?=\d)/', '$1.', $str);
        $str = strrev($str);
        //$str = number_format($str, 2, ',', '.');
        return $str;
    }

    /**
     * Indonesian Number Formatting
     *
     * @param string $str
     * @param integer $scale Decimal Number
     * @return string
     */
    public static function agregat2($str = '', $scale = 2)
    {
        /*$str = strrev($str);
        $str = preg_replace('/(\d\d\d)(?=\d)/', '$1.', $str);
        $str = strrev($str);*/
        $str = number_format($str, $scale, ',', '.');
        return $str;
    }

    /**
     * Generate Alpha Numeric Random String
     *
     * @param integer $length
     * @return string
     */
    public static function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * BC MATH
     */

    /**
     * Basic Calculator Math Addition
     *
     * @param mixed $num1
     * @param mixed $num2
     * @return string
     */
    public static function bcadd($num1, $num2)
    {
        $num1 = self::bcToString($num1);
        $num2 = self::bcToString($num2);
        return rtrim(rtrim(bcadd($num1, $num2), '0'), '.') ?: '0';
    }


    /**
     * pengurangan
     */
    /**
     * Basic Calculator Math Subtraction
     *
     * @param mixed $num1
     * @param mixed $num2
     * @return string
     */
    public static function bcsub($num1, $num2)
    {
        $num1 = self::bcToString($num1);
        $num2 = self::bcToString($num2);
        return rtrim(rtrim(bcsub($num1, $num2), '0'), '.') ?: '0';
    }

    /**
     * perkalian
     */
    /**
     * Basic Calculator Math Multiplication
     *
     * @param mixed $num1
     * @param mixed $num2
     * @return string
     */
    public static function bcmul($num1, $num2)
    {
        $num1 = self::bcToString($num1);
        $num2 = self::bcToString($num2);
        return rtrim(rtrim(bcmul($num1, $num2), '0'), '.') ?: '0';
    }

    /**
     * pembagian
     */
    /**
     * Basic Calculator Math Division
     *
     * @param mixed $num1
     * @param mixed $num2
     * @return string
     */
    public static function bcdiv($num1, $num2)
    {
        $num1 = self::bcToString($num1);
        $num2 = self::bcToString($num2);
        return rtrim(rtrim(bcdiv($num1, $num2), '0'), '.') ?: '0';
    }
    
    /**
     * Convert nomor desimal
     */
    public static function bcConvertNumber(&$num1, &$num2)
    {
        $num1 = self::bcToString($num1);
        $num2 = self::bcToString($num2);
        
        $des1 = 10^((int) strpos(strrev($num1), "."));
        $des2 = 10^((int) strpos(strrev($num2), "."));

        if($des1>$des2){
            $num1 = $num1 * $des1;
            $num2 = $num2 * $des1;
            return $des1;
        }else{
            $num1 = $num1 * $des2;
            $num2 = $num2 * $des2;
            return $des2;
        }
    }

    /**
     * untuk memastikan jika ada scientifik notation akan diconvert ke decimal biasa
     */
    /**
     * Convert given input into valid BCMath value
     *
     * @param mixed $num
     * @return string
     */
    public static function bcToString($num)
    {
        if (is_string($num)) $num = (float) $num;
        $num = rtrim(sprintf("%.20f", $num), "0");
        return rtrim($num, '.') ?: '0';
    }

    /**
     * Synapse Basic Calculator Matemathic
     * =========================================================================
     */

    /**
     * Synapse Calculator Math Addition (Penambahan)
     *
     * @param mixed $num1
     * @param mixed $num2
     * 
     * @return string
     */
    public static function synadd($num1, $num2)
    {
        $decimalPoin = self::bcConvertNumber($num1, $num2);
        $result = $num1+$num2;
        
        return $result/$decimalPoin;
    }

    /**
     * Synapse Calculator Math Subtraction (Pengurangan)
     *
     * @param mixed $num1
     * @param mixed $num2
     * 
     * @return string
     */
    public static function synsub($num1, $num2)
    {
        $decimalPoin = self::bcConvertNumber($num1, $num2);
        $result = $num1-$num2;

        return $result/$decimalPoin;
    }

    /**
     * Synapse Calculator Math Multiplication (Perkalian)
     *
     * @param mixed $num1
     * @param mixed $num2
     * 
     * @return string
     */
    public static function synmul($num1, $num2)
    {
        $decimalPoin = self::bcConvertNumber($num1, $num2);
        $result = $num1*$num2;

        return $result/($decimalPoin^2);
    }

    /**
     * Synapse Calculator Math Division (Pembagian)
     *
     * @param mixed $num1
     * @param mixed $num2
     * 
     * @return string
     */
    public static function syndiv($num1, $num2)
    {
        $decimalPoin = self::bcConvertNumber($num1, $num2);
        $result = $num1/$num2;
        
        return $result;
    }
}
