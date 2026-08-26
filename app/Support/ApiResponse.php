<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * ApiResponse
 *
 * Standard envelope untuk REST API /api/v1/* supaya semua client
 * (Flutter mobile + web) jangka satu bentuk response yang sama.
 *
 * Konvensyen (docs/FLUTTER_PLAN.md §8):
 *   success  → { data: ..., meta: {...} }
 *   error    → { message: ..., errors: {...} }
 *   senarai  → { data: [...], meta: { pagination }, links: {...} }
 */
class ApiResponse
{
    public static function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Balut paginator Laravel kepada envelope pagination §8.2.
     */
    public static function paginated(LengthAwarePaginator $paginator, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => array_merge([
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ], $meta),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ], $status);
    }

    public static function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
