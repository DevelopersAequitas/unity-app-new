<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\UtcDateTime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global CRUD Timezone Normalization Middleware.
 *
 * Ensures that all incoming datetime fields on Create (POST) and Edit (PUT / PATCH)
 * API requests are converted into canonical UTC before reaching controllers and models,
 * while preserving pure calendar date fields (YYYY-MM-DD) and attaching the resolved
 * client timezone on the request for read/display responses.
 */
class NormalizeCrudTimestampsToUtc
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientTimezone = UtcDateTime::resolveRequestTimezone($request, null, 'UTC');
        $request->attributes->set('client_timezone', $clientTimezone);

        if (in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH'], true)) {
            $input = $request->all();

            if (! empty($input)) {
                $normalized = UtcDateTime::normalizePayloadToUtc($input, $clientTimezone);
                $request->merge($normalized);
            }
        }

        return $next($request);
    }
}
