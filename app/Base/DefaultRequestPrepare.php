<?php

namespace App\Base;

class DefaultRequestPrepare
{
    /*
     * process request preparation if needed
     */
    public function prepare(&$controller,&$request,$isListRequest=false) 
    {
        if($isListRequest){
            $controller->setParams([
                'input'=>$request->all(),
                'route'=>$request->route()->parameters
            ]);
        }else{
            $controller->buildParams();
        }
        
        return true;
    }
}
