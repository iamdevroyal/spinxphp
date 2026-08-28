<?php

declare(strict_types=1);

namespace Spinx\Http\Middleware;

use Throwable;
use Spinx\Http\Exceptions\HttpException;
use Spinx\Http\Exceptions\ProblemDetails;
use Spinx\Http\Request;
use Spinx\Http\Response;
use Spinx\Log\Log;
use Spinx\Support\Config;
use Spinx\Validation\ValidationException;

/**
 * ApiErrorHandlerMiddleware — RFC 7807 Error Interceptor for API Requests.
 *
 * Catches any unhandled Throwable thrown during the execution of an API route,
 * converting it into a standardized RFC 7807 problem details JSON response.
 *
 * Registered globally as the 'api.errors' middleware alias.
 */
final class ApiErrorHandlerMiddleware
{
    /**
     * @param mixed $request
     * @param \Closure(mixed): mixed $next
     */
    public function handle(mixed $request, \Closure $next): mixed
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    private function handleException(Throwable $e, mixed $request): Response
    {
        $requestId = bin2hex(random_bytes(8));
        $instance  = is_object($request) && method_exists($request, 'path') ? $request->path() : ($_SERVER['REQUEST_URI'] ?? '/');
        $debug     = (bool) (Config::get('app.debug', false) || env('APP_DEBUG', false));

        // 1. ValidationException (422)
        if ($e instanceof ValidationException) {
            $problem = ProblemDetails::validation(
                errors: method_exists($e, 'getErrors') ? $e->getErrors() : (property_exists($e, 'errors') ? (array) $e->errors : []),
                detail: $e->getMessage() ?: 'The given data was invalid.',
            );
            return $this->formatResponse($problem, $instance, $requestId);
        }

        // 2. Spinx HttpException Hierarchy (400, 401, 403, 404, 429, etc.)
        if ($e instanceof HttpException) {
            $problem = $e->toProblemDetails();
            return $this->formatResponse($problem, $instance, $requestId);
        }

        // 3. Log unexpected server errors
        try {
            Log::error($e->getMessage(), [
                'exception'  => get_class($e),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'request_id' => $requestId,
                'instance'   => $instance,
            ]);
        } catch (Throwable) {
            // Logging failure fallback (never crash the error handler)
        }

        // 4. Generic Internal Server Error (500)
        $problem = ProblemDetails::serverError(
            detail: $debug ? $e->getMessage() : 'An unexpected internal server error occurred.'
        );

        if ($debug) {
            $problem->withExtension('debug', [
                'class' => get_class($e),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
            ]);
        }

        return $this->formatResponse($problem, $instance, $requestId);
    }

    private function formatResponse(ProblemDetails $problem, string $instance, string $requestId): Response
    {
        $problem->withInstance($instance);
        $problem->withRequestId($requestId);

        $body = json_encode($problem->toArray(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        header('Content-Type: application/problem+json', replace: true, response_code: $problem->status);
        echo $body;

        return Response::json($problem->toArray(), $problem->status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
