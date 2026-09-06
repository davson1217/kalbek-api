<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiTraffic
{
    /**
     * @var array<int, string>
     */
    private array $sensitiveKeys = [
        'authorization',
        'cookie',
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'client_secret',
        'stripe_signature',
        'x-stripe-signature',
        'x-xsrf-token',
        'x-csrf-token',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldLog($request)) {
            return $next($request);
        }

        $startedAt = microtime(true);

        Log::info('api.request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'body' => $this->requestBody($request),
        ]);

        $response = $next($request);

        Log::info('api.response', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'headers' => $this->sanitizeHeaders($response->headers->all()),
            'body' => $this->responseBody($response),
        ]);

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if (! $request->is('api/*')) {
            return false;
        }

        return (bool) config('logging.api_traffic.enabled');
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @return array<string, mixed>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $key => $value) {
            $sanitized[$key] = $this->isSensitive($key) ? '[redacted]' : $value;
        }

        return $sanitized;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestBody(Request $request): array
    {
        if ($request->files->count() > 0) {
            return [
                'input' => $this->sanitizeValue($request->except(array_keys($request->files->all()))),
                'files' => collect($request->allFiles())->map(fn ($file) => [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ])->all(),
            ];
        }

        if ($request->isJson()) {
            return ['json' => $this->sanitizeValue($request->json()->all())];
        }

        return ['input' => $this->sanitizeValue($request->all())];
    }

    /**
     * @return array<string, mixed>|string|null
     */
    private function responseBody(Response $response): array|string|null
    {
        $contentType = (string) $response->headers->get('content-type');

        if (! str_contains($contentType, 'json') && ! str_starts_with($contentType, 'text/')) {
            return '[not logged: non-text response]';
        }

        $content = (string) $response->getContent();
        $limit = (int) config('logging.api_traffic.max_response_body_chars', 4000);

        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->sanitizeValue($decoded);
            }
        }

        return strlen($content) > $limit
            ? substr($content, 0, $limit).'... [truncated]'
            : $content;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitive($key)) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($item);
        }

        return $sanitized;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower(str_replace('-', '_', $key));

        return in_array($normalized, $this->sensitiveKeys, true);
    }
}
