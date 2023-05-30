<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Base\BaseController;

use App\Facades\PostReference;

use hpsynapse\moduser\Facades\UserAuth;

class PostReferenceController extends BaseController
{
    protected $cacheActive = true;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->forceApiOutput();
    }

    /**
     * GET - /sys/postref/
     * get post reff
     *
     * @param Request $request
     *      form_id
     *      tenant_id *optional
     *      user_id *optional
     *
     * @return Array list data config
     */
    public function getRef(Request $request)
    {
        $this->output['data'] = PostReference::getPostRef(
            $request->input('form_id'),
            $request->input('tenant_id', config('tenant.id', false)),
            $request->input('user_id', UserAuth::user('id'))
        );

        if (!$this->output['data']) {
            $this->setError(PostReference::error());
        }

        return $this->done();
    }
}
