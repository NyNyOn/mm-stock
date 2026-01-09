<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Redirect the user to the appropriate dashboard based on their permissions.
     */
    public function index()
    {
        $user = Auth::user();

        if ($user && $user->can('dashboard:view')) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('user.equipment.index');
    }
}
