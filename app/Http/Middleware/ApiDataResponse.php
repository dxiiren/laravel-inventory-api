<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApiDataResponse
{
    public function handle(Request $request, Closure $next): RedirectResponse|Response|JsonResponse
    {
        $response = $next($request);

        if (! ($response instanceof JsonResponse)) {
            return $response;
        }

        $status = $response->getStatusCode();
        $original = $response->getData(true);

        return response()->json([
            'code' => $status,
            'message' => data_get($original, 'message', 'Success'),
            'data' => in_array($status, [200, 201]) ? $original : null,
            'errors' => $status === 422 ? data_get($original, 'errors') : null,
        ], $status);
    }
}
