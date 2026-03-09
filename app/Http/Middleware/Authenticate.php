<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        // API requests must return 401 JSON instead of redirecting to a web login route.
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        return Route::has('login') ? route('login') : null;
    }
}
