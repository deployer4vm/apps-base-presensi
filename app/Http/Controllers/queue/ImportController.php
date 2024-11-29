<?php

namespace App\Http\Controllers\queue;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Base\BaseController;

use App\Facades\Web;
use App\Facades\Trans;
use App\Facades\Import;
use App\Facades\Tenant;

use App\Models\Job;

use hpsynapse\moduser\Models\User;

class ImportController extends BaseController
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
        $this->output['data']['jobs'] = Job::where('payload', 'LIKE', '%\\\\Import\\"%')
            ->orWhere('payload', 'LIKE', '%\\\\Import\\"%')
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
            $newJobs[$i]['import'] = Import::getImport($v->formated_payload['data']['command']['cacheKey']);
            $i++;
        }
        $this->output['data']['jobs'] = $newJobs;

        //-------------------------------

        // set data2 khusus jika bukan API
        if ($this->isWebCall()) {
            $this->response = 'system.queue.importList';
        }
        return $this->done();
    }

    public function cancelQueue(Request $request)
    {
        if ($import = Import::getImport($request->route('cacheKey'))) {
            Import::cancelImport($import['cacheKey']);
            $this->output['message'] = 'Cancel Success';
        } else {
            $this->setError('Detail import <b>' . $request->route('cacheKey') . '</b> tidak ditemukan !');
        }
        $this->response = redirect()->route('system.queue.import.list');
        return $this->done();
    }

    public function historyQueue(Request $request)
    {
        $this->output['params'] = $this->getListParam();
        $this->output['data']['list'] = Import::listImport(
            $this->output['params']['filter'],
            $this->output['params']['orderBy']
        );

        $tmpUser = [];
        foreach ($this->output['data']['list'] as $k => $v) {
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
        }

        // set data2 khusus jika bukan API
        if ($this->isWebCall()) {
            $this->response = 'system.queue.importHistory';
        }
        return $this->done();
    }

    public function deleteQueue(Request $request)
    {
        if ($import = Import::getImport($request->route('cacheKey'))) {
            Import::deleteImport($import['cacheKey']);
            $this->output['message'] = 'Delete Success';
        } else {
            $this->setError('Detail import <b>' . $request->route('cacheKey') . '</b> tidak ditemukan !');
        }
        $this->response = redirect()->route('system.queue.import.history');
        return $this->done();
    }

    public function detailQueue(Request $request)
    {
        $this->output['data']['isHistory'] = $request->input('isHistory', false);
        $this->output['data']['data'] = Import::getImport($request->route('cacheKey'));
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
                $this->setError('Detail import <b>' . $request->route('cacheKey') . '</b> tidak ditemukan !');
                $this->response = redirect()->route('system.queue.import.list');
                return $this->done();
            }

            $this->response = 'system.queue.importDetail';
        }
        return $this->done();
    }
}
