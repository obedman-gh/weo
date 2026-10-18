<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveUser
{
    public function handle(Request $r, Closure $next)
    {
        if ($r->user() && ! $r->user()->active) {
            auth()->logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();

            return redirect('/login')->withErrors(['email' => 'Akun dinonaktifkan. Hubungi admin.']);
        }
        $response = $next($r);
        if ($r->user()) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        }

        return $response;
    }
}
