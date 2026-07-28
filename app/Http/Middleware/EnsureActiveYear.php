<?php

namespace App\Http\Middleware;

use App\Services\ActiveYear;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveYear
{
    public function __construct(private ActiveYear $activeYear) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('activeYear', $this->activeYear->current());

        return $next($request);
    }
}
