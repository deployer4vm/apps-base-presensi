<?php

namespace App\Http\Middleware;

use Closure;
use hpsynapse\moduser\Services\UserAuth;
use Illuminate\Support\Facades\Auth;
use hpsynapse\moduser\Models\ApiToken;
use App\Base\Traits\ResCacheTrait;

class AcceesConfigCheck
{
    use ResCacheTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->cacheActive = true;
        if (!($config = $this->_getCache('generalconfig', 'accesss'))) {
            $config = [
                'allow_login' => 1,
                'allow_login_exept' => [],
                'allow_login_only' => []
            ];
            $this->_saveCache('generalconfig', 'accesss', $config);
        }

        $isWebReq = true;
        $accessBlocked = false;
        $isLogin = false;

        if (Auth::check()) {
            $isLogin = true;
            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                $isWebReq = false;
                $userId = Auth::user()->user_id;
                $token = Auth::user()->api_token;
            } else {
                $userId = UserAuth::user('id');
                $token = UserAuth::getToken();
            }

            //jika allow login
            if ($config['allow_login']) {
                if ($config['allow_login_exept'] && in_array($userId, $config['allow_login_exept'])) {
                    $accessBlocked = true;
                }
                //jika tidak boleh login
            } else {
                $accessBlocked = true;
                if ($config['allow_login_only'] && in_array($userId, $config['allow_login_only'])) {
                    $accessBlocked = false;
                }
            }
        }


        if ($accessBlocked && $isLogin) {
            if ($isWebReq) {
                if ($isLogin)
                    Auth::logout();
                return redirect()->route('login');
            } else {
                ApiToken::where('api_token', $token)->delete();
                throw new \Illuminate\Auth\AuthenticationException();
            }
        }

        return $next($request);
    }
}
