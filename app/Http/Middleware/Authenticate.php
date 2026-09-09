<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

use Closure;

class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        if ($request->header('Authorization', false) == false){
            if($request->header('Syn-Api-Token', false) != false) {
                $request->headers->add(['Authorization' => 'Bearer ' . $request->header('Syn-Api-Token', '')]);
            }
        }

        $this->authenticate($request, $guards);

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (!($request->expectsJson() || $request->wantsJson() || $request->ajax())) {
            return route('auth.login');
        }
    }
}
