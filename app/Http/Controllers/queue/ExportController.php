<?php

namespace App\Http\Controllers\queue;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Base\BaseController;
use App\Facades\Web;
use App\Facades\Trans;
use App\Facades\Export;
use App\Facades\Tenant;

use App\Models\Job;
use hpsynapse\moduser\Models\User;

class ExportController extends BaseController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function listQueue(Request $request)
    {
        $tmpUser = [];
        // -----------------------
        $this->output['data']['jobs'] = Job::where('payload', 'LIKE', '%\\\\Export\\"%')
            ->orWhere('payload', 'LIKE', '%\\\\ExportSpout\\"%')
            ->orderBy('attempts', 'DESC')
            ->orderBy('queue', 'ASC')
            ->get()
            ->append(['formated_payload']);
        $newJobs = [];
        $i = 0;
        foreach ($this->output['data']['jobs'] as $v) {
            $newJobs[$i] = $v->toArray();
            $userId = explode('.', $v->formated_payload['data']['command']['cacheKey']);
            $userId = $userId[count($userId) - 1];
            if (!isset($tmpUser[$userId]))
                $tmpUser[$userId] = User::where('id', $userId)->first();

            $newJobs[$i]['user'] = $tmpUser[$userId];
            $newJobs[$i]['export'] = Export::getExport($v->formated_payload['data']['command']['cacheKey']);
            $i++;
        }
        $this->output['data']['jobs'] = $newJobs;

        //-------------------------------

        // set data2 khusus jika bukan API
        if ($this->isWebCall()) {
            $this->response = 'system.queue.list';
        }
        return $this->done();
    }

    public function cancelQueue(Request $request)
    {
        if ($export = Export::getExport($request->route('cacheKey'))) {
            Export::cancelExport($export['cacheKey']);
            $this->output['message'] = 'Cancel Success';
        } else {
            $this->setError('Detail Export <b>' . $request->route('cacheKey') . '</b> tidak ditemukan !');
        }
        $this->response = redirect()->route('system.queue.export.list');
        return $this->done();
    }

    public function historyQueue(Request $request)
    {
        $this->output['params'] = $this->getListParam();
        $this->output['data']['list'] = Export::listExport(
            $this->output['params']['filter'],
            $this->output['params']['orderBy']
        );

        $tmpUser = [];
        $tmpList = [];
        foreach ($this->output['data']['list'] as $k =>  $v) {
            if(!Tenant::dbExists($v['tenant_id']))
                continue;
            $userId = $v['user_id'];
            if (!isset($tmpUser[$v['tenant_id']][$userId])) {
                $user = (new User);
                $user->setTenantId($v['tenant_id']);
                $tmpUser[$v['tenant_id']][$userId] = $user->where('id', $userId)->first();
            }
            $this->output['data']['list'][$k]['user'] = $tmpUser[$v['tenant_id']][$userId];
            $this->output['data']['list'][$k]['tenant'] = Tenant::getTenant($v['tenant_id']);
            $tmpList[] = $this->output['data']['list'][$k];
        }
        $this->output['data']['list'] = $tmpList;

        // set data2 khusus jika bukan API
        if ($this->isWebCall()) {
            $this->response = 'system.queue.history';
        }
        return $this->done();
    }

    public function deleteQueue(Request $request)
    {
        if ($export = Export::getExport($request->route('cacheKey'))) {
            Export::deleteExport($export['cacheKey']);
            $this->output['message'] = 'Delete Success';
        } else {
            $this->setError('Detail Export <b>' . $request->route('cacheKey') . '</b> tidak ditemukan !');
        }
        $this->response = redirect()->route('system.queue.export.history');
        return $this->done();
    }

    public function detailQueue(Request $request)
    {
        $this->output['data']['isHistory'] = $request->input('isHistory', false);
        $this->output['data']['data'] = Export::getExport($request->route('cacheKey'));
        if ($this->output['data']['data']) {
            $userId = explode('.', $this->output['data']['data']['cacheKey']);
            $this->output['data']['data']['user'] = User::where('id', $userId[count($userId) - 1])->first();
            $this->output['data']['data']['jobs']
                = Job::where('payload', 'LIKE', '%\"' . $this->output['data']['data']['cacheKey'] . '\\\\\"%')
                    ->get()
                    ->append(['formated_payload'])
                    ->toArray();
        }

        // set data2 khusus jika bukan API
        if ($this->isWebCall()) {

            if (!$this->output['data']['data']) {
                $this->setError('Detail Export <b>' . $request->route('cacheKey') . '</b> tidak ditemukan !');
                $this->response = redirect()->route('system.queue.export.list');
                return $this->done();
            }

            $this->response = 'system.queue.detail';
        }
        return $this->done();
    }
}
