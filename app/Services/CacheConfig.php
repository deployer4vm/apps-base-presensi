<?php

namespace App\Services;

use App\Base\BaseRepository;

class CacheConfig extends BaseRepository
{
    protected $cacheActive = true;
    protected $skipCache = false;
    private $groupKey = 'generalconfig';

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
}
