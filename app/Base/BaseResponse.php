<?php

namespace App\Base;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Session;

abstract class BaseResponse implements Responsable
{
    protected $data;
    protected $response;
    protected $forceOutput;
    protected $isViewVarWraped = false; //apakah seluruh variable diwrap/grupping ke variable $viewWrapVarName
    protected $viewWrapVarName; //nama variable wrap/grouping

    /**
     * Constructor
     *
     * @param array $output autput dari controller
     * @param mixed $response
     *      string jika nama view
     *      instance redirect() jika redirect
     * @param int $forceOutput 0 auto, 1 force web, 2 force api
     * @param bool $isViewVarWraped Jika akan menggunakan variable lain untuk di-passing data ke view selain variable $data
     * @param string $viewWrapVarName Nama variable yang digunakan untuk passing data
     */
    public function __construct(
        $output = false,
        $response = 'list',
        $forceOutput = 0,
        $isViewVarWraped = false,
        $viewWrapVarName = 'data'
    ) {
        $this->output = $output;
        $this->response = $response;
        $this->forceOutput = $forceOutput;
        $this->viewWrapVarName = $viewWrapVarName;
        $this->isViewVarWraped = $isViewVarWraped;
    }

    /**
     * prepare all data
     */
    abstract protected function prepare();

    /**
     * Override from parent
     * Get Response from Request
     *
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function toResponse($request)
    {
        return $this->isApiCall($request) ? $this->apiResponse() : $this->viewResponse();
    }

    /**
     * Detect apakah request untuk output ke API atau View
     *
     * @param \Illuminate\Http\Request $request
     * @return bool
     */
    protected function isApiCall($request)
    {
        if ($this->forceOutput) {
            return $this->forceOutput == 2 ? true : false;
        }

        if ($request->wantsJson()) {
            return true;
        }

        return false;
    }

    /**
     * cek apakah request API atau WEB
     *
     * @param \Illuminate\Http\Request $request
     * @return boolean
     */
    protected function isAjaxCall($request)
    {
        return $request->ajax() ? true : false;
    }

    /**
     * API REQUEST
     * =========================================================================
     */
    /**
     * Prepare API Output
     *
     * @return void
     */
    public function prepareApi()
    {
        $outputParam = [
            'http_code' => 200,
            'status' => 200,
            'data' => [],
            'params' => [],
            'message' => '',
            'errors' => null
        ];
        //delete semua data selain data khusus api
        foreach ($outputParam as $key => $value) {
            if (isset($this->output[$key])) {
                $data[$key] = $this->output[$key];
            } else {
                $data[$key] = $value;
            }
        }

        //jika menyertakan data tipe listing
        // if(isset($this->output['listdata'])&&is_array($this->output['listdata'])){
        //     $data['data'] = $this->output['listdata'];
        // }

        //jika error maka kosongkan data
        if (!is_null($data['errors'])) $data['data'] = null;

        $this->output = $data;
    }

    /**
     * Response output as JSON for API Request
     *
     * @return void
     */
    private function apiResponse()
    {
        $this->prepareApi();
        $this->prepare();
        $tmpHttpCode = $this->output['http_code'];
        unset($this->output['http_code']);
        return response()->json($this->output, $tmpHttpCode);
    }

    /**
     * WEB REQUEST
     * =========================================================================
     */
    /**
     * Prepare View Output
     *
     * @return void
     */
    public function prepareView()
    {
        $this->alert = false;
        $this->errors = false;
        $this->with = false;
        $this->viewdata = false;

        $dataTmp = $this->output['data'];
        $dataTmp['params'] = $this->output['params'] ?? [];

        if (isset($this->output['message']) && $this->output['message']) {
            $this->alert = [
                'type' => $this->output['message_type'],
                'message' => $this->output['message']
            ];
        }

        if (
            isset($this->output['errors'])
            && $this->output['errors'] != null
            && $this->output['errors'] != [true]
        ) {
            $this->errors = $this->output['errors'];
        }

        //jika menggroupkan seluruh variable di data ke varible terntentu
        if ($this->isViewVarWraped) {
            $dataTmp[$this->viewWrapVarName] = $this->output['data'];
        }

        //jika menyertakan data tambahan untuk view
        if (isset($this->output['viewdata']) && is_array($this->output['viewdata'])) {
            $dataTmp = array_merge($dataTmp, $this->output['viewdata']);
            $this->viewdata = $this->output['viewdata'];
        }

        if (!$dataTmp) $dataTmp = [];
        $this->output = $dataTmp;
    }

    /**
     * Response output as view/redirection
     *
     * @return mixed View or Redirect response
     */
    private function viewResponse()
    {
        $this->prepareView();
        $this->prepare();

        //jika string berarti view
        if (is_string($this->response)) {
            $this->response = view($this->response, $this->output);
        } else {
            //tambahkan get parameter jika menyertakan viewdata
            if ($this->viewdata) {
                $redirectUrl = $this->_viewResponseProccParam(
                    $this->response->getTargetUrl(),
                    $this->viewdata
                );
                $this->response = $this->response->setTargetUrl($redirectUrl);
            }
        }
        if ($this->alert) Session::put('alert', $this->alert);

        if ($this->errors) $this->response = $this->response->withErrors($this->errors);

        return $this->response;
    }

    /**
     * Process Redirection URL
     *
     * @param string $redirectUrl Base URL
     * @param array $addQuery Additional Query String
     *
     * @return string URL
     */
    private function _viewResponseProccParam($redirectUrl, $addQuery)
    {
        $resultUrl = \parse_url($redirectUrl);
        $addQuery = http_build_query($addQuery);
        if (isset($resultUrl['query'])) {
            $resultUrl['query'] = $resultUrl['query'] . '&' . $addQuery;
        } else {
            $resultUrl['query'] = $addQuery;
        }
        $resultUrl = $resultUrl['scheme'] . '://'
            . $resultUrl['host']
            . ($resultUrl['path'] ?? '')
            . (isset($resultUrl['query']) ? '?' . $resultUrl['query'] : '');
        return $resultUrl;
    }
}
