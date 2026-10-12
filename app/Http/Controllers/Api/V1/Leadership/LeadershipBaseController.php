<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

abstract class LeadershipBaseController extends Controller
{
    /**
     * Return a standardized success JSON response.
     *
     * @param  array<string, mixed>|null  $meta
     */
    protected function success(
        mixed $data = null,
        string $message = 'Request processed successfully.',
        int $status = 200,
        ?array $meta = null
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    /**
     * Return a paginated success JSON response.
     */
    protected function paginate(
        LengthAwarePaginator $paginator,
        string $message = 'Records fetched successfully.'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Return a standardized error JSON response.
     *
     * @param  array<string, mixed>|null  $errors
     */
    protected function error(
        string $message,
        int $status = 400,
        ?array $errors = null,
        ?string $code = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        return response()->json($payload, $status);
    }
}
