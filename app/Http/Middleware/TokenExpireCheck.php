<?php

namespace App\Http\Middleware;

use Closure;
use hpsynapse\moduser\Facades\UserAuth;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

use hpsynapse\moduser\Models\ApiToken;


class TokenExpireCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            $isWebReq = true;
            if (($request->expectsJson() || $request->wantsJson() || $request->ajax())) {
                $isWebReq = false;
                $updatedAt = Auth::user()->updated_at;
                $token = Auth::user()->api_token;
                // jika token permanent maka tidak perlu dicek
                if(Auth::user()->is_permanent){
                    ApiToken::where('api_token', $token)->update(['updated_at' => now()]);
                    return $next($request);
                }
            } else {
                $updatedAt = UserAuth::getSessionLastUpdate();
                $token = UserAuth::getToken();
            }

            $lastAccess = (new Carbon($updatedAt))->addMinute(config('session.lifetime'));

            if ($lastAccess->lessThan(now())) {
                ApiToken::where('api_token', $token)->delete();
                if ($isWebReq) {
                    Auth::logout();
                } else {
                    throw new \Illuminate\Auth\AuthenticationException();
                }
                return;
            } else {
                ApiToken::where('api_token', $token)->update(['updated_at' => now()]);
            }
        }
        return $next($request);
    }
}
