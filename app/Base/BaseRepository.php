<?php

namespace App\Base;

use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Facades\Log;
use App\Base\Traits\ResCacheTrait;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{

    use Macroable;
    use ResCacheTrait;

    //default model
    protected $model;

    //list field yg dimasukan untuk search
    protected $searchField = ['name'];

    protected $pagination = [
        'count' => 0,
        'offset' => 0,
        'limit' => 0,
        'currentPage' => 1,
        'pageCount' => 1
    ]; //default hasil list disimpan

    private $paginationDefault = [
        'count' => 0,
        'offset' => 0,
        'limit' => 0,
        'currentPage' => 1,
        'pageCount' => 1
    ]; //default data untuk $pagination

    /**
     * BLOCK CLASS DEPENDENCY
     * -------------------------------------------------------------------------------------
     *
     * class dependendency ini digunakan untuk dependency ke class yg menggunakan bindind interface & model nya.
     * dibuat seperti ini karena saat repository di binding dengan interface, maka sulit untuk repository tersebut mendependency ke repository yg juga binding dengna interface.
     */
    private $dependencyLoaded = []; //list instance dari class dependency yg telah diload
    protected $dependency = [
        // 'jurnal' => 'App\MainApp\Modules\Pembukuan\Contracts\Jurnal' --> list array dependency
    ];

    /**
     * Autoload dependency
     */
    public function __get($name)
    {
        if (isset($this->dependency[$name])) {
            if (!isset($this->dependencyLoaded[$name])) {
                $this->dependencyLoaded[$name] = resolve($this->dependency[$name]);
                if (method_exists($this->dependencyLoaded[$name], 'setTenantId'))
                    $this->dependencyLoaded[$name]->setTenantId($this->tenantId);
            }
            return $this->dependencyLoaded[$name];
        }
        throw new Exception("Property $name is not defined");
    }
    /**
     * -------------------------------------------------------------------------------------
     * / BLOCK CLASS DEPENDENCY
     */

    /**
     * BLOCK AUTO RESOURCE
     * -------------------------------------------------------------------------------------
     *
     * auto fungsion
     */
    protected $_initialized = false; //untuk menandai apakah fungsi2 init awal sudah dieksekusi,
    // agar tidak di over exekusi

    protected $autoResource = [
        // 'Workshop' => 'hpsynapse\modworkshop\Models\Ws' // 1 general model
        // 'Workshop' => [
        //     'r' => 'hpsynapse\modworkshop\Models\Ws',
        //     'w' => 'hpsynapse\modworkshop\Models\Ws',
        // ]
    ];
    protected $autoResourceSearchField = [
        // 'Workshop' => ['name'] --> list array workshop
    ];
    //list field validation saat create
    protected $autoResourceCreateValidate = [
        // 'Workshop' => ['nama'=>'required']
    ];
    //list field validation saat update
    protected $autoResourceUpdateValidate = [
        // 'Workshop' => ['nama'=>'required']
    ];

    public function __construct()
    {
        if ($this->_initialized == false)
            $this->_callInit();
    }

    /**
     * Initialize
     *
     * @return void
     */
    public function _callInit()
    {
        $this->setAutoResourceCreateValidate();
        $this->setAutoResourceUpdateValidate();
        $this->_initialized = true;
    }

    /**
     * Override
     *
     * Define Validation for Creating Resource
     *
     * @return void
     */
    public function setAutoResourceCreateValidate()
    {
    }

    /**
     * Override
     *
     * Define Validation for Updating Resource
     *
     * @return void
     */
    public function setAutoResourceUpdateValidate()
    {
    }

    /**
     * Magic function for method with prefix:
     * - list
     * - getReadModel
     * - getWriteModel
     * - getCreateValidation
     * - getUpdateValidation
     * - get
     * - create
     * - update
     * - delete
     * or with 'Exists' suffix
     *
     * @param [type] $name
     * @param [type] $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (strpos($name, 'list') === 0) {
            return $this->_autoResourceList($name, $arguments);
        } else if (strpos($name, 'getReadModel') === 0) {
            return $this->_autoResourceGetModel($name, true);
        } else if (strpos($name, 'getWriteModel') === 0) {
            return $this->_autoResourceGetModel($name, false);
        } else if (strpos($name, 'getCreateValidation') === 0) {
            return $this->_autoResourceGetValidation($name, true);
        } else if (strpos($name, 'getUpdateValidation') === 0) {
            return $this->_autoResourceGetValidation($name, false);
        } else if (strpos($name, 'get') === 0) {
            return $this->_autoResourceGet($name, $arguments);
        } else if (strpos($name, 'create') === 0) {
            return $this->_autoResourceCreate($name, $arguments);
        } else if (strpos($name, 'update') === 0) {
            return $this->_autoResourceUpdate($name, $arguments);
        } else if (strpos($name, 'delete') === 0) {
            return $this->_autoResourceDelete($name, $arguments);
        } else if (strrpos($name, 'Exists')) {
            return $this->_autoResourceExists($name, $arguments);
        }

        throw new Exception("Method " . $name . " is not defined");
    }

    /**
     * Get Resource Model
     *
     * @param string $name Function/Model Name
     * @param bool $isRead false = use write resource config
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function _autoResourceGetModel($name, $isRead = true)
    {
        $model = substr($name, $isRead ? 12 : 13);
        $rw = $isRead ? 'r' : 'w';
        if (
            is_array($this->autoResource[$model])
            && isset($this->autoResource[$model][$rw])
        ) {
            return is_string($this->autoResource[$model][$rw])
                ? new $this->autoResource[$model][$rw]
                : $this->autoResource[$model][$rw];
        } else if (!is_array($this->autoResource[$model])) {
            return is_string($this->autoResource[$model])
                ? new $this->autoResource[$model]
                : $this->autoResource[$model];
        }

        throw new Exception("Method " . $name . " is not defined");
    }

    /**
     * Get Resource Validation
     *
     * @param string $name Function/Model Name
     * @param boolean $isCreate Create/Update Validation?
     * @return array Validation Rules array
     */
    protected function _autoResourceGetValidation($name, $isCreate = true)
    {
        $model = substr($name, 19);
        if ($isCreate && isset($this->autoResourceCreateValidate[$model])) {
            return $this->autoResourceCreateValidate[$model];
        } else if (isset($this->autoResourceUpdateValidate[$model])) {
            return $this->autoResourceUpdateValidate[$model];
        }

        throw new Exception("Method " . $name . " is not defined");
    }

    /**
     * Get List from Resource
     *
     * @param string $name Function/Model Name
     * @param array $arguments Function Arguments:
     *      [
     *          (array) $filter,
     *          (int) $offset,
     *          (int) $limit,
     *          (array) $orderBy,
     *          (bool) $returnAsModel
     *      ]
     * @return array|\Illuminate\Database\Eloquent\Model jika array, akan berisi data pagination
     */
    protected function _autoResourceList($name, $arguments)
    {
        $model = substr($name, 4);
        if (isset($this->autoResource[$model])) {

            $filter = $arguments[0] ?? [];
            //jika tidak menyertakan searchfield maka gunakan default
            if (!isset($filter['searchField']))
                $filter['searchField'] = $this->autoResourceSearchField[$model]
                    ?? $this->searchField;

            return $this->_list(
                $this->{'getReadModel' . $model}(),
                $filter,
                $arguments[1] ?? 0,
                $arguments[2] ?? 0,
                $arguments[3] ?? [],
                $arguments[4] ?? false
            );
        }

        throw new Exception("Method " . $name . " is not defined");
    }

    /**
     * Get 1 Record as Array
     *
     * @param string $name Function/Model Name
     * @param array $arguments Function Arguments:
     *      [(array|int) $filterOrId]
     * @return void
     */
    protected function _autoResourceGet($name, $arguments)
    {
        $model = substr($name, 3);
        if (isset($this->autoResource[$model])) {
            return $this->_getOne(
                $this->{'getReadModel' . $model}(),
                $arguments[0] ?? null
            );
        }

        throw new Exception("Method " . $name . " is not defined");
    }

    /**
     * Create Resource
     *
     * @param string $name Function/Model Name
     * @param array $arguments array data
     *      [$arrayData]
     * @return false|array false = gagal validasi/insert
     *                     array = sukses, data yang di-insert-kan
     */
    protected function _autoResourceCreate($name, $arguments)
    {
        $model = substr($name, 6);
        if (isset($this->autoResource[$model])) {
            $createModel = $this->{'getWriteModel' . $model}();

            $data = $arguments[0] ?? [];
            $data = $this->_filterAllowField($data, $createModel->getFillable());
            if (isset($this->autoResourceCreateValidate[$model])) {
                //jika error/tidak valid
                if (!$this->_createValidate(
                    $this->autoResourceCreateValidate[$model],
                    $data
                )) {
                    return false;
                }
            }
            return $this->_create($createModel, $data);
        }

        throw new Exception("Method " . $name . " is not defined");
    }

    /**
     * Update Resource
     *
     * @param string $name Function/Model Name
     * @param array $arguments Argument for Condition and Updated Data
     *      [
     *          $arrayWhereOrId,
     *          $updatedData
     *      ]
     * @return false|int false = gagal validasi/update
     *                   int = sukses, jumlah affected rows
     */
    protected function _autoResourceUpdate($name, $arguments)
    {
        $model = substr($name, 6);
        if (isset($this->autoResource[$model])) {
            $updateModel = $this->{'getWriteModel' . $model}();
            $data = $arguments[1] ?? [];

            $data = $this->_filterAllowField($data, $updateModel->getFillable());
            if (isset($this->autoResourceUpdateValidate[$model])) {
                //jika error/tidak valid
                if (!$this->_updateValidate(
                    $this->autoResourceUpdateValidate[$model],
                    $data
                )) {
                    return false;
                }
            }
            return $this->_update(
                $updateModel,
                $arguments[0] ?? null,
                $data
            );
        }

        throw new Exception("Method " . $name . " is not defined");
    }

    /**
     * resource delete
     */
    /**
     * Delete Resource
     *
     * @param string $name Function/Model name
     * @param array $arguments
     *      [$where]
     * @return bool
     */
    protected function _autoResourceDelete($name, $arguments)
    {
        $model = substr($name, 6);
        if (isset($this->autoResource[$model])) {
            return $this->_delete(
                $this->{'getWriteModel' . $model}(),
                $arguments[0] ?? null
            );
        }

        throw new Exception("Method " . $name . " is not defined");
    }

    /**
     * Check if Resource Exists
     *
     * @param string $name Function/Model name
     * @param array $arguments
     * @return bool
     */
    protected function _autoResourceExists($name, $arguments)
    {
        $model = ucfirst(substr($name, 0, -6));
        if (isset($this->autoResource[$model])) {
            return $this->_exists(
                $this->{'getReadModel' . $model}(),
                $arguments[0] ?? null
            );
        }

        throw new Exception("Method " . $name . " is not defined");
    }

    /**
     * -------------------------------------------------------------------------------------
     * / BLOCK AUTO RESOURCE
     */

    protected $tenantId = 0;

    /**
     * Set Tenant ID
     *
     * @param integer $tenantId
     * @return void
     */
    public function setTenantId(int $tenantId = 0)
    {
        $this->tenantId = $tenantId;
    }

    protected $error = ''; //error message string
    protected $errorValidator = []; //error validator/request
    protected $errorCode = 0; //error code

    /**
     * Clear error data
     *
     * @return void
     */
    public function clearError()
    {
        $this->error = '';
        $this->errorValidator = [];
        $this->errorCode = 0;
    }

    /**
     * Get full error message in HTML format
     *
     * @return string Error Message
     */
    public function errorFull()
    {
        $errorValidator = '';
        if ($this->errorValidator) {
            $errorValidator = '<br><ul>' . implode("\n", array_map(function ($v) {
                return '<li>' . $v . '</li>';
            }, $this->errorValidator)) . '</ul>';
        }

        return $this->error . $errorValidator;
    }

    /**
     * Get error string
     *
     * @return string error string
     */
    public function error()
    {
        return $this->error;
    }

    /**
     * Get Validation error string
     *
     * @return string error string
     */
    public function errorValidator()
    {
        return $this->errorValidator;
    }

    /**
     * Get error code
     *
     * @return int error code
     */
    public function errorCode()
    {
        return $this->errorCode;
    }

    /**
     * Get Default Eloquent Model
     *
     * @return \Illuminate\Database\Eloquent\Model model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set Default Model
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function setModel(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get Default Listing Output
     *
     * @return array
     */
    protected function getDefaultListFormat()
    {
        return $this->paginationDefault;
    }

    protected $triggerEvent = true;

    /**
     * Enable Trigger Event
     *
     * @return void
     */
    public function enableEvent()
    {
        $this->triggerEvent = true;
    }

    /**
     * Disable Trigger Event
     *
     * @return void
     */
    public function disableEvent()
    {
        $this->triggerEvent = false;
    }

    /*
     * MAIN FUNCTION
     * -------------------------------------------------------------------------
     */

    /**
     * Cek apakah key di $inputData ada semua di $availableFields
     *
     * @param array         $inputData          array data
     * @param array         $availableFields    list available field nya
     *
     * @return boolean                           true jika sesuai, false jika tidak sesuai
     */
    final protected function _checkField(array $inputData = [], $availableFields = null)
    {
        $collection = collect($inputData);

        return $collection->every(function ($value, $key) use ($availableFields) {
            return in_array($key, $availableFields);
        });
    }

    /**
     * Hapus semua item array $data yang tidak ada di $allowedFields.
     *
     * filter array hanya berdasarkan key yg diallow nya saja
     * filter $data yang akan di update / output / field, jika ada field yang tidak sesuai dengan
     * list allowedFields field maka akan dihapus
     *
     * @param array         $data               input datanya
     * @param array         $allowedFields    daftar field-field yang boleh ada di $data
     *
     * @return array                            hasil filter data
     */
    protected function _filterAllowField(array $data = [], array $allowedFields = null)
    {
        $collection = collect($data);

        return $collection->filter(function ($value, $key) use ($allowedFields) {
            return in_array($key, $allowedFields);
        })->toArray();
    }

    /**
     * Hapus semua item array $data yang ada di $rejectedFields.
     * 
     * filter array berdasarkan field yang tidak boleh ada (dihapus)
     * filter data yang akan di update / output / field, jika ada field yang terdaftar
     * di rejectedField maka akan dihapus
     *
     * @param array        $data                input datanya
     * @param array        $rejectedFields      list field yang akan dihapus
     * @return array                            hasil filter data
     */
    final protected function _filterField($data, $rejectedFields = null)
    {
        $collection = collect($data);

        return $collection->filter(function ($value, $key) use ($rejectedFields) {
            return !in_array($key, $rejectedFields);
        })->toArray();
    }

    /**
     * hapus semua data yang value nya kosong
     *
     * @param array     $data       array data yang difilter
     *
     * @return array                hasil data yang sudah difilter
     */
    final protected function _filterEmptyField(array $data = [])
    {
        $collection = collect($data);
        return $collection->filter(function ($value, $key) {
            return !empty($value);
        })->toArray();
    }

    /**
     * generate/build basic where function
     *
     * @param \Illuminate\Database\Eloquent\Model      $model
     * @param array         $filter     synapse where format
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    final protected function _where($model, $where)
    {
        //jika sudah kosong maka langsung kembalikan model nya
        if (empty($where)) return $model;

        //jika where di isi selain array maka asumsikan isinya adalah id table
        if (!is_array($where)) {
            $where = [['id', $where]];
        }

        //berarti sudah tidak nested, harusnya yang sudah tidak nested tidak masuk ke sini
        if (isset($where[0]) && !is_array($where[0]) && $where[0] != 'or') {
            $model = $this->__whereNotNested($model, $where);
        } else {
            $model = $this->__whereNested($model, $where);
        }

        return $model;
    }

    /**
     * helper untuk _where(), memproses array where yang masih nested
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $where
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function __whereNested($model, $where)
    {

        foreach ($where as $value) {
            //jika value[1] tidak ada kemungkinan ada yang keliru input format, maka langsung tolak
            if (!is_array($value) || !array_key_exists(1, $value)) return $model;

            //jika sudah tidak nested maka langsung proses
            if (
                is_array($value)
                && !is_array($value[0])
                && strtolower($value[0]) != 'or'
            ) {
                $model = $this->__whereNotNested($model, $value);
                //jika masih nested maka process recursive lagi
            } else {
                $varWhere = 'where';
                //detek apakah or
                if (!is_array($value[0]) && strtolower($value[0]) == 'or') {
                    unset($value[0]);
                    $varWhere = 'orWhere';
                }
                $model = $model->$varWhere(function ($model) use ($value) {
                    $model = $this->_where($model, $value);
                });
            }
        }
        return $model;
    }

    /**
     * helper untuk _where(), memproses array where yang sudah tidak nested
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $where
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function __whereNotNested($model, $where)
    {

        $op = '=';
        $field = $where[0];
        $isOr = false;

        if (stripos($where[0], 'or ') === 0) {
            $field = str_ireplace('or ', '', $where[0]);
            $isOr = true;
        }

        //jika ada 3 item berarti menyertakan operator nya juga
        if (count($where) == 3) {
            $op = $where[1];
            $dVal = $where[2];
        } else {
            $dVal = $where[1];
        }

        //jika valuenya array berarti diprses menggunakan IN
        if (is_array($dVal)) {
            if ($isOr) {
                if ($op == '=') {
                    $model = $model->orWhereIn($field, $dVal);
                } else {
                    $model = $model->orWhereNotIn($field, $dVal);
                }
            } else {
                if ($op == '=') {
                    $model = $model->whereIn($field, $dVal);
                } else {
                    $model = $model->whereNotIn($field, $dVal);
                }
            }
        } else {
            if ($isOr) {
                if ($dVal === 'NULL') {
                    $model = $model->orWhereNull($field);
                } else if ($dVal === 'NOT NULL') {
                    $model = $model->orWhereNotNull($field);
                } else {
                    $model = $model->orWhere($field, $op, $dVal);
                }
            } else {
                if ($dVal === 'NULL') {
                    $model = $model->whereNull($field);
                } else if ($dVal === 'NOT NULL') {
                    $model = $model->whereNotNull($field);
                } else {
                    $model = $model->where($field, $op, $dVal);
                }
            }
        }
        return $model;
    }

    /**
     * pemrosesan default search data berdasarkan $q string yg diinput
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $q query string
     * @param array $searchField list field yg di search nya
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    final protected function _searchString($model, $q, $searchField = false)
    {
        $model = $model->where(function ($query) use ($q, $searchField) {
            foreach ($searchField as $value) {
                $query = $query->orWhere($value, 'LIKE', '%' . $q . '%');
            }
        });
        return $model;
    }

    /**
     * Default fungsi list data
     *
     * @param \Illuminate\Database\Eloquent\Model instance $model model data yang digunakan
     * @param array $filter filter data jika ada
     *      tenantId|tenant_id      bigint              id tenant yang di filter
     *      q                       string              jika menyertakan ini maka akan dilakuan string filter berdasarkan field $searchField     *
     *      function                function($model)    filter tambahan jika diperlukan
     *      searchField             array               list field/column yg termasuk kedalam filter search
     *      hiddenColumn            array               list field/column yg di hidde * -- HINDARI PENGGUNAAN HIDDEN COLUMN UNTUK DATA BESAR
     *      append                  array|string        list custom attribute yg akan ditampilkan
     *      with                    array|string        list custom relation yg akan ditampilkan
     *      has                     array|string        list custom has yg akan ditampilkan
     *      idAsKey                 boolean             true jika key data menggunakan ID, false jika urutan array default (default false)
     *
     *      ADDITIONAL_PARAM array where untuk default filter
     * @param int $offset, posisi yg pertama kali ditampilkan
     * @param int $limit jika 0 maka view all, jumlah data yg ditampilkan
     * @param array $orderBy [['field','DESC/ASC'],['other_field','ASC/DESC']] atau array 1 level jika memang cuma 1 yg di order by nya
     * @param bool $returnModel True jika yang direturn hasil model eloquen, False jika format _list synapse
     *
     * @return array
     */
    final protected function _list(
        $model,
        array $filter = [],
        int $offset = 0,
        int $limit = 0,
        array $orderBy = [],
        $returnModel = false
    ) {

        if (!empty($orderBy)) {
            if (!is_array($orderBy[0]))
                $orderBy = [$orderBy];

            foreach ($orderBy as $oBitem) {
                $model = $model->orderBy($oBitem[0], $oBitem[1]);
            }
        }

        $hiddenColumn = null;
        $appendAttribut = null;
        $idAsKey = false; // key di list data, apakah menggunakan ID atau urut array secara default saja
        if (!empty($filter)) {
            if (isset($filter['idAsKey'])) {
                $idAsKey = true;
                unset($filter['idAsKey']);
            }
            $model = $this->_filter($model, $filter);
            if (isset($filter['hiddenColumn'])) {
                $hiddenColumn = $filter['hiddenColumn'];
                unset($filter['hiddenColumn']);
            }
            if (isset($filter['append'])) {
                $appendAttribut = $filter['append'];
                unset($filter['append']);
            }
            unset($filter);
        }

        if (empty($model)) {
            $this->pagination = $this->getDefaultListFormat();
            return $this->pagination;
        }

        $this->pagination['count'] = $model->count();
        $this->pagination['offset'] = $offset;
        $this->pagination['limit'] = $limit;
        $this->pagination['currentPage'] = 1;
        $this->pagination['pageCount'] = 1;

        if ($limit) {
            // $model = $model->limit($limit)->offset($offset);

            $this->pagination['currentPage'] = (int) ceil(($offset + 1) / $limit);
            $this->pagination['pageCount'] = (int) ceil($this->pagination['count'] / $limit);
        }

        if ($model) {
            // $this->pagination['query'] = $model->toSql();
            // $this->pagination['queryBindings'] = $model->getBindings();
            // if($appendAttribut){
            //     $this->pagination['data'] = $model->get()->append($appendAttribut)->toArray();
            // }else{
            //     $this->pagination['data'] = $model->get()->toArray();
            // }

            if ($returnModel)
                return $model;

            $this->_tmpListData = [];
            $this->chunkWithLimit(
                $model,
                100,
                $offset,
                $limit ?: null,
                function ($chunkedData) use ($appendAttribut) {
                    // $model->chunk(100, function ($data) use($appendAttribut) {
                    if ($appendAttribut)
                        $chunkedData = $chunkedData->append($appendAttribut);

                    foreach ($chunkedData as $item) {
                        $this->_tmpListData[] = $item->toArray();
                    }
                    usleep(50);
                }
            );

            $this->pagination['data'] = $this->_tmpListData;

            // jika menyertakan hiddeColumn berarti ada column yg di hide
            // jika menyertakan idAsKey berarti key data menggunakan field id
            if ($hiddenColumn || $idAsKey) {
                $tmpData = [];
                foreach ($this->pagination['data'] as $value) {
                    if ($hiddenColumn)
                        foreach ($hiddenColumn as $column) {
                            unset($value[$column]);
                        }
                    if ($idAsKey) {
                        $tmpData[$value['id']] = $value;
                    } else {
                        $tmpData[] = $value;
                    }
                }
                $this->pagination['data'] = $tmpData;
                unset($tmpData);

                // $collection = collect($this->pagination['data']);
                // $collection->transform(function($i) use ($hiddenColumn) {
                //     foreach ($hiddenColumn as $value) {
                //         unset($i[$value]);
                //     }
                //     return $i;
                // });
                // $this->pagination['data'] = $collection->toArray();
            }
        } else {
            $this->pagination['data'] = [];
        }
        return $this->pagination;
    }

    /**
     * Chunk the query with limit and process the chunked result using $callback if given
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param integer $count Jumlah Total Row
     * @param integer $offset Offset/Starting row
     * @param integer $remaining Sisa yang belum belum diproses
     * @param callable $callback Callback function untuk memproses chunk
     * @return bool false jika $callback me-return false
     */
    private function chunkWithLimit(
        $model,
        $count,
        $offset = 0,
        $remaining = null,
        callable $callback = null
    ) {
        do {
            if (!is_null($remaining)) {
                $limit = min($count, $remaining);
            } else {
                $limit = $count;
            }

            $results = $model->skip($offset)->take($limit)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if (is_callable($callback) && call_user_func($callback, $results) === false) {
                return false;
            }

            $offset += $countResults;

            if (!is_null($remaining)) {
                $remaining -= $countResults;
                if ($remaining == 0) {
                    break;
                }
            }
        } while ($countResults == $limit);

        return true;
    }

    /**
     * Inisiasi filter import, transform filter ke format where
     *
     * @param array $filter standard synapse filter ditambah :
     *      view_import     *Optional, untuk mode tampil data
     *                      - 0 atau jika tidak disertakan maka hanya menampilkan data aktif saja
     *                      - 1 menampilkan import yang on progress saja
     *                      - 2 menampilkan semua
     *      import_id       *Wajib diisi jika view_import = 1, berisi id importId
     * @return array transformed array
     */
    public function initImportFilter($filter)
    {
        if (!isset($filter['view_import'])) {
            // jika tidak menyertakan view_import maka tampilkan hanya data publish
            $filter[] = [
                ['is_import', 0],
                ['OR import_status', 1]
            ];
        } else {
            // jika menampilkan hanya data yg sedang import
            if ($filter['view_import'] == 1) {
                $filter[] = [
                    ['is_import', 1],
                    ['import_status', 0],
                    ['import_id', $filter['import_id']]
                ];
                unset($filter['import_id']);
                // jika bukan dua maka hanya tampilkan data publish saja (samakan dengan tidak menyertakan)
            } else if ($filter['view_import'] != 2) {
                $filter[] = [
                    ['is_import', 0],
                    ['OR import_status', 1]
                ];
            }
            unset($filter['view_import']);
        }

        return $filter;
    }

    /**
     * Penerapan Filter array ke Eloquent Model
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $filter
     * @return \Illuminate\Database\Eloquent\Model
     */
    final protected function _filter($model, array $filter = [])
    {
        if (empty($model)) return $model;

        $qSearch = null;
        $searchField = null;

        if (isset($filter['with'])) {
            $model = $model->with($filter['with']);
            unset($filter['with']);
        }

        if (isset($filter['has'])) {
            $model = $model->has($filter['has']);
            unset($filter['has']);
        }

        if (empty($filter['q']))
            unset($filter['q']);

        //hiddenColumn digunakan di filter saat result
        if (isset($filter['hiddenColumn']) || empty($filter['hiddenColumn']))
            unset($filter['hiddenColumn']);

        //append digunakan di filter saat result
        if (isset($filter['append']))
            unset($filter['append']);

        if (empty($filter['searchField']))
            unset($filter['searchField']);

        if (isset($filter['q'])) {
            $qSearch = $filter['q'];
            unset($filter['q']);
        }

        if (isset($filter['tenant_id'])) {
            $filter[] = ['tenant_id', $filter['tenant_id']];
            unset($filter['tenant_id']);
        } else if (isset($filter['tenantId'])) {
            $filter[] = ['tenant_id', $filter['tenantId']];
            unset($filter['tenantId']);
        }

        if (isset($filter['searchField'])) {
            $searchField = $filter['searchField'];
            unset($filter['searchField']);
        }

        if (isset($filter['function'])) {
            $model = $filter['function']($model);
            unset($filter['function']);
        }

        if (isset($filter))
            $model = $this->_where($model, $filter);

        if ($qSearch) {
            $searchField = $searchField ? $searchField : $this->searchField;
            $model = $this->_searchString($model, $qSearch, $searchField);
        }
        return $model;
    }

    /**
     * Generate pagination untuk di view blade (menggunakan pagination laravel)
     *
     * @param string $path path paginationnya
     * @param array $pagination
     *      count
     *      offset
     *      limit
     *      data
     * @return pagination instance
     */
    final protected function _getPagination(string $path = '', $pagination = false, $view = 'component.pagination')
    {
        if (!$path)
            $path = request()->url();
        if (!$pagination)
            $pagination = $this->pagination;
        return pagination_generate($pagination, $path, $view);
    }

    /**
     * Get 1 record data using Synapse Filter Array
     *
     * @param \Illuminate\Database\Eloquent\Model instance  $model
     * @param array|int         $filter     synapse array filter format, atau id table
     *
     * @return false|array    false jika gagal, array record jika ada
     */
    final protected function _getOne($model, $filter)
    {
        if (empty($model)) return $model;
        // $data = $this->_getOneModel($model, $filter);
        if (!is_array($filter))
            $filter = ['id', $filter];

        $data = $this->_filter($model, $filter)->first();

        if ($data && isset($filter['append'])) {
            $data = $data->append($filter['append']);
        }
        return $data ? $data->toArray() : false;
    }

    /**
     * Get 1 record data using Array Where (not synapse defined filter array) and return the Eloquent Model instead of array
     *
     * @param \Illuminate\Database\Eloquent\Model $model      model eloquent
     * @param array|int         $where      array where filter format, atau id table
     *
     * @return false|\Illuminate\Database\Eloquent\Model false jika gagal, aloquent collection jika berhasil
     */
    final protected function _getOneModel($model, $where)
    {
        if (empty($model))
            return $model;

        //jika array berarti berisi where
        if (!is_array($where))
            $where = [['id', $where]];

        $data = $this->_where($model, $where);
        $data = $data->first();

        if (!$data)
            return false;

        return $data;
    }

    /**
     * Check if data exists
     *
     * @param \Illuminate\Database\Eloquent\Model           $model      model eloquent
     * @param array|int         $ehere     array where filter format, atau id table
     *
     * @return boolean
     */
    final protected function _exists($model, $where): bool
    {
        if (empty($model))
            return false;

        //jika array berarti berisi where
        if (!is_array($where))
            $where = ['id', $where];

        $data = $this->_where($model, $where);

        return $data->exists();
    }

    /**
     * Insert new record and return the inserted data as array
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array $data
     * @return false|array    null jika gagal, atau array record databasenya jika berhasil
     */
    final protected function _create($model, array $data)
    {
        $this->clearError();
        //get QueryExeption
        try {
            if (($result = $model->create($data))) {
                return $model->find($result->id)->toArray();
            }
        } catch (\Illuminate\Database\QueryException $ex) {
            $this->error = $ex->getMessage();
        }
        return false;
    }

    /**
     * Validasi menggunakan laravel Validator saat create
     *
     * @param array $rules validator rule, key : nama field, value : rule
     * @param array $data data input nya
     * @return boolean valid atau tidak valid
     */
    final protected function _createValidate(array $rules, array $data)
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $this->error = __('alert.form_must_complete_title');
            $this->errorValidator = $validator->errors()->all();
            return false;
        }
        return true;
    }

    /**
     *
     * Update data
     *
     * @param \Illuminate\Database\Eloquent\Model          $model  instance eloquent model yang akan diupdate
     * @param array|int         $where  array where filter atau string/integer id data
     * @param array             $data   array data yang akan update
     *
     * @return boolean|integer     effected arrow atau true jika berhasil, false jika gagal
     */
    final protected function _update($model, $where, array $data)
    {
        $this->clearError();
        //get QueryExeption
        try {
            if (!is_array($where))
                $where = [['id', $where]];

            $model = $this->_where($model, $where);

            if ($model) {
                $return = $model->update($data);
                return $return == null ? true : $return;
            }
        } catch (\Illuminate\Database\QueryException $ex) {
            $this->error = $ex->getMessage();
        }

        return false;
    }

    /**
     * Validasi menggunakan laravel Validator saat update
     *
     * @param array $rules validator rule, key : nama field, value : rule
     * @param array $data data input nya
     * @return boolean valid atau tidak valid
     */
    final protected function _updateValidate(array $rules, array &$data)
    {
        $validateRule = [];
        foreach ($rules as $field => $rule) {
            //jika data disertakan maka proses validasinya
            if (isset($data[$field])) {
                //jika rule nya kosong berarti tandanya jangan dimasukan
                if (empty($rule)) {
                    unset($data[$field]);
                } else {
                    $validateRule[$field] = $rule;
                }
            }
        }

        $validator = Validator::make($data, $validateRule);

        if ($validator->fails()) {
            $this->error = __('alert.form_must_complete_title');
            $this->errorValidator = $validator->errors()->all();
            return false;
        }
        return true;
    }

    /**
     *
     * Delete data
     *
     * @param eloquent          $model  instance eloquent model yang akan diupdate
     * @param array|int         $where  array synapse where format atau integer id data
     *
     * @return boolean
     */
    final protected function _delete($model, $where): bool
    {
        if (empty($model))
            return false;

        $model = $this->_where($model, $where);

        if ($model != false) {
            if ($model->count() <= 0)
                return true;
            if ($model->delete())
                return true;
        } else {
            $this->error = __('lang.data_not_found');
        }

        return false;
    }

    /*
     * MAIN MODEL IMPLEMENTATION
     * -------------------------------------------------------------------------
     */

    /**
     * method default untuk listing data model default
     *
     * @param array         $filter     array synapse filter format
     * @param int           $offset
     * @param int           $limit
     * @param array         $orderBy
     *
     * @return array|null
     */
    public function getList(
        array $filter = [],
        int $offset = 0,
        int $limit = 0,
        array $orderBy = []
    ) {
        return $this->_list($this->model, $filter, $offset, $limit, $orderBy);
    }

    /**
     * Default pagination function
     *
     * @param string $path
     * @param array $pagination data pagination
     * @param string $view view pagination
     *
     * @return \Illuminate\Pagination\Paginator laravel object
     */
    public function getPagination($path = '', $pagination = false, $view = 'component.pagination')
    {
        return $this->_getPagination($path, $pagination, $view);
    }

    /**
     * method default untuk get 1 record data model utama
     *
     * @param array|int         $where  array synapse where format atau integer id data
     *
     * @return array|null
     */
    public function getOne($where)
    {
        return $this->_getOne($this->model, $where);
    }

    /**
     * cek apakah data yg dimaksud ada
     *
     * @param array|int         $where  array synapse where format atau integer id data
     *
     * @return boolean
     */
    public function exists($where): bool
    {
        return $this->_exists($this->model, $where);
    }

    /**
     * Create new data on default model
     *
     * @param array $data
     *
     * @return array|null
     */
    public function create(array $data)
    {
        return $this->_create($this->model, $data);
    }

    /**
     * Update data on default model
     *
     * @param array|integer     $key
     * @param array             $data
     *
     * @return null|integer     effected arrow atau null jika gagal
     */
    public function update($where, array $updatedData)
    {
        return $this->_update($this->model, $where, $updatedData);
    }

    /**
     * Delete data on default model
     *
     * @param bool
     */
    public function delete($where)
    {
        return $this->_delete($this->model, $where);
    }
}
