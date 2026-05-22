<?php

declare(strict_types=1);

namespace HayBTech\Exceptions;

/**
 * Thrown when the HayBTech API returns a non-2xx HTTP response.
 *
 * Inspect $httpStatus and $errorCode to determine how to handle the failure:
 *   - 401 / invalid_api_key   → rotate your secret key
 *   - 402 / payment_rejected  → payment conditions not met (low balance, etc.)
 *   - 409 / idempotency_conflict → reuse a different Idempotency-Key
 *   - 422 / validation_error  → fix your request payload
 *   - 429 / rate_limit        → backoff and retry
 *   - 5xx                     → transient HayBTech error, retry with backoff
 */
class ApiException extends HayBTechException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus,
        private readonly string $errorCode = '',
        private readonly array $rawResponse = [],
    ) {
        parent::__construct($message, $httpStatus);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Full decoded response body from the API.
     *
     * @return array<string, mixed>
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    public function isRetryable(): bool
    {
        return $this->httpStatus >= 500 || $this->httpStatus === 429;
    }
}
