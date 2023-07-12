<?php

namespace App\Base;

use App\Base\Traits\ResCacheTrait;
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
    use ResCacheTrait;

    const MESSAGE_TYPE_INFO = "info";
    const MESSAGE_TYPE_WARNING = "warning";
    const MESSAGE_TYPE_DANGER = "danger";

    /**
     * default data parameter untuk Responseable
     *
     * @var array
     */
    protected $output = [
        'status' => 200,
        'message' => '',
        'message_type' => 'info', //khusus warning view (bukan api)
        'data' => null,
        'params' => null,
        'viewdata' => null, //data yang hanya disertakan di web request
        'errors' => null,
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

    //nama class responseable nya
    protected $responsableName = '\App\Base\DefaultResponse';

    //force output menjadi api atau web
    private $forceOutput = 0; //0 auto, 1 WEB, 2 API

    /**
     * Set $this->output['data']
     *
     * @param mixed Output Data
     * @return $this
     */
    protected function setData($data)
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
    protected function setParams($params)
    {
        $this->output['params'] = $params;
        return $this;
    }

    /**
     * Get Params
     *
     * @return array $this->output['params']
     */
    protected function getParams()
    {
        return $this->output['params'];
    }

    /**
     * Set $this->output['message'] dan $this->output['message_info']
     *
     * @param string Message
     * @param string type: info, warning, danger
     * @return $this
     */
    protected function setMessage($message, $type = self::MESSAGE_TYPE_INFO)
    {
        $this->output['message'] = $message;
        $this->output['message_type'] = $type;
        return $this;
    }

    /**
     * Set Warning Status Output
     *
     * @param string $message
     * @param string $type 'warning','info','warning','danger'
     * @param integer $code http response code
     * @param mixed $error
     * @param mixed $response
     */
    protected function setWarning(
        $message,
        $type = self::MESSAGE_TYPE_WARNING,
        $code = 400,
        $error = false,
        $response = null
    ) {
        $this->output['status'] = $code;
        $this->output['message'] = $message;
        $this->output['message_type'] = $type;
        $this->output['errors'] =
            $error === true || $error === 1 || $error === false
                ? [true]
                : $error;

        if (!is_null($response)) {
            $this->response =
                $response === true || $response === 1 || $response === false
                    ? redirect(url()->previous())->withInput()
                    : $response;
        }

        if ($this->isWebCall() && $this->forceOutput != 2) {
            Session::put('alert', [
                'type' => $type,
                'message' => $message,
            ]);
        }
    }

    /**
     * Set Error Output
     *
     * @param string $message
     * @param mixed $error
     * @param integer $code
     * @param mixed $response
     */
    protected function setError(
        $message,
        $error = false,
        $code = 400,
        $response = null
    ) {
        $this->setWarning($message, self::MESSAGE_TYPE_DANGER, $code, $error, $response);
    }

    /**
     * set alert view
     *
     * @param string $message
     * @param string $type 'warning','info','warning','danger'
     */
    protected function setAlert($message, $type = self::MESSAGE_TYPE_INFO)
    {
        $this->setWarning($message, $type, '200');
    }

    /**
     * generate/get default parameter di resource listing, yang akan dipassing juga ke output
     *
     * @param bool $mergeToParam    true jika parameter input lainnya langsung dimasukan ke query dan filter
     *                          false jika dipisah di key terpisah saja (all)
     * @param array $mergeParam     list parameter yg di HANYA / TIDAK (tergantung parameter $mergeType) merge kan ke query & filter
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
        if ($mergeToParam && !empty($params['all'])) {
            foreach ($params['all'] as $key => $param) {
                if (
                    ($mergeType && !in_array($key, $mergeParam)) ||
                    ($mergeType && in_array($key, $mergeParam))
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
     */
    /**
     * Undocumented function
     *
     * @param boolean $mergeParam
     * @param array $mergeExcept
     * @return void
     */
    public function buildParams(bool $mergeParam = true, array $mergeExcept = [])
    {
        $this->setParams($this->getListParam($mergeParam, $mergeExcept));
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
     * Return Output/Response
     *
     * @param mixed $response
     *
     * @return \App\Base\responsableName
     */
    // @SuppressWarnings(PHPMD.BooleanArgumentFlag)
    protected function done($response = false)
    {
        if ($response) {
            $this->response = $response;
        }

        return new $this->responsableName(
            $this->output,
            $this->response,
            $this->forceOutput,
            $this->isViewVarWraped,
            $this->viewWrapVarName
        );
    }

    /*
     * controller level cache
     * -------------------------------------------------------------------------
     */
}
