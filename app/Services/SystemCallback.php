<?php

namespace App\Services;

use Exception;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use App\Models\SystemCallback as MSystemCallback;
use App\Models\SystemCallbackLog;

use hpsynapse\moduser\Facades\UserAuth;
use hpsynapse\moduser\Models\User;

class SystemCallback
{
    protected $error = '';
    
    public function error()
    {
        return $this->error;
    }
    
    public function listActiveCallBack()
    {        
        $listCallback = MSystemCallback::where('status',0)->where(function($m){
            $m->whereRaw('hit_count < max_hit_retry')->orWhere('max_hit_retry',0);
        })->where('last_hit_at','<=',now()->subMinute(5)->format('Y-m-d H:i:s'));

        if($listCallback->count()>=1){
            return $listCallback->toArray();
        }

        return [];
    }

    public function retrySecureCallBack($callbackId)
    {
        $callback = MSystemCallback::where('id',$callbackId)->first();
        if(!$callback){
            $this->error = 'Callback tidak ditemukan.';
            return false;
        }

        return $this->secureCallBack([
            'id'=>$callback->id,
            'callback_id'=>$callback->callback_id,
            'tenant_id'=>$callback->tenant_id,
            'system_user_id'=>$callback->system_user_id,
            'callback_url'=>$callback->callback_url,
            'data'=>$callback->data,
        ]);
    }
    
    /**
     * create new callback
     * 
     * @param Array $data
     *      id                  *optional, jika re-callback yg sebelumnya gagal
     *      max_hit_retry       *optional, jumlah maksimal retry, def 3
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
            $this->error = 'Parameter tidak lengkap.';
            Log::info([
                'secureCallBack Fail : '.$this->error,
                $data
            ]);
            return false;
        }

        if(config('AppConfig.system.multitenant.active', false) && isset($data['tenant_id'])){
            \App\Facades\Tenant::setActiveTenantById($data['tenant_id']);
        }

        $user = User::where('id',$data['system_user_id'])->where('system_user',1)
            ->whereNotNull('secret_key')->first();
        
        if(!array_key_exists('max_hit_retry',$data))
            $data['max_hit_retry'] = 3;        

        if($user){      
            $callbackData = [
                'tenant_id'=>isset($data['tenant_id'])?$data['tenant_id']:0,
                'system_user_id'=>$data['system_user_id'],
                'callback_id'=>$data['callback_id'],
                'last_hit_at' => now()->format('Y-m-d H:i:s'),
            ];

            // jika tidak menyertakan id maka ini callback baru, 
            // jika menyertakan maka callback ulang
            if(empty($data['id'])){                
                $createData = true;   
            }else{
                MSystemCallback::where('id',$data['id'])->update($callbackData);     
                // jika data callback tidak ada kemungkinan update gagal, maka create ulang
                if(($callbackData = MSystemCallback::where('id',$data['id'])->first())==false){
                    $createData = true;
                    unset($data['id']);
                }
            }

            // jika perlu create data baru
            if($createData){
                // jika callback baru maka isi lengkap datanya
                $callbackData['callback_url'] = $data['callback_url'];
                $callbackData['data'] = $data['data'];
                $callbackData['status'] = 1;
                $callbackData['hit_count'] = 0;
                $callbackData['max_hit_retry'] = $data['max_hit_retry'];

                $callbackData = MSystemCallback::create($callbackData); 
            }else{
                // jika sudah mencapai max hit maka tolak
                if( $callbackData['hit_count'] >= $callbackData->max_hit_retry ){
                    $this->error = 'Callback sudah mencapai batas maksimal hit';
                    return false;
                }
            }

            $tmpData = $data['data'];
            $data['data'] = json_encode($data['data'],JSON_PRESERVE_ZERO_FRACTION);
            
            if(empty($data['data'])){
                $this->error = 'Encode data failed.';
                Log::info([
                    'secureCallBack Fail : '.$this->error,
                    $tmpData,
                    $data['data']
                ]);
                return false;
            }

            

            $return = $this->sendCallBack($data,$user->toArray(),$callbackData);
            MSystemCallback::where('id',$callbackData->id)->update([
                'status'=>$return?1:0,//update status gagal tidak nya
                'hit_count'=>DB::raw('hit_count + 1')
            ]);

            $this->error = '';
            return MSystemCallback::with(['log'])->where('id',$callbackData->id)->first()->toArray();
        }

        $this->error = 'User System Not Found.';
        Log::info([
            'secureCallBack Fail : '.$this->error,
            $data['system_user_id'],
            $user
        ]);
        return false;
    }

    private function sendCallBack($data,$user,$callbackData)
    {   
        $client = new \GuzzleHttp\Client();
        
        $header = [
            'X-Client-Key' => $user['username'],
            'Accept' => 'application/json',
        ];

        $log = [
            'tenant_id' => $data['callback_id'],
            'system_callback_id' => $callbackData->id,
            'callback_id' => $data['callback_id'],
            'callback_url' => $data['callback_url'],
            'callback_data' => $data['data'],
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
            $responseCode = $res->getStatusCode();
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