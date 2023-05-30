<?php

namespace App\Http\Middleware;

use Closure;
// use Illuminate\Support\Facades\Auth;
use App\Facades\Tenant;

class IframeLoadCheck
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
        // jika ada parameter onIframeConfig maka langsung hapus agar tidak
        // masuk request parameter
        if($request->input('onIframeConfig'))
            unset($request['onIframeConfig']);

        return $next($request);
    }
}
