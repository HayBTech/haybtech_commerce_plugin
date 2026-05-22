<?php

declare(strict_types=1);

namespace HayBTech;

use ArrayAccess;
use JsonSerializable;

/**
 * Enhanced response wrapper for the HayBTech API.
 * 
 * Provides easy access to data and helpful methods like redirect().
 */
final class HayBTechResponse implements ArrayAccess, JsonSerializable
{
    public function __construct(
        private readonly array $data,
        private readonly int $status = 200,
    ) {}

    /**
     * Get a value from the response data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Return the raw response array.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Automatically redirect the user to the payment_url if present.
     * 
     * Usage: HayBTech::payments()->create([...])->redirect();
     */
    public function redirect(): void
    {
        $url = $this->get('data')['payment_url'] ?? null;

        if ($url) {
            header('Location: ' . $url);
            exit;
        }
    }

    /**
     * Check if the request was successful (alias for status code check).
     */
    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    // --- ArrayAccess Implementation ---

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Immutable
    }

    public function offsetUnset(mixed $offset): void
    {
        // Immutable
    }

    // --- JsonSerializable Implementation ---

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function __toString(): string
    {
        return (string) json_encode($this->data);
    }
}
