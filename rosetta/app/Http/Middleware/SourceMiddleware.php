<?php

namespace Rosetta\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Config;

class SourceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)//#719
    {
        if($request->headers)
        {
            if($request->headers->has('RequestSource'))
            {
                Config::set('rosetta.request_source',$request->headers->get('RequestSource'));//This will be ROS. Required for auditing.
            }
            else 
            {
                Config::set('rosetta.request_source','MOBILE_APP');
            }
        }
        return $next($request);
    }
}
