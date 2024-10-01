<?php

namespace App\Http\Middleware;

use Closure;
// use Illuminate\Support\Facades\Auth;
use App\Facades\Tenant;

/**
 * SUDAH TIDAK DIGUNAKAN
 * dipindah ke TranslationServiceProvider
 */
class AppGroupCheck
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
        if (config('AppConfig.system.multitenant.active')) {
            $appGroup = $request->header('Group-App', false)
                ?: $request->route('group_app', false)
                ?: $request->input('group_app', false);

            if ($appGroup)
                Tenant::setActiveTenantByGroup($appGroup);
        }
        return $next($request);
    }
}
