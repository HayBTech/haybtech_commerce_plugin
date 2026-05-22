<?php

declare(strict_types=1);

namespace HayBTech\Resources;

use HayBTech\Exceptions\ApiException;
use HayBTech\HayBTechClient;
use HayBTech\HayBTechResponse;

/**
 * Payments resource — mirrors POST/GET /v1/payments.
 *
 * Every method returns the raw API response array. The top-level keys are:
 *   - data     : the transaction object (or array of objects for list())
 *   - pagination: present in list() responses
 */
final class Payments
{
    public function __construct(private readonly HayBTechClient $client) {}

    /**
     * Create a new payment (initiate a transaction).
     *
     * Minimal example (hosted checkout — payer picks provider on our page):
     *   $result = $haybtech->payments->create([
     *       'merchant_ref' => 'CMD-0042',
     *       'amount'       => 25000,
     *   ]);
     *   $result->redirect(); // Boom! Automatic redirection
     *
     * @param  array  $params
     * @return HayBTechResponse
     *
     * @throws ApiException
     */
    public function create(array $params, string $idempotencyKey = ''): HayBTechResponse
    {
        $headers = $idempotencyKey !== '' ? ['Idempotency-Key' => $idempotencyKey] : [];

        return $this->client->post('payments', $params, $headers);
    }

    /**
     * Retrieve a single transaction by its HayBTech ID or your merchant_ref.
     *
     * @return HayBTechResponse
     *
     * @throws ApiException
     */
    public function retrieve(string $id): HayBTechResponse
    {
        return $this->client->get("payments/{$id}");
    }

    /**
     * List transactions for the authenticated merchant.
     *
     * @param  array  $params
     * @return HayBTechResponse
     *
     * @throws ApiException
     */
    public function list(array $params = []): HayBTechResponse
    {
        return $this->client->get('payments', $params);
    }

    /**
     * Pull the latest status from the upstream PSP and reconcile locally.
     *
     * @return HayBTechResponse
     *
     * @throws ApiException
     */
    public function verify(string $id): HayBTechResponse
    {
        return $this->client->post("payments/{$id}/verify");
    }
}
