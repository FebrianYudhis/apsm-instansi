<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Activitylog\CauserResolver;
use Symfony\Component\HttpFoundation\Response;

class SetActivityLogCauser
{
    public function __construct(private CauserResolver $causerResolver) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->causerResolver->setCauser($request->user());

        return $next($request);
    }
}
