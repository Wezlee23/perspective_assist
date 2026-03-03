<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Automatically authenticates a default user for the app.
 * Since this is a NativePHP desktop app, no login is needed.
 */
class AutoAuthenticateUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            $user = User::firstOrCreate(
                ['email' => 'user@petraai.local'],
                [
                    'name' => 'PetraAI User',
                    'password' => bcrypt('petraai-local'),
                ]
            );

            Auth::login($user);
        }

        return $next($request);
    }
}
