<?php

namespace App\Services;

use App\Models\MConfig;
use App\Models\MConfigTenant;
use App\Base\BaseRepository;

class DbConfig extends BaseRepository
{
    protected $tenantId = 0; //defaultnya all tenant

    /**
     * set default tenant id yg digunakan jika tenant tidak/belum diload
     */
    public function setDefaultTenantId(int $tenantId = 0)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * get / set config
     *
     * @param string    $group          grup config
     * @param string    $key            key config
     * @param mixed       $default        default value yang diset jika key tidak ada, isi null jika
     *                                  tidak set default value
     * @param boolean   $saveDefault    default value yang diset jika key tidak ada
     * @param boolean   $castAsArray    true jika value diperlakukan sebagai array
     *
     * @return mixed|null                 null jika gagal, $default value yang diset jika berhasil
     */
    public function getConfig(
        string $group,
        string $key,
        $default = null,
        $saveDefault = true,
        $castAsArray = false
    ) {
        return $this->_getConfig(
            $group,
            $key,
            $default,
            $saveDefault,
            $this->tenantId?$this->tenantId:config('tenant.id', 0),
            $castAsArray
        );
    }

    /**
     * get / set config
     *
     * @param string    $group          grup config
     * @param string    $key            key config
     * @param mixed      $default        default value yang diset jika key tidak ada, isi null jika
     *                                  tidak set default value
     * @param boolean   $saveDefault    default value yang diset jika key tidak ada
     * @param boolean   $castAsArray    true jika value diperlakukan sebagai array
     *
     * @return mixed|null                 null jika gagal, $default value yang diset jika berhasil
     */
    public function getGlobalConfig(
        string $group,
        string $key,
        $default = null,
        $saveDefault = true,
        $castAsArray = false
    ) {
        return $this->_getConfig(
            $group, 
            $key, 
            $default, 
            $saveDefault, 
            0, 
            $castAsArray);
    }


    /**
     * main get / set config function
     *
     * @param string    $group          grup config
     * @param string    $key            key config
     * @param mix       $default        default value yang diset jika key tidak ada, isi null jika
     *                                  tidak set default value
     * @param boolean   $saveDefault    default value yang diset jika key tidak ada
     * @param integer   $tenantId       tenant id, 0 jika global (all tenant)
     * @param Boolean   $castAsArray    true jika value diperlakukan sebagai array
     *
     * @return string|null                 null jika gagal, $default value yang diset jika berhasil
     */
    private function _getConfig(
        string $group,
        string $key,
        $default = null,
        $saveDefault = true,
        $tenantId = 0,
        $castAsArray = false
    ) {
        $model = $tenantId?MConfigTenant::select('value'):MConfig::select('value');
        $data = $this->_getOne($model, [
            ['tenant_id', $tenantId],
            ['group', $group],
            ['key', $key]
        ]);

        if ($data) {
            if ($castAsArray) {
                $default = json_decode($data['value'], true);
            } else {
                $default = $data['value'];
            }
        } else {
            if (!is_null($default) && $saveDefault && $key) {
                if ($castAsArray) {
                    $this->_setConfig($group, $key, json_encode($default), $tenantId);
                } else {
                    if (is_array($default)) {
                        $default = $default['value'];
                    }
                    $this->_setConfig($group, $key, $default, $tenantId);
                }
            }
        }

        return $default;
    }

    /**
     * ambil list config per group
     *
     * @param string|array          $group      grup config
     * @param boolean               $returnValue        jika true maka return nya hanya field value saja, jika false maka full record
     *
     * @return array                array list config, dengan format [['key'=>record config]]
     */
    public function listConfig($group, $returnValue = false)
    {
        return $this->_listConfig(
            $group, 
            $this->tenantId?$this->tenantId:config('tenant.id', 0), 
            $returnValue
        );
    }

    /**
     * ambil list config global per group
     *
     * @param string|array          $group              grup config
     * @param boolean               $returnValue        jika true maka return nya hanya field value saja, jika false maka full record
     *
     * @return array                array list config, dengan format [['key'=>record config]]
     */
    public function listGlobalConfig($group, $returnValue = false)
    {
        return $this->_listConfig($group, 0, $returnValue);
    }

    /**
     * main list config per group function
     *
     * @param string|array          $group      grup config
     * @param integer               $tenantId       tenant id, 0 jika global (all tenant)
     * @param boolean               $returnValue        jika true maka return nya hanya field value saja, jika false maka full record
     *
     * @return array                array list config, dengan format [['key'=>record config]]
     */
    private function _listConfig($group, $tenantId = 0, $returnValue = false)
    {
        $model = $tenantId?MConfigTenant::select(['value', 'key']):MConfig::select(['value', 'key']);
        $list = $this->_list($model, [
            ['tenant_id', $tenantId],
            ['group', $group]
        ]);

        $data = [];

        if ($list['count']) {
            foreach ($list['data'] as $key => $value) {
                $data[$value['key']] = $returnValue ? $value['value'] : $value;
            }
        }

        return $data;
    }

    /**
     * save config
     *
     * @param string            $group      grup config
     * @param string            $key        key config
     * @param string|array      $value      value yang diset
     * @param int               $tenant_id  tenant id
     *
     * @return mixed|boolean                  false jika gagal, value yang diset jika berhasil
     */
    public function setConfig(string $group, string $key, $value, $tenant_id = false)
    {
        if ($tenant_id == false) {
            $tenant_id = config('tenant.id', $this->tenantId);
        }
        return $this->_setConfig($group, $key, $value, $tenant_id);
        // return $this->_setConfig($group,$key, $value,config('tenant.id',$this->tenantId));
    }


    /**
     * save config di all tenant
     *
     * @param string            $group      grup config
     * @param string            $key        key config
     * @param string|array      $value      value yang diset
     *
     * @return mixed|boolean                  false jika gagal, value yang diset jika berhasil
     */
    public function setGlobalConfig(string $group, string $key, $value)
    {
        return $this->_setConfig($group, $key, $value, 0);
    }

    /**
     * main save config function
     *
     * @param string            $group      grup config
     * @param string            $key        key config
     * @param string|array             $value      value yang diset
     * @param integer           $tenantId   tenant id, 0 jika all tenant
     *
     * @return mixed|boolean                  false jika gagal, value yang diset jika berhasil
     */
    private function _setConfig(string $group, string $key, $value, $tenantId = 0)
    {
        $model = $tenantId?new MConfigTenant:new MConfig;
        
        //jika config sudah ada maka update data nya
        if ($this->_exists($model, [
            ['tenant_id', $tenantId],
            ['group', $group],
            ['key', $key]
        ])) {
            // $model = $tenantId?new MConfigTenant:new MConfig;
            return $this->_update($model, [
                ['tenant_id', $tenantId],
                ['group', $group],
                ['key', $key],
            ], [
                'value' => $value['value'] ?? $value
            ]);
            //jika belum ada maka create
        } else {
            // $model = $tenantId?new MConfigTenant:new MConfig;
            return $this->_create($model, [
                'tenant_id' => $tenantId,
                'group' => $group,
                'key' => $key,
                'name' => $value['name'] ?? '',
                'value' => $value['value'] ?? $value
            ]);
        }
    }

    /**
     * delete config
     *
     * @param string            $group      grup config
     * @param string            $key        key config
     */
    public function deleteConfig(string $group, string $key)
    {
        return $this->_delete(new Mconfig, [
            ['tenant_id', $this->tenantId?$this->tenantId:config('tenant.id', 0)],
            ['group', $group],
            ['key', $key],
        ]);
    }

    /**
     * delete config global di all tenant
     *
     * @param string            $group      grup config
     * @param string            $key        key config
     */
    public function deleteGlobalConfig(string $group, string $key)
    {
        return $this->_delete(new MConfigTenant, [
            ['tenant_id', 0],
            ['group', $group],
            ['key', $key],
        ]);
    }
}
