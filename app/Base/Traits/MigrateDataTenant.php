<?php

namespace App\Base\Traits;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

use App\Facades\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * use trait ini di migration yang datanya ada pemisahan antar tenantnya
 */
trait MigrateDataTenant
{
    /**
     * Mode migrasi dijalan dari mana :
     *      true = jika migrasi dijalan dari Tenant service
     *      false = jika migrasi dijalan kan dari fitur artisan migrate
     *
     * @var boolean
     */
    public $_tenantMigrateMode = false;

    public function setTenantId($tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Set mode migrasi tenant
     */
    /**
     * Set mode migrasi tenant
     *
     * @param boolean true = jika migrasi dijalan dari Tenant service,
     *                     false = jika migrasi dijalan kan dari fitur artisan migrate
     * @return void
     */
    public function setTenantMigrateMode($tenantMigrateMode)
    {
        $this->_tenantMigrateMode = $tenantMigrateMode;
    }

    /**
     * apakah mode migrasi yang dijalan adalah dari Tenant class service,
     * jika ya berarti ini adalah untuk generate migration per tenant saja
     *
     * @return Boolean
     *      true jika migrasi dijalan dari Tenant service
     *      false jika migrasi dijalan kan dari fitur artisan migrate
     */
    public function tenantMigrateMode()
    {
        return $this->_tenantMigrateMode;
    }

    public function createPerTenant($table, $bluePrint)
    {
        //jika mode nya tidak share dalam 1 table
        if (
            config('AppConfig.system.multitenant.active', false)
            && config('AppConfig.system.multitenant.data_mode', 1) != 1
        ) {
            $filter = isset($this->tenantId) ? [['id', $this->tenantId]] : [];
            $tenantList = Tenant::listTenant($filter);
            foreach ($tenantList['data'] as $tenant) {
                //jika per database
                if (config('AppConfig.system.multitenant.data_mode') == 3) {
                    Tenant::setDb($tenant['id']);
                    if (
                        Tenant::dbExists($tenant['id'])
                        && !Schema::connection(config('database.perTenant') . $tenant['id'])
                            ->hasTable($table)
                    ) {
                        Schema::connection(config('database.perTenant') . $tenant['id'])
                            ->create($table, $bluePrint);
                    }
                    // jika per table
                } else {
                    $tmpTable = Tenant::getTableName($table, $tenant['id']);
                    if (!Schema::hasTable($tmpTable)) {
                        Schema::create($tmpTable, $bluePrint);
                    }
                }
            }
            // jika di 1 table
        } else {
            if (!Schema::hasTable($table))
                Schema::create($table, $bluePrint);
        }
    }

    /**
     * @param Boolean $ifColumnExist
     *      jika TRUE maka si blueprint akan dieksekusi jika kolom $column ada
     *      jika FALSE maka si blueprint akan dieksekusi jika kolom $column tidak ada
     */
    public function tablePerTenant($table, $bluePrint, $column = false, $ifColumnExist = false)
    {
        //jika mode nya tidak share dalam 1 table
        if (
            config('AppConfig.system.multitenant.active', false)
            && config('AppConfig.system.multitenant.data_mode', 1) != 1
        ) {
            $filter = isset($this->tenantId) ? [['id', $this->tenantId]] : [];
            $tenantList = Tenant::listTenant($filter,0,0,['id','ASC']);
            foreach ($tenantList['data'] as $tenant) {
                try{
                    //jika per database
                    if (config('AppConfig.system.multitenant.data_mode', 1) == 3) {
                        Tenant::setDb($tenant['id']);
                        if (
                            Tenant::dbExists($tenant['id'])
                            && Schema::connection(config('database.perTenant') . $tenant['id'])
                            ->hasTable($table)
                        ) {
                            if (
                                $column == false
                                || (!$ifColumnExist
                                    && !Schema::connection(config('database.perTenant') . $tenant['id'])
                                        ->hasColumn($table, $column)
                                )
                                || ($ifColumnExist
                                    && Schema::connection(config('database.perTenant') . $tenant['id'])
                                    ->hasColumn($table, $column)
                                )
                            ) {
                                Schema::connection(config('database.perTenant') . $tenant['id'])
                                    ->table($table, $bluePrint);
                            }
                        }
                        // jika per table
                    } else {
                        $tmpTable = Tenant::getTableName($table, $tenant['id']);
                        if (Schema::hasTable($tmpTable)) {
                            if (
                                $column == false
                                || (!$ifColumnExist && !Schema::hasColumn($tmpTable, $column))
                                || ($ifColumnExist && Schema::hasColumn($tmpTable, $column))
                            ) {
                                Schema::table($tmpTable, $bluePrint);
                            }
                        }
                    }                       
                    echo "\nTenant : ".$tenant['id']." Migrated ! \n";                 
                }catch(\Exception $e){
                    echo "\nTenant : ".$tenant['id']." ERROR ! \n";
                    throw $e;
                }
            }
            // jika di 1 table
        } else {
            if (Schema::hasTable($table)) {
                if (
                    $column == false ||
                    (!$ifColumnExist && !Schema::hasColumn($table, $column)) ||
                    ($ifColumnExist && Schema::hasColumn($table, $column))
                )
                    Schema::table($table, $bluePrint);
            }
        }
    }

    public function tableIndexPerTenant($table, $bluePrint, $column, $ifIndexExist = false, $unique = false)
    {
        $columnName = is_array($column) ? implode('_', $column) : $column;
        $indexName = $table . '_' . $columnName . '_' . ($unique ? 'unique' : 'index');

        //jika mode nya tidak share dalam 1 table
        if (
            config('AppConfig.system.multitenant.active', false)
            && config('AppConfig.system.multitenant.data_mode', 1) != 1
        ) {
            $filter = isset($this->tenantId) ? [['id', $this->tenantId]] : [];
            $tenantList = Tenant::listTenant($filter);
            // if ($unique) Log::debug($indexName);
            foreach ($tenantList['data'] as $tenant) {
                try{
                    //jika per database
                    if (config('AppConfig.system.multitenant.data_mode', 1) == 3) {
                        Tenant::setDb($tenant['id']);
                        if (
                            Tenant::dbExists($tenant['id'])
                            && Schema::connection(config('database.perTenant') . $tenant['id'])
                            ->hasTable($table)
                        ) {
                            $schemaManager = Schema::connection(config('database.perTenant') . $tenant['id'])->getConnection()
                                ->getDoctrineSchemaManager();
                            $indexesFound  = $schemaManager->listTableIndexes($table);
                            // if ($unique) {
                            //     Log::debug($indexesFound);
                            //     Log::debug((int) array_key_exists($indexName, $indexesFound));
                            // }
                            if (
                                ($ifIndexExist === false
                                    && array_key_exists($indexName, $indexesFound) === false
                                )
                                || ($ifIndexExist
                                    && array_key_exists($indexName, $indexesFound)
                                )
                            ) {
                                Schema::connection(config('database.perTenant') . $tenant['id'])
                                    ->table($table, $bluePrint);
                            }
                        }
                        // jika per table
                    } else {
                        $tmpTable = Tenant::getTableName($table, $tenant['id']);
                        $schemaManager = Schema::getConnection()->getDoctrineSchemaManager();
                        $indexesFound  = $schemaManager->listTableIndexes($tmpTable);
                        if (Schema::hasTable($tmpTable)) {
                            if (
                                (!$ifIndexExist && !array_key_exists($indexName, $indexesFound))
                                || ($ifIndexExist && array_key_exists($indexName, $indexesFound))
                            ) {
                                Schema::table($tmpTable, $bluePrint);
                            }
                        }
                    }
                    
                }catch(\Exception $e){
                    echo 'Error tenant : '.$tenant['id'];
                    throw $e;
                }
            }
            // jika di 1 table
        } else {
            if (Schema::hasTable($table)) {
                $schemaManager = Schema::getConnection()->getDoctrineSchemaManager();
                $indexesFound  = $schemaManager->listTableIndexes($table);
                if (
                    $column == false ||
                    (!$ifIndexExist && !array_key_exists($indexName, $indexesFound)) ||
                    ($ifIndexExist && array_key_exists($indexName, $indexesFound))
                )
                    Schema::table($table, $bluePrint);
            }
        }
    }

    public function tableRenameColumnPerTenant($table, $bluePrint, $oldColumn, $newColumn)
    {
        //jika mode nya tidak share dalam 1 table
        if (
            config('AppConfig.system.multitenant.active', false)
            && config('AppConfig.system.multitenant.data_mode', 1) != 1
        ) {
            $filter = isset($this->tenantId) ? [['id', $this->tenantId]] : [];
            $tenantList = Tenant::listTenant($filter);
            foreach ($tenantList['data'] as $tenant) {
                //jika per database
                if (config('AppConfig.system.multitenant.data_mode', 1) == 3) {
                    Tenant::setDb($tenant['id']);
                    if (
                        Tenant::dbExists($tenant['id'])
                        && Schema::connection(config('database.perTenant') . $tenant['id'])
                        ->hasTable($table)
                    ) {
                        if (
                            Schema::connection(config('database.perTenant') . $tenant['id'])
                            ->hasColumn($table, $oldColumn)
                            && !Schema::connection(config('database.perTenant') . $tenant['id'])
                                ->hasColumn($table, $newColumn)
                        ) {
                            Schema::connection(config('database.perTenant') . $tenant['id'])
                                ->table($table, $bluePrint);
                        }
                    }
                    // jika per table
                } else {
                    $tmpTable = Tenant::getTableName($table, $tenant['id']);
                    if (Schema::hasTable($tmpTable)) {
                        if (
                            !Schema::hasColumn($tmpTable, $newColumn)
                            && Schema::hasColumn($tmpTable, $oldColumn)
                        ) {
                            Schema::table($tmpTable, $bluePrint);
                        }
                    }
                }
            }
            // jika di 1 table
        } else {
            if (Schema::hasTable($table)) {
                if (
                    !Schema::hasColumn($table, $newColumn)
                    && Schema::hasColumn($table, $oldColumn)
                )
                    Schema::table($table, $bluePrint);
            }
        }
    }

    /**
     * rename nama table di mode multi tenanat
     */
    public function renameTablePerTenant($oldTable, $newTable)
    {
        //jika mode nya tidak share dalam 1 table
        if (
            config('AppConfig.system.multitenant.active', false)
            && config('AppConfig.system.multitenant.data_mode', 1) != 1
        ) {
            $filter = isset($this->tenantId) ? [['id', $this->tenantId]] : [];
            $tenantList = Tenant::listTenant($filter);
            foreach ($tenantList['data'] as $tenant) {

                //jika per database
                if (config('AppConfig.system.multitenant.data_mode', 1) == 3) {

                    Tenant::setDb($tenant['id']);
                    if (
                        Tenant::dbExists($tenant['id'])
                        && Schema::connection(config('database.perTenant') . $tenant['id'])
                        ->hasTable($oldTable)
                        && !Schema::connection(config('database.perTenant') . $tenant['id'])
                            ->hasTable($newTable)
                    ) {
                        Schema::connection(config('database.perTenant') . $tenant['id'])
                            ->rename($oldTable, $newTable);
                    }
                } else {
                    // jika per table
                    $tmpOldTable = Tenant::getTableName($oldTable, $tenant['id']);
                    $tmpNewTable = Tenant::getTableName($newTable, $tenant['id']);
                    if (Schema::hasTable($tmpOldTable) && !Schema::hasTable($tmpNewTable))
                        Schema::rename($tmpOldTable, $tmpNewTable);
                }
            }

            // jika di 1 table
        } else {
            if (Schema::hasTable($oldTable) && !Schema::hasTable($newTable))
                Schema::rename($oldTable, $newTable);
        }
    }


    /**
     * eksekusi statement
     *
     * @param string            $statement
     * @param string|false      $table          isi false jika tidak detek table ada atau tidak,
     *                                          isi dengan nama table jika mendetek table ada ataut tidak
     * @param boolean           $ifTableExist
     */
    public function statementPerTenant($statement, $table = false, $ifTableExist = true)
    {
        //jika mode nya tidak share dalam 1 table
        if (
            config('AppConfig.system.multitenant.active', false)
            && config('AppConfig.system.multitenant.data_mode', 1) != 1
        ) {
            $filter = isset($this->tenantId) ? [['id', $this->tenantId]] : [];
            $tenantList = Tenant::listTenant($filter);
            foreach ($tenantList['data'] as $tenant) {
                //jika per database
                if (config('AppConfig.system.multitenant.data_mode', 1) == 3) {
                    Tenant::setDb($tenant['id']);
                    if (
                        Tenant::dbExists($tenant['id'])
                        && ($table == false
                            || Schema::connection(config('database.perTenant') . $tenant['id'])
                            ->hasTable($table) == $ifTableExist
                        )
                    ) {
                        DB::connection(config('database.perTenant') . $tenant['id'])
                            ->statement($statement);
                    }

                    // jika per table pake prefix nama table
                } else {
                    $tmpTable = $table ? Tenant::getTableName($table, $tenant['id']) : false;
                    if ($tmpTable == false || Schema::hasTable($tmpTable) == $ifTableExist) {
                        DB::statement($statement);
                    }
                }
            }
            // jika di 1 table
        } else {
            if ($table == false || Schema::hasTable($table) == $ifTableExist) {
                DB::statement($statement);
            }
        }
    }

    /**
     * truncate table
     *
     * @param string|false      $table          isi false jika tidak detek table ada atau tidak,
     *                                          isi dengan nama table jika mendetek table ada ataut tidak
     * @param boolean           $ifTableExist
     */
    public function truncatePerTenant($table = false, $ifTableExist = true)
    {
        //jika mode nya tidak share dalam 1 table
        if (
            config('AppConfig.system.multitenant.active', false)
            && config('AppConfig.system.multitenant.data_mode', 1) != 1
        ) {
            $filter = isset($this->tenantId) ? [['id', $this->tenantId]] : [];
            $tenantList = Tenant::listTenant($filter);
            foreach ($tenantList['data'] as $tenant) {
                //jika per database
                if (config('AppConfig.system.multitenant.data_mode', 1) == 3) {
                    Tenant::setDb($tenant['id']);
                    if (
                        Tenant::dbExists($tenant['id'])
                        && ($table == false
                            || Schema::connection(config('database.perTenant') . $tenant['id'])
                            ->hasTable($table) == $ifTableExist
                        )
                    ) {
                        DB::connection(config('database.perTenant') . $tenant['id'])
                            ->table($table)
                            ->truncate();
                    }

                    // jika per table pake prefix nama table
                } else {
                    $tmpTable = $table ? Tenant::getTableName($table, $tenant['id']) : false;
                    if ($tmpTable == false || Schema::hasTable($tmpTable) == $ifTableExist) {
                        DB::table($tmpTable)->truncate();
                    }
                }
            }
            // jika di 1 table
        } else {
            if ($table == false || Schema::hasTable($table) == $ifTableExist) {
                DB::table($table)->truncate();
            }
        }
    }

    /**
     */
    /**
     * delete table per tenant
     *
     * @param string $table
     * @param boolean $ifTableExist check jika table ada atau tidak
     * @return void
     */
    public function dropTablePerTenant($table, $ifTableExist = true)
    {
        //jika mode nya tidak share dalam 1 table
        if (
            config('AppConfig.system.multitenant.active', false)
            && config('AppConfig.system.multitenant.data_mode', 1) != 1
        ) {
            $filter = isset($this->tenantId) ? [['id', $this->tenantId]] : [];
            $tenantList = Tenant::listTenant($filter);
            foreach ($tenantList['data'] as $tenant) {
                //jika per database
                if (config('AppConfig.system.multitenant.data_mode', 1) == 3) {
                    Tenant::setDb($tenant['id']);
                    if (Tenant::dbExists($tenant['id'])) {
                        if ($ifTableExist) {
                            Schema::connection(config('database.perTenant') . $tenant['id'])
                                ->dropIfExists($table);
                        } else {
                            Schema::connection(config('database.perTenant') . $tenant['id'])
                                ->drop($table);
                        }
                    }

                    // jika per table pake prefix nama table
                } else {
                    $tmpTable = Tenant::getTableName($table, $tenant['id']);

                    if ($ifTableExist) {
                        Schema::dropIfExists($tmpTable);
                    } else {
                        Schema::drop($tmpTable);
                    }
                }
            }
            // jika di 1 table
        } else {
            if ($ifTableExist) {
                Schema::dropIfExists($table);
            } else {
                Schema::drop($table);
            }
        }
    }
}
