<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class InstallMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // if we are not on the install route
        if ($request->path() != 'install') {

            try {
                $user = User::first();
            } catch (QueryException $e) {

                return redirect()->route('install');

            }

            if (User::first() === null) {
                return redirect()->route('install');
            }
        }

        return $next($request);
    }
}
