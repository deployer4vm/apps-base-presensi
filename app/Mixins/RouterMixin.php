<?php

namespace App\Mixins;

// use Illuminate\Support\Facades\Route;

/**
 * tambahan fungsi route
 */
class RouterMixin
{

    /**
     * default router setting untuk resource import
     */
    public function importResource()
    {
        return function ($param) {
            $this->get($param['prefix'] . '/import', $param['controller'] . '@import')
                ->name($param['name'] . '.import');
            //form opname, import status & list import
            $this->get($param['prefix'] . '/import-status', $param['controller'] . '@importStatus')
                ->name($param['name'] . '.importStatus');

            //proses / upload import file
            $this->post($param['prefix'] . '/import', $param['controller'] . '@importUpload')
                ->name($param['name'] . '.importUpload');
            //approve / proses data yg sudah di import
            $this->post($param['prefix'] . '/import/confirm', $param['controller'] . '@importApprove')
                ->name($param['name'] . '.importApprove');
            //cancel import file
            $this->delete($param['prefix'] . '/import', $param['controller'] . '@importCancel')
                ->name($param['name'] . '.importCancel');
        };
    }

    /**
     * default router setting untuk resource export
     */
    public function exportResource()
    {
        return function ($param) {
            //get download status
            $this->get($param['prefix'] . '/download', $param['controller'] . '@downloadStatus')
                ->name($param['name'] . '.downloadStatus');
            //generate download
            $this->post($param['prefix'] . '/download', $param['controller'] . '@downloadGenerate')
                ->name($param['name'] . '.downloadGenerate');
        };
    }
}
