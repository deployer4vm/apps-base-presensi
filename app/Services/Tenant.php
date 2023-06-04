<?php

namespace App\Services;

use Exception;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Base\BaseRepository;

use App\Models\Tenant as MTenant;
use App\Models\TenantDomain;
use App\Models\TenantGroup;
use App\Models\TenantGroupTenant;
use Closure;
use config;
use PDO;
use Symfony\Component\Routing\Exception\InvalidParameterException;

class Tenant extends BaseRepository
{

    protected $autoResource = [
        'Tenant' => [
            'r' => MTenant::class,
            'w' => MTenant::class
        ],
        'TenantDomain' => [
            'r' => TenantDomain::class,
            'w' => TenantDomain::class
        ],
        'TenantGroup' => [
            'r' => TenantGroup::class,
            'w' => TenantGroup::class
        ],
        'TenantGroupTenant' => [
            'r' => TenantGroupTenant::class,
            'w' => TenantGroupTenant::class
        ],
    ];

    protected $autoResourceSearchField = [
        'Tenant' => ['group_app', 'domain', 'name', 'note'],
        'TenantDomain' => ['domain', 'redirect'],
        'TenantGroup' => ['name'],
    ];

    private $_tmpTenantList = [];
    private $_tmpTenantListByGroupApp = [];
    private $_tmpTenantListByDomain = [];

    // public function listTenant(array $filter = [], int $offset = 0, int $limit = 0, array $orderBy = [])
    // {
    //     return $this->_autoResourceList('listTenant',[$filter,$offset,$limit,$orderBy]);
    // }

    /**
     * Check if the given data is the same as current tenant
     *
     * @param string $project
     * @param integer $tenantId
     * @return boolean
     */
    public function isCurrentTenant($project, $tenantId)
    {
        $project_code = config('AppConfig.client.project_code');
        if ($project_code != $project) {
            return false;
        }

        if (
            (is_array($tenantId) && !in_array(config('tenant.instance_data.id'), $tenantId))
            || config('tenant.instance_data.id') != $tenantId
        ) {
            return false;
        }

        // if (is_array($tenantId)) {
        //     if (!in_array(config('tenant.instance_data.id'), $tenantId)) {
        //         return false;
        //     }
        // } else {
        //     if (config('tenant.instance_data.id') != $tenantId) {
        //         return false;
        //     }
        // }

        return true;
    }

    /**
     * Get Tenant Data by ID
     *
     * @param integer $tenantId
     * @return false|array false = Tenant data not found
     */
    public function getTenantById($tenantId)
    {
        if (!isset($this->_tmpTenantList[$tenantId])) {
            $tenant = $this->getTenantModel()->find($tenantId);
            if (!$tenant) {
                $this->error = 'Tenant not found';
                return false;
            }

            $this->_tmpTenantList[$tenantId]
                = $this->_tmpTenantListByGroupApp[$tenant->group_app]
                = $this->_tmpTenantListByDomain[$tenant->domain]
                = $tenant->toArray();
        }

        return $this->_tmpTenantList[$tenantId];
    }

    /**
     * Get Tenant Data by Group App
     *
     * @param string $groupApp
     * @return false|array false = Tenant data not found
     */
    public function getTenantByGroupApp($groupApp)
    {
        if (!isset($this->_tmpTenantListByGroupApp[$groupApp])) {
            $tenant = $this->getTenantModel()->where('group_app', $groupApp)->first();
            if (!$tenant) {
                $this->error = 'Tenant not found';
                return false;
            }

            $this->_tmpTenantListByGroupApp[$groupApp]
                = $this->_tmpTenantList[$tenant->id]
                = $this->_tmpTenantListByDomain[$tenant->domain]
                = $tenant->toArray();
        }

        return $this->_tmpTenantListByGroupApp[$groupApp];
    }

    /**
     * Get Tenant Data by Domain
     *
     * @param string $domain
     * @return false|array false = data not found
     */
    public function getTenantByDomain($domain = false)
    {
        $domain = $domain ?: request()->getHttpHost();

        if (!isset($this->_tmpTenantListByDomain[$domain])) {
            $domainData = TenantDomain::where('domain', $domain)
                ->where('status', '!=', 0)
                ->first();
            if (!$domainData) {
                $this->error = 'Tenant Domain not found';
                return false;
            }

            $tenant = $this->getTenantModel()
                ->where('id', $domainData['tenant_id'])
                ->first();
            if (!$tenant) {
                $this->error = 'Tenant not found';
                return false;
            }

            //$this->_tmpTenantListByDomain[$domain] = $tenant->toArray();

            $this->_tmpTenantListByDomain[$domain]
                = $this->_tmpTenantListByGroupApp[$tenant->group_app]
                = $this->_tmpTenantList[$tenant->id]
                = $tenant->toArray();
            $this->_tmpTenantListByDomain[$domain]['domain'] = $domainData->toArray();

            if ($this->_tmpTenantListByDomain[$domain]) {
                $this->_tmpTenantListByDomain[$domain]['domain'] = $domainData->toArray();
            } else {
                return false;
            }

            $this->_tmpTenantListByGroupApp[$this->_tmpTenantListByDomain[$domain]['group_app']] = $this->_tmpTenantListByDomain[$domain];
            $this->_tmpTenantList[$this->_tmpTenantListByDomain[$domain]['id']] = $this->_tmpTenantListByDomain[$domain];
        }

        return $this->_tmpTenantListByDomain[$domain];
    }

    /**
     * START - GROUP MANAGE ACTIAVE TENANT
     */

    /**
     * Set Active Tenant by ID
     *
     * @param integer $tenantId
     * @return void
     */
    public function setActiveTenantById($tenantId)
    {
        $tenant = $this->getTenantById($tenantId);
        if ($tenant)
            $this->setActiveTenant($tenant);
    }

    /**
     * Set Active Tenant by Group
     *
     * @param string $appGroup
     * @return void
     */
    public function setActiveTenantByGroup($appGroup = false)
    {
        $appGroup = $appGroup
            ?: request()->header('Group-App', false)
            ?: request()->route('group_app', false)
            ?: request()->input('group_app', false);
        // if (!$appGroup) {
        //     if (!($appGroup = request()->header('Group-App', false))) {
        //         if (!($appGroup = request()->route('group_app', false))) {
        //             $appGroup = request()->input('group_app', false);
        //         }
        //     }
        // }

        // jika mengakses aplikasi tenant
        if (
            (!$appGroup && config('AppConfig.system.multitenant.owner_subfolder', '') == '')
            || ($appGroup && $appGroup == config('AppConfig.system.multitenant.owner_subfolder'))
        ) {
            $this->setOnTenantManager();
        } else {
            $tenant = $this->getTenantByGroupApp($appGroup);
            if ($tenant)
                $this->setActiveTenant($tenant->toArray());
        }
    }

    /**
     * Set active tenant by domain
     * fungsi ini dieksekusi di RouteServiceProvider utama jika multi tentant nya didetect via domain
     *
     * @param string $domain
     * @return void
     */
    public function setActiveTenantByDomain($domain = false)
    {
        $domain = $domain ?: request()->getHttpHost();

        // jika mengakses domain owner maka tandai sebagai koneksi domain owner
        if ($domain == config('AppConfig.system.multitenant.owner_domain')) {
            $this->setOnTenantManager();

            // jika mengakses domain storage all tenant maka tandai sebagai koneksi domain storage
        } else if ($domain == config('AppConfig.system.multitenant.alltenant_storage_domain')) {
            $this->setOnStorageAlltenant();

            // jika mengakses domain general API maka tandai sebagai koneksi domain api
        } else if ($domain == config('AppConfig.system.multitenant.general_api_domain')) {
            $this->setOnGeneralApi();
        } else {
            $tenant = $this->getTenantByDomain($domain); //$this->getTenantModel()->where('domain',$domain)->first();
            if ($tenant)
                $this->setActiveTenant($tenant);
        }
    }

    /**
     * Set Active Tenant by Data Array
     *
     * @param array $dataTenant
     * @return void
     * @throws Exception
     */
    public function setActiveTenant(array $dataTenant)
    {
        if (!isset($dataTenant['id'])) {
            throw new Exception('Invalid Tenant array');
        }

        $config = app('config');
        $config->set('tenant', $dataTenant);

        // set packageLocal pertenant
        if (config('AppConfig.packageLocalPerTenant.' . $dataTenant['id']))
            $config->set(
                'AppConfig.packageLocal',
                config('AppConfig.packageLocalPerTenant.' . $dataTenant['id'])
            );

        resolve('bindTenant', ['tenant_id' => $dataTenant['id']]);

        $this->setDb($dataTenant['id']);
    }

    /**
     * Get Actuve Tenant Config
     *
     * @param string $field
     * @return mixed
     */
    public function getActiveTenant(string $field = '')
    {
        return config($field ? ('tenant.' . $field) : 'tenant');
    }

    /**
     * set aplikasi yang sedang aktif adalah tenant management (owner) bukan aplikasi per tenantnya
     *
     * @return void
     */
    public function setOnTenantManager()
    {
        $config->setTenantConfig(['isOnTenantManager' => true]);
    }


    /**
     * set aplikasi yang sedang aktif adalah domain cdn/storage per tenant
     *
     * @return void
     */
    public function setOnStorageAlltenant()
    {
        $config->setTenantConfig(['isOnStorageAlltenant' => true]);
    }

    /**
     * set aplikasi yang sedang aktif adalah domain api general
     *
     * @return void
     */
    public function setOnGeneralApi()
    {
        $config->setTenantConfig(['isOnGeneralApi' => true]);
    }

    /**
     * Set Tenant Config
     *
     * @param mixed $value
     * @return void
     */
    private function setTenantConfig($value)
    {
        $config = app('config');
        $config->set('tenant', $value);
    }

    /**
     * apakah yang aktif sekarang adalah aplikasi tenant managementnya ?
     *
     * @return boolean true jika yang aktif adalah aplikasi tenant management, false jika bukan
     */
    public function isOnTenantManager()
    {
        return config('tenant.isOnTenantManager', false);
    }

    /**
     * apakah yang aktif sekarang adalah storage all tenant
     *
     * @return boolean true jika yang aktif adalah storage all tenant, false jika bukan
     */
    public function isOnStorageAlltenant()
    {
        return config('tenant.isOnStorageAlltenant', false);
    }

    /**
     * apakah yang aktif sekarang adalah domain api general ?
     *
     * @return boolean true jika yang diakses adalah domain api general, false jika bukan
     */
    public function isOnGeneralApi()
    {
        return config('tenant.isOnGeneralApi', false);
    }

    /**
     * END - GROUP MANAGE ACTIVE TENANT
     */

    /**
     * START - GROUP MANAGE PEMISAHAN DATABASE ATAU TABLE PER TENANT
     */

    /**
     * Get Tenant Default Model
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function getTenantModel()
    {
        if (!config('AppConfig.system.multitenant.table_instance', false)) {
            return new MTenant;
        }

        return MTenant::with(['instanceData']);
    }

    /**
     * Get nama koneksi database per tenant
     *
     * @param integer $tenantId
     * @return string
     */
    public function getDbConnectionName($tenantId)
    {
        if (config('AppConfig.system.multitenant.data_mode', 1) != 3)
            return config('database.default');

        return config('database.perTenant') . $tenantId;
    }

    /**
     * generate and get connection database pertenant
     *
     * @param integer $tenantId
     * @return string
     */
    public function getDbConnection($tenantId)
    {
        if (config('AppConfig.system.multitenant.data_mode', 1) != 3)
            return config('database.connections.' . config('database.default'));

        $dbConfigName = $this->getDbConnectionName($tenantId);

        // jika multidatabase server aktif maka detek dan sinkronkan konfig db nya
        if (config('database.multi_database_server.enable', false)) {
            if (!($dbConfig = $this->getDbConnection_getServer($tenantId))) {
                return false;
            }
        } else {
            $dbConfig = config('database.connections.' . config('database.perTenant'));
        }

        $dbConfig['database'] = $dbConfig['database_prefix'] . $tenantId;
        $dbConfig['name'] = $dbConfig['name'] . ' ' . $tenantId;

        config(['database.connections.' . $dbConfigName => $dbConfig]);

        return $dbConfig;
    }

    /**
     * Get Server Data for Specific Tenant Database Connection
     *
     * @param integer $tenantId
     * @return mixed
     */
    private function getDbConnection_getServer($tenantId)
    {
        $server = config('database.multi_database_server.servers.' . config('tenant.db'));
        if (config('tenant.id') != $tenantId) {
            if (!($tenant = $this->getTenantById($tenantId)))
                return false;
            $server = config('database.multi_database_server.servers.' . $tenant['db']);
        }

        return $server;
    }

    /**
     * Get Tenant Database Size
     *
     * @param integer $tenantId
     * @return float
     */
    public function getDbSize($tenantId = 0)
    {
        if ($this->dbExists($tenantId)) {
            $result = $this->db($tenantId)
                ->select(DB::raw('SELECT table_name AS "Table",
                    ((data_length + index_length) / 1024 / 1024) AS "Size"
                    FROM information_schema.TABLES
                    WHERE table_schema = "' . $this->getDbConnection($tenantId)['database'] . '"
                    ORDER BY (data_length + index_length) DESC'));
            $size = array_sum(array_column($result, 'Size'));
            return round((float) $size, 2);
        }
        return 0;
    }

    /**
     * Get Tenant PDO Instance
     *
     * @param integer $tenantId
     *
     * @return PDO|Closure|null
     */
    public function getDbRawPDO($tenantId = false)
    {
        return $this->db($tenantId)->getRawPdo();
    }

    /**
     * Get nama database untuk database pertenant
     *
     * @param integer $tenantId
     * @return mixed
     */
    public function getDbName($tenantId)
    {
        if (!($dbConfig = $this->getDbConnection($tenantId)))
            return false;

        return $dbConfig['database'];

        // if(config('AppConfig.system.multitenant.data_mode',1) != 3)
        //     return config('database.connections.'.config('database.default').'.database');

        // return config('database.connections.'.config('database.perTenant').'.database_prefix').$tenantId;
    }

    /**
     * cek apakah database pertenant sudah ada
     *
     * @param integer $tenantId
     * @return boolean
     */
    public function dbExists($tenantId)
    {
        // $schemaName = config("database.connections.".config("database.perTenant").".database_prefix").$tenantId;
        $schemaName = $this->getDbName($tenantId);
        $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME =  ?";
        $db = DB::select($query, [$schemaName]);

        //jika empty berarti database belum ada
        return !empty($db);
    }

    /**
     * Check if Table exists in Tenant Database
     *
     * @param string $tableName
     * @param integer $tenantId
     * @return boolean
     */
    public function tableExists($tableName, $tenantId = false)
    {
        if (!$tenantId) $tenantId = config('tenant.id');
        $this->getDbConnection($tenantId); // generate dulu confignya
        return Schema::connection($this->getDbConnectionName($tenantId))
            ->hasTable($tableName);
    }

    /**
     * Check if Column exists in a table in Tenant Database
     *
     * @param string $tableName
     * @param string $columnName
     * @param integer $tenantId
     * @return boolean
     */
    public function tableColumnExists($tableName, $columnName, $tenantId = false)
    {
        if (!$tenantId) $tenantId = config('tenant.id');
        $this->getDbConnection($tenantId); // generate dulu confignya
        return Schema::connection($this->getDbConnectionName($tenantId))
            ->hasColumn($tableName, $columnName);
    }

    /**
     * set connection active database per tenant session saat ini
     *
     * @param integer $tenantId
     */
    public function setDb($tenantId)
    {
        if (config('AppConfig.system.multitenant.data_mode', 1) != 3)
            return true;

        $dbConfigName = $this->getDbConnectionName($tenantId);
        config(['tenant.connection', $dbConfigName]);

        // tambah connection database on thy fly sesuai tenant yang aktifnya (jika belum ditambah)
        if (config('database.connections.' . $dbConfigName, false) == false) {
            $dbConfig = $this->getDbConnection($tenantId);
            config(['database.connections.' . $dbConfigName => $dbConfig]);
        }
        return true;
    }

    /**
     * memigrasikan seluruh migrasi per tenant di 1 tenant baru
     *
     * @param integer $tenantId
     * @return boolean
     */
    public function migrate($tenantId)
    {
        //jika database belum ada maka tolak
        if (!$this->dbExists($tenantId)) {
            return false;
        }

        $migrations = config('hpsynapse.migration_path');
        foreach ($migrations as $migrationpath) {
            $migrationFileList = glob($migrationpath . DIRECTORY_SEPARATOR . '*.php');
            foreach ($migrationFileList as $migration) {
                include_once $migration;
                $migrationClass = ucfirst(Str::camel(substr(
                    str_replace('.php', '', basename($migration)),
                    18
                )));
                $tmpClass = new $migrationClass;
                if (method_exists($tmpClass, 'tenantMigrateMode')) {
                    if (
                        !property_exists($tmpClass, 'tenantId')
                        || $tmpClass->tenantId == $tenantId
                    ) {
                        $tmpClass->setTenantMigrateMode(true);
                        $tmpClass->setTenantId($tenantId);
                        $tmpClass->up();
                        echo 'migrated -> ' . $migration . '<br>';
                    } else {
                        echo 'not migrated -> ' . $migration . '<br>';
                    }
                }
            }
        }

        return true;
    }

    /**
     * men-seed seluruh seed per tenant di 1 tenant baru
     *
     * @param integer $tenantId
     * @return boolean
     */
    public function seed($tenantId)
    {
        //jika database belum ada maka tolak
        if (!$this->dbExists($tenantId)) {
            return false;
        }

        $seeds = config('hpsynapse.seed_path');
        foreach ($seeds as $seedpath) {
            $seedFileList = glob($seedpath . DIRECTORY_SEPARATOR . '*.php');
            foreach ($seedFileList as $seed) {
                include_once $seed;
                $seedClass = ucfirst(
                    Str::camel(
                        substr(
                            str_replace('.php', '', basename($seed)),
                            18
                        )
                    )
                );
                $tmpClass = new $seedClass;
                // hanya meng-seed yang seed pertenant saja
                if (method_exists($tmpClass, 'tenantSeedMode')) {
                    if (
                        !property_exists($tmpClass, 'tenantId')
                        || $tmpClass->tenantId == $tenantId
                    ) {
                        $tmpClass->setTenantSeedMode(true);
                        $tmpClass->setTenantId($tenantId);
                        $tmpClass->run();
                    }
                }
            }
        }

        return true;
    }

    /**
     * create database per Koperasi, saat ini hanya support MariaDB/MySQL
     *
     * @param integer $tenantId
     * @param integer $dbServerId
     * @return boolean
     */
    public function createDatabase($tenantId, $dbServerId = 0)
    {
        if (
            ($dbServerId == 0 && config('xmlapi.dbcreate_use_cpanel'))
            || ($dbServerId != 0
                && config(
                    "database.multi_database_server.servers." . $dbServerId . ".cpanel.dbcreate_use_cpanel",
                    false
                )
            )
        ) {
            return $this->createDatabaseCpanel($tenantId, $dbServerId);
        } else {
            return $this->createDatabaseSql($tenantId, $dbServerId);
        }
    }

    /**
     * create database menggunakan query sql
     *
     * @param integer $tenantId
     * @param integer $dbServerId
     * @return boolean
     */
    public function createDatabaseSql($tenantId, $dbServerId = 0)
    {

        //jika database sudah ada maka tolak
        if ($this->dbExists($tenantId)) {
            return false;
        }

        // jika di server db utama
        if ($dbServerId == 0) {
            $dbServerConfig = "database.connections." . config("database.perTenant");
            $schemaName = config($dbServerConfig . ".database_prefix")
                . $tenantId;
            $charset = config($dbServerConfig . ".charset", 'utf8mb4');
            $collation = config(
                $dbServerConfig . ".collation",
                'utf8mb4_general_ci'
            );
            DB::statement("CREATE DATABASE IF NOT EXISTS $schemaName CHARACTER SET $charset COLLATE $collation;");
        } else {
            $dbServerConfig = "database.multi_database_server.servers." . $dbServerId;
            $schemaName = config($dbServerConfig . ".database_prefix") . $tenantId;
            $charset = config($dbServerConfig . ".charset", 'utf8mb4');
            $collation = config($dbServerConfig . ".collation", 'utf8mb4_general_ci');

            $pdo = new \PDO(
                "mysql:host=" . config($dbServerConfig . ".host"),
                config($dbServerConfig . ".username"),
                config($dbServerConfig . ".password")
            );
            // $pdo = Tenant::getDbRawPDO($koperasiId);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $schemaName CHARACTER SET $charset COLLATE $collation;");
        }
        // generate connection config per tenant nya
        // Tenant::getDbConnection($koperasiId);
        // DB::connection(Tenant::getDbConnectionName($koperasiId))->statement("CREATE DATABASE IF NOT EXISTS $schemaName CHARACTER SET $charset COLLATE $collation;");

        return true;
    }

    /**
     * create database menggunakan api cpanel
     *
     * @param integer $tenantId
     * @param integer $dbServerId
     * @return boolean
     */
    public function createDatabaseCpanel($tenantId, $dbServerId = 0)
    {

        // jika di server db utama
        if ($dbServerId == 0) {
            $dbPrefix = config("database.connections." . config("database.perTenant") . ".database_prefix");
            $cpanel = [
                'username' => config('xmlapi.username'),
                'password' => config('xmlapi.password'),
                'domain' => config('xmlapi.domain')
            ];
        } else {
            $dbPrefix = config('database.multi_database_server.servers.' . $dbServerId . '.database_prefix');
            $cpanel = [
                'username' => config('database.multi_database_server.servers.' . $dbServerId . '.cpanel.username'),
                'password' => config('database.multi_database_server.servers.' . $dbServerId . '.cpanel.password'),
                'domain' => config('database.multi_database_server.servers.' . $dbServerId . '.cpanel.domain')
            ];
        }

        $schemaName = $dbPrefix . $tenantId;
        $uapi = new \App\Services\cpanelAPI(
            $cpanel['username'],
            $cpanel['password'],
            $cpanel['domain']
        ); //instantiate the object
        $ret = $uapi->uapi->Mysql->create_database(array('name' => $schemaName));

        if ($ret->status == 0)
            $this->error = $ret->errors[0];

        return $ret->status ? true : false;
    }

    /**
     * Get nama table dengan prefix tenant
     *
     * @param string $tableName
     * @param integer $tenantId
     * @return string
     */
    public function getTableName($tableName, $tenantId)
    {
        // jika multi tenant dalam 1 database beda table
        if (config('AppConfig.system.multitenant.data_mode') == 2) {
            return config('AppConfig.system.multitenant.table_prefix', '_')
                . $tenantId . '_' . $tableName;
        } else {
            return $tableName;
        }
    }

    /**
     * DB TRANSACTION PER TENANT CONNECTION
     */

    /**
     * JANGAN DIGUNAKAN DULU - KAYANYA MASIH BUG BELUM TESTING LAGI
     * detek otomatis dbtransaction
     *
     * @param object $that $this dari class bersangkutan
     * @param string $func callback fungsi yang akan dieksekusi dengan format $func($that)
     * @param string $rollbackFunc callback fungsi saat terjadi error
     *
     * @return boolean true jika berhasil, false atau throw error jika gagal
     */
    public function dbBeginTransactionIfNotExist($that, $func, $rollbackFunc = null)
    {
        // jika belum ada transaksi aktif maka aktifkan
        $dontHaveTransactionLevel = !$this->dbTransactionLevel();

        try {
            if ($dontHaveTransactionLevel)
                $this->dbBeginTransaction();

            $return = $func($that);

            if ($dontHaveTransactionLevel)
                $this->dbCommit();
        } catch (Exception  $e) {
            $return = false;

            if ($dontHaveTransactionLevel)
                $this->dbRollback();

            $this->error = $e->getMessage();

            Log::error('dbBeginTransactionIfNotExist ERROR');
            Log::error($e);

            // eksekusi rollback function jika disertakan
            if ($rollbackFunc != null)
                $rollbackFunc($that);

            // jika sedang dalam transaksi dari parent maka teruskan error nya ke parent transaction nya
            if (!$dontHaveTransactionLevel)
                throw new Exception($this->errorFull());
        }

        return $return;
    }

    /**
     * get transaction level
     *
     * @param integer $tenantId
     * @return integer
     */
    public function dbTransactionLevel($tenantId = false)
    {
        $this->getTenantConnection($tenantId)->transactionLevel();
    }

    /**
     * begin db transaction pertenant, hanya eksekusi di multi tenant db yg sudah di-initialize sebelumnya
     *
     * @param integer $tenantId
     * @return void
     */
    public function dbBeginTransaction($tenantId = false)
    {
        $this->getTenantConnection($tenantId)->beginTransaction();
    }

    /**
     * commit db transaction pertenant, hanya eksekusi di multi tenant db yg sudah di-initialize sebelumnya
     *
     * @param integer $tenantId
     * @return void
     */
    public function dbCommit($tenantId = false)
    {
        $this->getTenantConnection($tenantId)->commit();
    }

    /**
     * rollback db transaction pertenant, hanya eksekusi di multi tenant db yg sudah di-initialize sebelumnya
     *
     * @param integer $tenantId
     * @return void
     */
    public function dbRollback($tenantId = false)
    {
        $this->getTenantConnection($tenantId)->rollback();
    }

    /**
     * Verify Tenant ID or Return Default ID from Config or Active Tenant
     *
     * @param integer $tenantId
     * @return integer A Valid Tenant ID
     */
    public function verifyTenantIdOrDefault($tenantId = false)
    {
        return $tenantId ?: config('tenant.id', false) ?: $that->getActiveTenant('id');
    }

    /**
     * Get DB Connection for given Tenant ID
     *
     * @param integer $tenantId
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function getTenantConnection($tenantId = false)
    {
        $tenantId = $that->verifyTenantIdOrDefault($tenantId);
        $this->getDbConnection($tenantId);
        return DB::connection($this->getDbConnectionName($tenantId));
    }

    /**
     * db per tenant, alias dari diatas
     *
     * @param integer $tenantId
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function db($tenantId = false)
    {
        return $this->getTenantConnection($tenantId);
    }

    /**
     * END - GROUP MANAGE PEMISAHAN DATABASE ATAU TABLE PER TENANT
     */


    /**
     * CRUD tenant
     */

    /**
     * Create Tenant
     *
     * @param array $input
     * @return false|array
     */
    public function createTenant($input)
    {
        if (!isset($input['group_app'])) {
            return false;
        }

        $input['domain'] = $input['group_app'] . '.' . config('AppConfig.system.multitenant.main_domain');

        // pastikan default config sudah terset
        if (!isset($input['config'])) {
            $input['config'] = [
                'storage_limit' => 0,
                'db_limit' => 0,
                'resource_limit' => 0,
            ];
        }

        $return = $this->_autoResourceCreate('createTenant', [$input]);
        if ($return) {
            $this->_autoResourceCreate('createTenantDomain', [[
                'tenant_id' => $return['id'],
                'domain' => $input['domain'],
                'status' => 1
            ]]);
        }

        // setelah proses create pastikan _tenant.json diupdate
        \App\Services\Utilities::artisan('synapse:updateTenantList');
        \App\Services\Utilities::artisan('config:cache');
        return $return;
    }

    /**
     * Update Tenant
     *
     * @param array $where Synapse Formatted Where array
     * @param array $data
     * @return false|integer false =  gagal, integer = affected rows
     */
    public function updateTenant($where, $data = array())
    {
        $oldTenant = $this->getTenant($where);
        if (!$oldTenant) {
            $this->error = __('lang.data_attribute_not_found', ['attribute' => 'Tenant']);
            return false;
        }

        if (isset($data['group_app']) && $oldTenant['group_app'] != $data['group_app']) {
            $data['domain'] = $data['group_app'] . '.' . config('AppConfig.system.multitenant.main_domain');
            $this->_autoResourceUpdate('updateTenantDomain', [
                [
                    ['domain', $oldTenant['domain']],
                    ['tenant_id', $oldTenant['id']],
                ],
                [
                    'domain' => $data['domain']
                ]
            ]);
        }

        $return = $this->_autoResourceUpdate('updateTenant', [
            $where,
            $data
        ]);

        // jika update status, db, s3storage
        if (isset($data['status']) || isset($data['db']) || isset($data['s3storage'])) {
            \App\Services\Utilities::artisan('synapse:updateTenantList');
            \App\Services\Utilities::artisan('config:cache');
        }

        return $return;
    }

    /**
     * Delete Tenant
     *
     * @param array $where Synapse Formatted Where array
     * @return boolean
     */
    public function deleteTenant($where)
    {
        $oldTenant = $this->_autoResourceGet('getTenant', [$where]);
        if ($oldTenant) {
            $return = $this->_autoResourceDelete('deleteTenant', [$where]);
            $this->_autoResourceDelete('deleteTenantGroupTenant', [['tenant_id', $oldTenant['id']]]);
            $this->_autoResourceDelete('deleteTenantDomain', [['tenant_id', $oldTenant['id']]]);
            // setelah proses delete pastikan _tenant.json diupdate
            \App\Services\Utilities::artisan('synapse:updateTenantList');
            \App\Services\Utilities::artisan('config:cache');
            return $return;
        }
        $this->error = __('lang.data_attribute_not_found', ['attribute' => 'Tenant']);
        return false;
    }


    /**
     * STORAGE
     */

    /**
     * get disk yang digunakan tenant yang disertakan, jika tidak ada maka akan
     * return disk default.
     *
     * @param integer $tenantId         Id tenant, jika 0 berarti akan ambil tenant yang aktif
     * @param boolean $isPublic
     *
     * @return string nama disknya
     */
    public function storageGetDisk($tenantId = 0, $isPublic = false)
    {
        // jika mendefinisikan tenant ID
        if (config('AppConfig.system.multitenant.active') && $tenantId) { // && config('tenant.id',0) != $tenantId){
            $serverId = config('AppConfig.tenant.' . $tenantId . '.s3storage', 0);

            // jika multi tenant aktif dan menggunakan s3 storage
            if (
                config('filesystems.s3_multi_server') &&
                $serverId != 0 &&
                config('filesystems.disks.s3_' . $serverId, false) != false
            ) {
                $disk = 's3_' . $serverId;
            } else {
                // set storage baru
                if ($isPublic) {
                    $disk = config(
                        'filesystems.disks.public_tenant_' . $tenantId . '.name',
                        $this->setStorageLocalPublicDisk($tenantId)
                    );
                } else {
                    $disk = config(
                        'filesystems.disks.local_tenant_' . $tenantId . '.name',
                        $this->setStorageLocalDisk($tenantId)
                    );
                }
            }

            return $disk;
        }

        return config('filesystems.default');
    }


    /**
     * get disk local yang digunakan tenant yang disertakan, jika tidak ada maka akan
     * return disk default.
     *
     * @param integer $tenantId         Id tenant, jika 0 berarti akan ambil tenant yang aktif
     * @param boolean $isPublic
     *
     * @return string nama disknya
     */
    public function storageGetDiskLocal($tenantId = 0, $isPublic = false)
    {
        // jika mendefinisikan tenant ID
        if (config('AppConfig.system.multitenant.active') && $tenantId) { // && config('tenant.id',0) != $tenantId){

            // set storage baru
            if ($isPublic) {
                $disk = config(
                    'filesystems.disks.public_tenant_' . $tenantId . '.name',
                    $this->setStorageLocalPublicDisk($tenantId)
                );
            } else {
                $disk = config(
                    'filesystems.disks.local_tenant_' . $tenantId . '.name',
                    $this->setStorageLocalDisk($tenantId)
                );
            }

            return $disk;
        }

        return config('filesystems.default');
    }

    /**
     * set config storage local per tenant
     *
     * @param integer $tenantId Id tenant
     *
     * @return string nama disk nya
     */
    public function setStorageLocalDisk(int $tenantId)
    {
        $tmpConfig = config('filesystems.disks.local');
        $tmpConfig['name'] = 'local_tenant_' . $tenantId;
        $tmpConfig['root_old'] = $tmpConfig['root_old'] ?? $tmpConfig['root'];
        $tmpConfig['root'] = $tmpConfig['root_old'] . '/tenant_' . $tenantId;
        app()->config['filesystems.disks.local_tenant_' . $tenantId] = $tmpConfig;

        return $tmpConfig['name'];
    }

    /**
     * set config storage local public per tenant
     *
     * @param integer $tenantId Id tenant
     *
     * @return string nama disk nya
     */
    public function setStorageLocalPublicDisk(int $tenantId)
    {
        $tmpConfig = config('filesystems.disks.public');
        if ($tmpConfig['driver'] == 's3')
            $tmpConfig = config('filesystems.disks.local');

        $tmpConfig['name'] = 'public_tenant_' . $tenantId;
        $tmpConfig['root_old'] = $tmpConfig['root_old'] ?? $tmpConfig['root'];
        $tmpConfig['root'] = $tmpConfig['root_old'] . '/tenant_' . $tenantId;
        $tmpConfig['visibility'] = 'public';
        app()->config['filesystems.disks.public_tenant_' . $tenantId] = $tmpConfig;

        return $tmpConfig['name'];
    }

    /**
     * get Storage instance per tenant
     *
     * @param integer Id tenant, jika 0 berarti akan ambil tenant yang aktif
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public function storage($tenantId = 0)
    {
        return Storage::disk($this->storageGetDisk($tenantId));
    }

    /**
     * get apakah tenant tersebut menggunakan storage S3 atau tidak
     *
     * @param integer Id tenant, jika 0 berarti akan ambil tenant yang aktif
     * @return boolean
     */
    public function storageIsS3($tenantId = 0)
    {
        return config('AppConfig.tenant.' . $tenantId . '.s3storage', 0) ? true : false;
    }

    /**
     * Get Storage Size
     *
     * @param integer $tenantId
     * @return float Ukuran storage dalam MB
     */
    public function storageSize($tenantId)
    {

        $bytes = $this->storageSize_byDisk($this->storageGetDisk($tenantId));

        // jika storage s3 maka kalkulasi juga disk storage local nya
        if ($this->storageIsS3($tenantId)) {
            $bytes +=  $this->storageSize_byDisk(config(
                'filesystems.disks.local_tenant_' . $tenantId . '.name',
                $this->setStorageLocalDisk($tenantId)
            ));
            $bytes +=  $this->storageSize_byDisk(config(
                'filesystems.disks.public_tenant_' . $tenantId . '.name',
                $this->setStorageLocalDisk($tenantId)
            ));
        }

        return round($bytes / 1024 / 1024, 2);
    }

    /**
     * Get Storage Size by Specific Disk
     *
     * @param string $disk
     * @return float
     */
    private function storageSize_byDisk($disk)
    {
        return array_sum(
            array_map(
                function ($file) {
                    return (float) $file['size'];
                },
                array_filter(
                    Storage::disk($disk)->listContents('/', true /*<- recursive*/),
                    function ($file) {
                        return $file['type'] == 'file';
                    }
                )
            )
        );
    }

    /**
     * Get Storage Size by Directory
     *
     * @param string $dir
     * @return float
     */
    private function storageSize_byDir($dir)
    {
        $total_size = 0;
        $count = 0;
        $dir_array = scandir($dir);
        foreach ($dir_array as $key => $filename) {
            if ($filename != ".." && $filename != ".") {
                if (is_dir($dir . "/" . $filename)) {
                    $new_foldersize = $this->storageSize_byDir($dir . "/" . $filename);
                    $total_size = $total_size + $new_foldersize;
                } else if (is_file($dir . "/" . $filename)) {
                    $total_size = $total_size + filesize($dir . "/" . $filename);
                    $count++;
                }
            }
        }
        return $total_size;
    }
}
