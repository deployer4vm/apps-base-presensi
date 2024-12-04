<?php

namespace App\Base;

// use App\Base\Traits\ResCacheTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as LaravelBaseController;
use Illuminate\Support\Facades\Session;

class BaseController extends LaravelBaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;
    // use ResCacheTrait;

    const MESSAGE_TYPE_SUCCESS = "success";
    const MESSAGE_TYPE_INFO = "info";
    const MESSAGE_TYPE_WARNING = "warning";
    const MESSAGE_TYPE_DANGER = "danger";

    private $requestPrepareClass = [['\App\Base\DefaultRequestPrepare', 'prepare']];

    /**
     * default data parameter untuk Responseable
     *
     * @var array
     */
    protected $output = [
        'http_code' => 200, //khusus response api, tapi tidak akan disertakan di return
        'status' => 200,
        'message' => '',
        'message_type' => 'info', //khusus warning view (bukan api), web request
        'data' => null,
        'params' => null,
        'viewdata' => null, //data yang hanya disertakan di web request
        'errors' => null,
        'error_code' => 0,
    ];

    /**
     * boolean nama variable wrap/grouping data di view (web request)
     */
    /**
     * apakah seluruh variable diwrap/grupping ke variable $viewWrapVarName
     *
     * @var boolean
     */
    protected $isViewVarWraped = false;

    /**
     * nama variable wrap/grouping,
     * untuk data berbentuk list array,
     * jadi di view akan jadi output->output['data'][VAR_NAME] dan di api akan jadi output->output['data']
     *
     * @var string
     */
    protected $viewWrapVarName = 'data'; //

    //default response paramter untuk
    protected $response = '';

    //nama class responsable nya
    private $responsableClass = '\App\Base\DefaultResponse';

    //force output menjadi api atau web
    private $forceOutput = 0; //0 auto, 1 WEB, 2 API

    /**
     * Set $this->output['data']
     *
     * @param mixed Output Data
     * @return $this
     */
    public function setData($data)
    {
        $this->output['data'] = $data;
        return $this;
    }

    /**
     * Set $this->output['params']
     *
     * @param array Output Parameters
     * @return $this
     */
    public function setParams(array $params)
    {
        $this->output['params'] = $params;
        return $this;
    }

    /**
     * Merge $this->output['params']
     *
     * @param array Output Parameters
     * @return $this
     */
    public function mergeParams(array $params)
    {
        $this->output['params'] = recuresive_array_merge($this->output['params'], $params);
        return $this;
    }

    /**
     * Get Params
     *
     * @return array $this->output['params']
     */
    public function getParams($paramKey = false)
    {
        return $paramKey ? ($this->output['params'][$paramKey] ?? null) : $this->output['params'];
    }

    public function getParamInput($paramKey = false, $default = false)
    {
        return $paramKey
            ? ($this->output['params']['input'][$paramKey] ?? $default)
            : ($this->output['params']['input'] ?? []);
    }

    public function getParamQuery($paramKey = false, $default = false)
    {
        return $paramKey
            ? ($this->output['params']['query'][$paramKey] ?? $default)
            : ($this->output['params']['query'] ?? []);
    }

    public function getParamRoute($paramKey = false, $default = false)
    {
        return $paramKey
            ? ($this->output['params']['route'][$paramKey] ?? $default)
            : ($this->output['params']['route'] ?? []);
    }

    /**
     * Set $this->output['message'] dan $this->output['message_info']
     *
     * @param string Message
     * @param string type: info, warning, danger
     * @return $this
     */
    public function setMessage($message, $type = self::MESSAGE_TYPE_INFO)
    {
        $this->output['message'] = $message;
        $this->output['message_type'] = $type;
        return $this;
    }

    /**
     * Set Warning Status Output
     *
     * @param string $message
     * @param string $type 'success','warning','info','warning','danger'
     * @param integer $httpCode http response code
     * @param integer $status status code
     * @param mixed $error
     * @param mixed $response
     */
    public function setWarning(
        $message,
        $type = self::MESSAGE_TYPE_WARNING,
        $httpCode = 400,
        $status = false,
        $error = false,
        $response = null
    ) {
        if ($status === false) $status = $httpCode;

        $this->output['http_code'] = $httpCode;
        $this->output['status'] = $status;
        $this->output['message'] = $message;
        $this->output['message_type'] = empty($type) ? self::MESSAGE_TYPE_WARNING : $type;
        $this->output['errors'] =
            $error === true || $error === 1 || $error === false
            ? [true]
            : (empty($error) ? [true] : $error);

        if (!is_null($response)) {
            $this->response =
                $response === true || $response === 1 || $response === false
                ? redirect(url()->previous())->withInput()
                : $response;
        }

        if ($this->isWebCall() && $this->forceOutput != 2) {
            Session::put('alert', [
                'type' => $type,
                'message' => $this->output['message'],
                'errors' => (array) $this->output['errors'],
            ]);
        }
    }

    /**
     * Set Error Output
     *
     * @param string $message
     * @param mixed $error
     * @param integer $httpCode
     * @param boolean|integer $status
     * @param mixed $response
     */
    public function setError(
        $message,
        $error = false,
        $httpCode = 400,
        $status = false,
        $response = null
    ) {
        if ($status === false) $status = $httpCode;

        $this->setWarning($message, self::MESSAGE_TYPE_DANGER, $httpCode, $status, $error, $response);
    }

    /**
     * set alert view
     *
     * @param string $message
     * @param string $type 'warning','info','warning','danger'
     */
    public function setAlert($message, $type = self::MESSAGE_TYPE_INFO)
    {
        $this->setWarning($message, $type, '200');
    }

    /**
     * generate/get default parameter di resource listing, yang akan dipassing juga ke output
     *
     * @param bool $mergeToParam    true jika parameter input lainnya langsung dimasukan ke query dan filter
     *                          false jika dipisah di key terpisah saja (all)
     * @param array $mergeParam     list parameter yg HANYA / TIDAK (tergantung parameter $mergeType) dimerge kan ke query & filter
     * @param bool $mergeType       true jika $mergeParam adalah list parameter yang tidak di merge
     *                              false jika $mergeParam adalah list parameter yang HANYA/ONLY di merge
     *
     * @return array format :
     *  [
     *      all => seluruh parameter input
     *      query => [ --> digunakan untuk menggenerate link url yg menyimpan informasi filter saat ini
     *          limit
     *          offset
     *          *orderBy --> optional jika menyertakan parameter orderBy atau orderType
     *          *orderType --> optional jika menyertakan parameter orderBy atau orderType
     *          *q --> optional jika menyertakan parameter q
     *          *with --> optional jika menyertakan parameter with
     *          *append --> optional jika menyertakan parameter append
     *          *has --> optional jika menyertakan parameter has
     *          *view_import,
     *          *import_id,
     *
     *          ... paramater2 input lainnya jika ada dan $mergeToParam == true
     *      ],
     *      filter => [
     *          *q,
     *          *append,
     *          *with,
     *          *has,
     *          *view_import,
     *          *import_id,
     *          [..] ... paramater2 input lainnya jika ada dan $mergeToParam == true
     *      ],
     *      orderBy => []
     *  ]
     */
    final protected function getListParam(
        bool $mergeToParam = true,
        array $mergeParam = [],
        bool $mergeType = true
    ) {
        $params = [
            'all' => request()->except([
                'limit',
                'offset',
                'orderBy',
                'orderType',
                'q',
                'append',
                'with',
                'has',
                'view_import',
                'import_id',
                'logquery'
            ]),
            'query' => [ //parameter yang dipassing di URL, termasuk juga parameter filter, untuk di passing ke pagination juga
                'limit' => request()->input('limit', 10),
                'offset' => request()->input('offset', 0),
            ],
            'filter' => [], //parameter filter ke method repo listing nya
            'orderBy' => [],
        ];

        //jika menyertakan orderBy
        if (request()->input('orderBy', null) || request()->input('orderType', null)) {
            $params['query']['orderBy'] = request()->input('orderBy', 'id');
            $params['query']['orderType'] = request()->input('orderType', 'ASC');
            $params['orderBy'] = [
                $params['query']['orderBy'],
                $params['query']['orderType']
            ];
        }

        //jika menyertakan query string
        if (request()->input('q', null)) {
            $params['filter']['q'] = $params['query']['q'] = request()->input('q', '');
        }

        //jika menyertakan with
        if (request()->input('with', null)) {
            $params['filter']['with'] = $params['query']['with'] = request()->input('with', []);
        }

        //jika menyertakan with
        if (request()->input('has', null)) {
            $params['filter']['has'] = $params['query']['has'] = request()->input('has', []);
        }

        //jika menyertakan append
        if (request()->input('append', null)) {
            $params['filter']['append'] = $params['query']['append'] = request()->input('append', []);
        }

        //jika parameter dimerge langsung dengan query dan filter
        if (!empty($params['all'])) {
            foreach ($params['all'] as $key => $param) {
                if (
                    empty($mergeParam) ||
                    ($mergeType && !in_array($key, $mergeParam)) ||
                    (!$mergeType && in_array($key, $mergeParam))
                ) {
                    $params['query'][$key] = $param;
                    if (
                        isset($param[0])
                        && in_array(strtoupper($param[0]), ['LIKE', '!=', '<', '<=', '>', '>='])
                    ) {
                        $params['filter'][] = [$key, $param[0], $param[1]];
                    } else {
                        $params['filter'][] = [$key, $param];
                    }
                }
            }
        }

        //detek import
        if (request()->input('view_import', 0) != 0) {
            $params['filter']['view_import'] = request()->input('view_import');
            $params['query']['view_import'] = request()->input('view_import');
            $params['filter']['import_id'] = request()->input('import_id', 0);
            $params['query']['import_id'] = request()->input('import_id', 0);
        }

        return $params;
    }

    /**
     * Otomatisasi `$this->output['params'] = $this->getListParam();`
     *
     * @param boolean $mergeToParam
     * @param array $mergeExcept
     * @return void
     */
    public function buildParams(
        bool $mergeToParam = true,
        array $mergeParam = [],
        bool $mergeType = true
    ) {
        $this->setParams($this->getListParam($mergeToParam, $mergeParam, $mergeType));
    }

    /**
     * cek apakah request dari ifframe atau bukan
     *
     * @return boolean
     */
    // @SuppressWarnings(PHPMD.Superglobals)
    protected function hasReferer()
    {
        return isset($_SERVER['HTTP_REFERER']) ? true : false;
    }

    /**
     * cek apakah request API
     *
     * @return boolean
     */
    protected function isApiCall()
    {
        return request()->wantsJson() ? true : false;
    }

    /**
     * cek apakah request Ajax
     *
     * @return boolean
     */
    protected function isAjaxCall()
    {
        return request()->ajax() ? true : false;
    }

    /**
     * cek apakah request WEB
     *
     * @return boolean
     */
    protected function isWebCall()
    {
        return !(request()->ajax() || request()->wantsJson()) ? true : false;
    }

    /**
     * bypass output responsable menjadi API (JSON) menghiraukan request yg masuk
     */
    protected function forceApiOutput()
    {
        $this->forceOutput = 2;
    }

    /**
     * bypass output responsable menjadi WEB menghiraukan request yg masuk
     */
    protected function forceWebOutput()
    {
        $this->forceOutput = 1;
    }

    /**
     * Set $this->requestPrepareClass
     *
     * @param string $requestPrepareClass RequestPrepare Class
     * @param string $methodeName methodeName Class
     * @return $this
     */
    protected function setRequestClass($requestPrepareClass, $methodeName = 'prepare')
    {
        $this->requestPrepareClass[] = [
            $requestPrepareClass,
            $methodeName
        ];
        return $this;
    }

    /**
     * Initialize auto request preparation
     *
     * @param Request $request              isi dengan $request dari controller
     * @param Boolead $isListRequest        true jika request list, false input general
     * @param False|Array $requestClass     jika ingin setRequestClass langsung
     */
    protected function prepare(&$request, $isListRequest = false, $requestClass = false)
    {
        if ($requestClass)
            $this->setRequestClass($requestClass[0], $requestClass[1]);

        $idx = count($this->requestPrepareClass) == 1 ? 0 : 1;
        $isOnController = $this->requestPrepareClass[$idx][0] == get_class($this);
        if (($isOnController ? $this : (new $this->requestPrepareClass[$idx][0]))->{$this->requestPrepareClass[$idx][1]}(
            $this,
            $request,
            $isListRequest
        ))
            return true;

        return false;
    }

    /**
     * Set $this->responsableClass
     *
     * @param string Responsable Class
     * @return $this
     */
    protected function setResponsableClass($responsableClass)
    {
        $this->responsableClass = $responsableClass;
        return $this;
    }

    /**
     * Return Output/Response
     *
     * @param mixed $response
     *
     * @return \App\Base\responsableClass
     */
    // @SuppressWarnings(PHPMD.BooleanArgumentFlag)
    protected function done($response = false)
    {
        if ($response) {
            $this->response = $response;
        }

        return new $this->responsableClass(
            $this->output,
            $this->response,
            $this->forceOutput,
            $this->isViewVarWraped,
            $this->viewWrapVarName
        );
    }
}
