<?php

namespace App\Http\Middleware;

use Closure;
// use Illuminate\Support\Facades\Auth;

/**
 * cek apakah domain tenant harus redirect
 */
class TenantDomainRedirectCheck
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
            // jika redirect maka redirect
            if (config('tenant.domain.status') == 2) {
                return redirect(config('tenant.domain.redirect'));

                // jika bukan di storage dan di tenant manager maka redirect ke domain utama
            } else if (
                (
                    config('tenant.status') != 1 
                    && !config('tenant.isOnTenantManager', false)
                )
                ||
                (
                    !config('tenant.isOnStorageAlltenant', false)
                    && !config('tenant.isOnTenantManager', false)
                    && !config('tenant.isOnGeneralApi', false)
                    && !config('tenant.id')
                )
                ||
                (
                    config('tenant.isOnStorageAlltenant', false)
                    && request()->segments()[0] != 'storage'
                )
            ) {
                return redirect((request()->secure() ? 'https://' : 'http://')
                    . config('AppConfig.system.multitenant.main_domain'));
            }
        }
        return $next($request);
    }
}
