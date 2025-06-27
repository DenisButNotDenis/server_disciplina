<?php

namespace App\Http\DTOs\LogRequest;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

/**
 * DTO class for representing a single log entry.
 * (Point 10, 14 - returns a full instance)
 */
final readonly class LogRequestResourceDTO extends Data
{
    public function __construct(
        public int $id,
        public string $fullUrl,
        public string $httpMethod,
        public ?string $controllerPath,
        public ?string $controllerMethod,
        public ?array $requestBody,
        public ?array $requestHeaders,
        public ?int $userId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public int $responseStatus,
        public ?array $responseBody,
        public ?array $responseHeaders,
        public Carbon $requestedAt,
        public Carbon $createdAt,
    ) {}

    /**
     * Creates a DTO instance from a LogRequest model object.
     *
     * @param \App\Models\LogRequest $logRequest The LogRequest model.
     * @return self
     */
    public static function fromModel(\App\Models\LogRequest $logRequest): self
    {
        return new self(
            id: $logRequest->id,
            fullUrl: $logRequest->full_url,
            httpMethod: $logRequest->http_method,
            controllerPath: $logRequest->controller_path,
            controllerMethod: $logRequest->controller_method,
            requestBody: $logRequest->request_body,
            requestHeaders: $logRequest->request_headers,
            userId: $logRequest->user_id,
            ipAddress: $logRequest->ip_address,
            userAgent: $logRequest->user_agent,
            responseStatus: $logRequest->response_status,
            responseBody: $logRequest->response_body,
            responseHeaders: $logRequest->response_headers,
            requestedAt: $logRequest->requested_at,
            createdAt: $logRequest->created_at,
        );
    }
}
