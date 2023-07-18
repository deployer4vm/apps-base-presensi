<?php

namespace App\Services;

use Exception;

use Illuminate\Support\Facades\Log;

use App\Models\SystemCallback as MSystemCallback;
use App\Models\SystemCallbackLog;

use hpsynapse\moduser\Facades\UserAuth;
use hpsynapse\moduser\Models\User;

class SystemCallback
{
    /**
     * @param Array $data
     *      callback_id
     *      tenant_id
     *      system_user_id
     *      callback_url
     *      data            Array
     */
    public function secureCallBack($data)
    {
        if(
            !isset($data['callback_id']) ||
            !isset($data['system_user_id']) ||
            !isset($data['callback_url']) ||
            !isset($data['data'])
        ){
            return false;
        }

        if(config('AppConfig.system.multitenant.active', false) && isset($data['tenant_id'])){
            \App\Facades\Tenant::setActiveTenantById($data['tenant_id']);
        }

        $user = User::where('id',$data['system_user_id'])->where('system_user',1)
            ->whereNotNull('secret_key')->first();
        
        if($user){      
            $callbackData = MSystemCallback::create([
                'tenant_id'=>isset($data['tenant_id'])?$data['tenant_id']:0,
                'system_user_id'=>$data['system_user_id'],
                'callback_id'=>$data['callback_id'],
                'callback_url'=>$data['callback_url'],
                'data'=>$data['data'],
                'last_hit_at' => now()->format('Y-m-d H:i:s'),
                'status'=>1
            ]);     

            $data['data'] = json_encode($data['data']);
            if(empty($data['data']))
                return false;

            $return = $this->sendCallBack($data,$user->toArray(),$callbackData);
            // jika gagal maka update status sebagai gagal
            if($return==false){
                MSystemCallback::where('id',$callbackData->id)->update([
                    'status'=>0
                ]);
            }
            return $return;
        }

        return false;
    }

    private function sendCallBack($data,$user,$callbackData)
    {        
        $client = new \GuzzleHttp\Client();
        
        $header = [
            'X-Client-Key' => $user['username'],
            'Accept' => 'application/json',
        ];

        $formParams = [
            'client_id' => $this->getConsumerKey(),
            'client_secret' => $this->getConsumerSecret()
        ];

        $log = [
            'tenant_id' => $data['callback_id'],
            'system_callback_id' => $callbackData->id,
            'callback_id' => $data['callback_id'],
        ];

        try {
            $res = $client->request(
                'POST',
                $data['callback_url'],
                [
                    'headers' => $header,
                    'body' => UserAuth::encryptCredential($data['data'],$user['secret_key'])
                ]
            );
        } catch (Exception $e) {
            Log::error('System Callback Return Error');
            Log::error($e);
            $responseBody = $e->getResponse()->getBody();
            $responseCode = $e->getResponse()->getStatusCode();
            $responseHeader = $e->getResponse()->getHeaders();
            Log::error($data);

            $this->error = (string) $responseBody;
            $this->error =  '['.$responseCode.'] API ERROR : '.$this->error;
            $return = false;
        }

        // Create Log
        if($responseCode==200){
            $responseBody = $res->getBody()->getContents();
            $responseCode = $res->getStatusCode();
            $responseHeader = $res->getHeaders();
            $log['status'] = 1;
            $return = json_decode($responseBody, true);
        }else{
            $log['status'] = 0;
        }
        $log['response'] = [
            'http_code'=>$responseCode,
            'headers'=>$responseHeader,
            'body' => $responseBody          
        ];
        $this->createLog($log);
        
        return $return;
    }

    private function createLog(array $data)
    {
        return SystemCallbackLog::create($data);
    }
}