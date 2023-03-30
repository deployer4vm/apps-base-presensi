<?php

namespace App\Base\Traits;

use App\Facades\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * use trait ini di model yang datanya ada pemisahan antar tenantnya
 */
trait ModelDataTenant
{
    protected $tenantId = 0;
    protected $_tableNameSetted = false;

    /**
     * Overide data model jika diperlukan
     *
     * 1 mode share dalam 1 table
     * 2 mode beda table
     * 3 mode beda database
     *
     * @return int Mode Data Model
     */
    public function getDataMode()
    {
        return config('AppConfig.system.multitenant.data_mode', 1);
    }

    /**
     * set tenant aktif model ini
     *
     * @param int $tenantId ID Tenant
     * @param boolean $isTenantId unused
     * @return void
     */
    public function setTenantId($tenantId, $isTenantId = true)
    {
        $this->tenantId = $tenantId;

        // $searchField = $isTenantId?'id':'group_app';
        // if($tenantId!=config('tenant.'.$searchField)){
        //     $tenantData = Tenant::getTenant([$searchField,$this->tenantId]);
        //     $config = app('config');
        //     $config->set('tenant',$tenantData);
        // }

        // $GLOBALS['model_tenant_id'] = app('tenant.id');
        // $this->tenantId = app('tenant.id');
    }

    /**
     * Get Tenant ID, set default from config if empty
     *
     * @return int Tenant ID
     */
    public function getTenantId()
    {
        if (empty($this->tenantId)) {
            $this->tenantId
                = $GLOBALS['model_tenant_id']
                = $GLOBALS['model_tenant_id'] ?? config('tenant.id');
        }

        return $this->tenantId;
    }

    /**
     * Set DB per Tenant, set default Tenant ID if empty
     *
     * @return void
     */
    public function setDbPerTenant()
    {
        $tenantId = $this->getTenantId();

        Tenant::setDb($tenantId);
    }

    /**
     * Get Tenant Connection Name
     *
     * @return string Connection Name
     */
    public function getConnectionName()
    {
        //get tenant id yang terset di model ini
        $tenantId = $this->getTenantId();

        if (config('AppConfig.system.multitenant.data_mode', 1) == 3) {
            $connectionName = Tenant::getDbConnectionName($tenantId);
            if ($this->connection != $connectionName) {
                $this->setDbPerTenant();
                $this->connection = $connectionName;
            }
        } else {
            $this->connection = config('database.perTenant');
        }

        return parent::getConnectionName();
    }

    /**
     * Set per Tenant Table Name
     *
     * @param string $table
     * @return self
     */
    public function setTable($table)
    {
        // jika sebelumnya nama table dengan prefix tenant telah diset,
        // maka tolak set nama table baru
        if ($this->_tableNameSetted && $this->table)
            $table = $this->table;

        $this->table = $table;

        return $this;
    }

    /**
     * Get per Tenant Table Name
     *
     * @return string Table Name
     */
    public function getTable()
    {
        $tenantId = $this->getTenantId();

        $table = $this->table;
        if (config('AppConfig.system.multitenant.data_mode', 1) == 2) {
            $table = config('AppConfig.system.multitenant.table_prefix', '_')
                . $tenantId . '_' . $table;
        }

        return $table;
    }
}
