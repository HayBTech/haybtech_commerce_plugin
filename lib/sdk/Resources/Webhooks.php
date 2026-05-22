<?php

declare(strict_types=1);

namespace HayBTech\Resources;

use HayBTech\HayBTechClient;

/**
 * Manage webhook endpoints.
 */
final class Webhooks
{
    public function __construct(private readonly HayBTechClient $client) {}

    /**
     * List all webhook endpoints for the current environment (test/live).
     */
    public function all(): \HayBTech\HayBTechResponse
    {
        return $this->client->get('/webhooks');
    }

    /**
     * Create a new webhook endpoint.
     */
    public function create(array $payload): \HayBTech\HayBTechResponse
    {
        return $this->client->post('/webhooks', $payload);
    }

    /**
     * Reveal the signing secret for an endpoint (requires OTP if LIVE).
     */
    public function reveal(string $id, ?string $otp = null): \HayBTech\HayBTechResponse
    {
        $body = $otp !== null ? ['otp' => $otp] : [];
        return $this->client->post("/webhooks/{$id}/reveal", $body);
    }

    /**
     * Rotate the signing secret for an endpoint (requires OTP if LIVE).
     */
    public function rotate(string $id, ?string $otp = null): \HayBTech\HayBTechResponse
    {
        $body = $otp !== null ? ['otp' => $otp] : [];
        return $this->client->post("/webhooks/{$id}/rotate", $body);
    }

    /**
     * Send a test ping to the endpoint.
     */
    public function test(string $id): \HayBTech\HayBTechResponse
    {
        return $this->client->post("/webhooks/{$id}/test");
    }

    /**
     * Delete a webhook endpoint (requires OTP if LIVE).
     */
    public function delete(string $id, ?string $otp = null): \HayBTech\HayBTechResponse
    {
        $headers = [];
        if ($otp !== null) {
            $headers['X-OTP'] = $otp;
        }
        return $this->client->delete("/webhooks/{$id}", $headers);
    }
}
