<?php

namespace App\Base;

class DefaultResponse extends BaseResponse
{
    /*
     * process data preparation or view modification if needed
     */
    public function prepare(&$request) {
        // hapus sisa-sisa auto request prepare jika ada
        if(isset($this->output['params']['route']))
            unset($this->output['params']['route']);
        if(isset($this->output['params']['input']))
            unset($this->output['params']['input']);
        if(isset($this->output['params']['query']))
            unset($this->output['params']['query']);
    }
}
