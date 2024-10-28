<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Base\BaseRepository;

class CacheConfig extends BaseRepository
{
    // protected $cacheActive = true;
    // protected $skipCache = false;
    private $groupKey = 'generalconfig';
    
    /**
     * data cache yang sudah di load di local variable per eksekusi per class (controller, repo, dll), format
     * $cacheData[$cacheMainPrefix.$prefix][$id] = $dataCache;
     */
    protected $cacheData;
    protected $cacheIndex;
    protected $cacheIndexField = [];

    /*
     * $_cachedMethod array list nama method berdasarkan prefix (groupdata) nya
     * format :
     *      ['prefix'=> ['method'],..]
     */
    // private $_cachedMethod = [];
    //prefix utama
    protected $cacheMainPrefix = '';
    protected $cacheActive = true;
    protected $cacheEngine = 'file';
    protected $skipCache = false;

    /**
     * get / set config
     *
     * @param string    $key            key config
     * @param mix       $default        default value yang diset jika key tidak ada, isi null jika
     *                                  tidak set default value
     * @param boolean   $saveDefault    default value yang diset jika key tidak ada
     *
     * @return mix|null                 null jika gagal, $default value yang diset jika berhasil
     */
    public function getConfig($key, $default = null, $saveDefault = true)
    {
        $config = $this->_getCache($this->groupKey, $key);
        if (is_null($config)) {
            if (!is_null($default) && $saveDefault)
                $this->_saveCache($this->groupKey, $key, $default);
            $config = $default;
        }
        return $config;
    }

    /**
     * save config
     *
     * @param string            $key        key config
     * @param mix               $value      value yang diset
     *
     * @return mix|boolean                  false jika gagal, value yang diset jika berhasil
     */
    public function setConfig($key, $value)
    {
        return $this->_saveCache($this->groupKey, $key, $value) ? $value : false;
    }

    /**
     * delete config
     *
     * @param string            $key        key config
     */
    public function deleteConfig($key)
    {
        return $this->_deleteCache($this->groupKey, $key);
    }

    /**
     * =========================================================================
     */
    
    /**
     * get nama field dan value yang dijadikan index cache
     *
     * @param string            $prefix
     * @param mix               $key key filter
     * @param mix               $value value filter
     * @return false|array      false jika tidak ada index
     */
    protected function _getCacheIndex($prefix, $key, $value = false)
    {
        if (is_array($key)) {
            foreach ($key as $field => $val) {
                if (in_array($field, $this->cacheIndexField[$prefix])) {
                    return [
                        'field' => $field,
                        'value' => $val
                    ];
                }
            }
        } else {
            if (in_array($key, $this->cacheIndexField[$prefix])) {
                return [
                    'field' => $key,
                    'value' => $value
                ];
            }
        }
        return false;
    }

    /**
     * set engine cache yg akan digunakan di repo ini
     *
     * @param string            $cacheEngine engine cache nya
     */
    public function setCacheEngine($cacheEngine)
    {
        $this->cacheEngine = $cacheEngine;
    }

    /**
     * set engine cache yg akan digunakan di repo ini
     *
     * @param string            $cacheEngine engine cache nya
     */
    public function setCacheEngineStatus($cacheActive)
    {
        $this->cacheActive = $cacheActive;
    }

    /**
     * save / update cache
     *
     * @param string            $prefix sub-prefix
     * @param string            $key key
     * @param string            $data
     * @param string            $expireDate
     * @param array             $fields
     */
    protected function _saveCache($prefix, $key, $data, $expireDate = false)
    {
        if ($this->skipCache) return null;
        if (!$this->cacheActive) return null; //$this->_saveCacheOnEngine($prefix,$key,$data,$expireDate);

        $fullPrefix = $this->cacheMainPrefix . '.' . $prefix;
        $fullPrefixKey = $fullPrefix . '.' . $key;

        if ($expireDate) {
            if (is_string($expireDate)) $expireDate = Carbon::parse($expireDate);
            Cache::store($this->cacheEngine)->put($fullPrefixKey, $data, $expireDate);
        } else {
            Cache::store($this->cacheEngine)->forever($fullPrefixKey, $data);
        }

        //load index per prefix nya
        if (isset($this->cacheIndexField[$prefix])) {
            $fields = $this->cacheIndexField[$prefix];
        } else {
            $fields = [];
        }

        //tambahkan index repo
        foreach ($fields as $field) {
            if (isset($data[$field])) {

                //simpan index
                if ($expireDate) {
                    Cache::store($this->cacheEngine)->put($fullPrefix . '.index.' . $field . '.' . $data[$field], $key, $expireDate);
                } else {
                    Cache::store($this->cacheEngine)->forever($fullPrefix . '.index.' . $field . '.' . $data[$field], $key);
                }
            }
        }
        return true;
        //        $fullPrefix = $this->cacheMainPrefix.'.'.$prefix;
        //
        //        //save cache
        //        $this->cacheData[$fullPrefix][$key] = $data;
        //
        //        //jika tidak menyertakan fields index maka isi dengan default field yang
        //        //disertakan di setiap repo
        //        if(isset($this->cacheIndexField[$prefix])){
        //            $fields = $this->cacheIndexField[$prefix];
        //        }else{
        //            $fields = [];
        //        }
        //
        //        //tambahkan index repo
        //        foreach ($fields as $field) {
        //            if(isset($data[$field]))$this->cacheIndex[$fullPrefix][$field][$data[$field]] = $key;//simpan index
        //        }
        //
        //        return true;
    }

    /**
     * @param String        $prefix
     * @param String        $key             id / prefix key per data nya
     * @param Mix           $defaultValue   default value saat cache belum ada
     * @param Boolean       $reload         True jika load data cache nya langsung ke cache engine
     *                                      False jika load data dari local var
     * @return mix
     */
    protected function _getCache($prefix, $key, $defaultValue = null, $reload = false)
    {
        if ($this->skipCache) return null;
        if (!$this->cacheActive) return null; //$this->_getCacheOnEngine($prefix,$key,$defaultValue);

        $fullPrefix = $this->cacheMainPrefix . '.' . $prefix;
        $fullPrefixKey = $fullPrefix . '.' . $key;

        //jika sudah diload sebelumnya maka ambil dari local var
        if ($reload == false && isset($this->cacheData[$fullPrefix]) && isset($this->cacheData[$fullPrefix][$key]))
            return $this->cacheData[$fullPrefix][$key];

        if (Cache::store($this->cacheEngine)->has($fullPrefixKey)) {
            $this->cacheData[$fullPrefix][$key] = Cache::store($this->cacheEngine)->get($fullPrefixKey);
            return $this->cacheData[$fullPrefix][$key];
        }

        return $defaultValue;
    }

    /**
     *
     * @param string        $prefix prefix cache nya
     * @param string        $field nama field index nya
     * @param string        $fieldValue value index nya
     * @param string        $defaultValue value index nya
     */
    protected function _getCacheByIndex($prefix, $field, $fieldValue, $defaultValue = null)
    {
        if ($this->skipCache) return null;
        if (!$this->cacheActive) return false; //$this->_getCacheByIndexOnEngine($prefix,$field,$fieldValue,$defaultValue);

        $fullPrefix = $this->cacheMainPrefix . '.' . $prefix;
        $indexPrefix = $fullPrefix . '.index.' . $field . '.' . $fieldValue;

        if (Cache::store($this->cacheEngine)->has($indexPrefix)) {
            $id = Cache::store($this->cacheEngine)->get($indexPrefix);
            return Cache::store($this->cacheEngine)->get($fullPrefix . '.' . $id);
        }
        return $defaultValue;

        //        $prefix = $this->cacheMainPrefix.'.'.$prefix;
        //
        //        if(isset($this->cacheIndex[$prefix][$field][$fieldValue])){
        //            $id = $this->cacheIndex[$prefix][$field][$fieldValue];
        //            if(isset($this->cacheData[$prefix][$id])){
        //                return $this->cacheData[$prefix][$id];
        //            }
        //        }
        //        return $defaultValue;
    }

    /**
     *
     * @param string        $prefix
     * @param string        $key
     * @return mix
     */
    protected function _hasCache($prefix, $key)
    {
        if ($this->skipCache) return false;
        if (!$this->cacheActive) return false; //$this->_hasCacheOnEngine($prefix,$key);

        $fullPrefix = $this->cacheMainPrefix . '.' . $prefix . '.' . $key;
        if (Cache::store($this->cacheEngine)->has($fullPrefix)) return true;
        return false;
        //        $prefix = $this->cacheMainPrefix.'.'.$prefix;
        //
        //        if(isset($this->cacheData[$prefix][$key]))
        //            return true;
        //        return false;
    }

    /**
     *
     * @param type $prefix
     * @param type $key
     * @return boolean
     */
    protected function _deleteCache($prefix, $key)
    {
        if ($this->skipCache) return false;
        if (!$this->cacheActive) return false;

        $fullPrefix = $this->cacheMainPrefix . '.' . $prefix;
        $fullPrefixKey = $fullPrefix . '.' . $key;

        Cache::store($this->cacheEngine)->forget($fullPrefixKey);
        return true;
    }
}
