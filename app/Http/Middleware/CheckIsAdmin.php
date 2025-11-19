<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the user is logged in and their group's name is 'admin' or 'IT'
        if (Auth::check() && in_array(Auth::user()->serviceUserRole?->userGroup?->name, ['admin', 'IT'])) {
            return $next($request);
        }

        // If not admin or IT, redirect to the dashboard
        return redirect('/dashboard')->with('error', 'You do not have permission to access this section.');
    }
}
