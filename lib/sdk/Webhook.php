<?php

declare(strict_types=1);

namespace HayBTech;

use HayBTech\Exceptions\SignatureException;

/**
 * Verifies HayBTech webhook signatures and constructs typed event objects.
 *
 * HayBTech signs every outbound webhook with HMAC-SHA256 using the endpoint's
 * secret. The signature header format is:
 *
 *   X-Haybtech-Signature: t=<unix_timestamp>,v1=<hex_hmac>
 *
 * The signed string is: "<timestamp>.<raw_json_body>"
 *
 * Usage in a webhook controller:
 *
 *   $payload   = file_get_contents('php://input');
 *   $signature = $_SERVER['HTTP_X_HAYBTECH_SIGNATURE'] ?? '';
 *   $secret    = 'whsec_...';
 *
 *   try {
 *       $event = Webhook::constructEvent($payload, $signature, $secret);
 *   } catch (SignatureException $e) {
 *       http_response_code(400);
 *       exit;
 *   }
 *
 *   if ($event['event'] === 'payment.success') {
 *       $merchantRef = $event['data']['merchant_ref'];
 *       // mark your order as paid
 *   }
 *
 *   http_response_code(200);
 */
final class Webhook
{
    /** Maximum age of a webhook before it is rejected as a replay. */
    private const TOLERANCE_SECONDS = 300;

    /** Maximum allowed size for a webhook payload (prevent memory exhaustion). */
    private const MAX_PAYLOAD_SIZE = 1048576; // 1 MB

    /**
     * Verify the signature and return the decoded event payload.
     *
     * @return array<string, mixed>
     *
     * @throws SignatureException  on invalid or expired signature
     */
    public static function constructEvent(
        string $payload,
        string $signatureHeader,
        string $secret,
        int $tolerance = self::TOLERANCE_SECONDS,
    ): array {
        if (strlen($payload) > self::MAX_PAYLOAD_SIZE) {
            throw new SignatureException('Le payload du webhook est trop volumineux (max 1Mo).');
        }

        self::verifySignature($payload, $signatureHeader, $secret, $tolerance);

        /** @var array<string, mixed>|null $event */
        $event = json_decode($payload, true);

        if (! is_array($event)) {
            throw new SignatureException(
                'Le payload webhook est du JSON invalide.'
            );
        }

        return $event;
    }

    /**
     * Verify the HMAC signature without decoding the payload.
     * Use this when you want to decode the payload yourself.
     *
     * @throws SignatureException
     */
    public static function verifySignature(
        string $payload,
        string $signatureHeader,
        string $secret,
        int $tolerance = self::TOLERANCE_SECONDS,
    ): void {
        ['timestamp' => $timestamp, 'v1' => $receivedHmac] = self::parseHeader($signatureHeader);

        // Anti-replay: reject events older than $tolerance seconds.
        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            throw new SignatureException(
                "Webhook rejeté : timestamp trop ancien ({$timestamp}). "
                .'Vérifiez l\'horloge système de votre serveur ou augmentez $tolerance.'
            );
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        // Constant-time comparison prevents timing-based signature forgery.
        if (! hash_equals($expected, $receivedHmac)) {
            throw new SignatureException(
                'Signature webhook invalide. '
                .'Vérifiez que vous utilisez le bon webhook_secret pour cet endpoint.'
            );
        }
    }

    /**
     * @return array{timestamp: int, v1: string}
     *
     * @throws SignatureException
     */
    private static function parseHeader(string $header): array
    {
        $parts = [];
        foreach (explode(',', $header) as $chunk) {
            $pair = explode('=', trim($chunk), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        if (! isset($parts['t'], $parts['v1'])) {
            throw new SignatureException(
                "Header de signature malformé : '{$header}'. "
                .'Format attendu : t=<timestamp>,v1=<hmac_hex>'
            );
        }

        return [
            'timestamp' => (int) $parts['t'],
            'v1'        => (string) $parts['v1'],
        ];
    }
}
