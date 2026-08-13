<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;

class VerifyCsrfToken extends Middleware
{
    protected $except = [
        'api/broadcasting/auth',
        'admin/pending-requests/certifications/*',
    ];

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param  Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        if ($request->expectsJson() || $request->bearerToken()) {
            return true;
        }

        return parent::inExceptArray($request);
    }
}
