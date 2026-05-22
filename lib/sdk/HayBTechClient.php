<?php

declare(strict_types=1);

namespace HayBTech;

use HayBTech\Exceptions\ApiException;
use HayBTech\Exceptions\HayBTechException;
use HayBTech\Resources\Payments;

/**
 * Main HTTP client for the HayBTech API.
 */
final class HayBTechClient
{
    public readonly Payments      $payments;
    public readonly Resources\Webhooks $webhooks;

    /** @var array<string, string> */
    private array $defaultHeaders;

    /**
     * @param  string  $secretKey  Your merchant secret key
     * @param  array{base_url?: string, timeout?: int, connect_timeout?: int} $options
     */
    public function __construct(
        private readonly string $secretKey,
        private readonly array $options = [],
    ) {
        if (! str_starts_with($secretKey, 'sk_')) {
            throw new HayBTechException(
                "Clé secrète invalide. Le format attendu est 'sk_live_…' (production) ou 'sk_test_…' (test)."
            );
        }

        if (strpbrk($secretKey, "\r\n") !== false) {
            throw new HayBTechException("Clé secrète invalide : contient des caractères interdits.");
        }

        $this->defaultHeaders = [
            'Authorization' => 'Bearer '.$secretKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'X-Request-ID'  => (string) bin2hex(random_bytes(16)),
            'User-Agent'    => 'HayBTech-PHP-SDK/1.0 PHP/'.PHP_VERSION,
        ];

        $this->payments      = new Payments($this);
        $this->webhooks      = new Resources\Webhooks($this);
    }

    /**
     * Prevents the secret key from being leaked when using var_dump() or print_r().
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'options' => $this->options,
            'secretKey' => 'sk_...'.substr($this->secretKey, -4),
            'isTestMode' => $this->isTestMode(),
        ];
    }

    /**
     * Prevents the client (and its secret key) from being serialized.
     *
     * @throws HayBTechException
     */
    public function __sleep(): array
    {
        throw new HayBTechException('La sérialisation du client HayBTech est interdite pour des raisons de sécurité.');
    }

    public function isTestMode(): bool
    {
        return str_starts_with($this->secretKey, 'sk_test_');
    }

    public function get(string $path, array $query = [], array $headers = []): HayBTechResponse
    {
        $url = $this->buildUrl($path);
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $this->send('GET', $url, null, $headers);
    }

    public function post(string $path, array $body = [], array $headers = []): HayBTechResponse
    {
        return $this->send('POST', $this->buildUrl($path), $body, $headers);
    }

    public function delete(string $path, array $headers = []): HayBTechResponse
    {
        return $this->send('DELETE', $this->buildUrl($path), null, $headers);
    }

    private function buildUrl(string $path): string
    {
        $baseUrl = $this->options['base_url'] 
            ?? getenv('HAYBTECH_API_URL') 
            ?? 'https://api.haybtech.sn/v1';

        $base = rtrim((string) $baseUrl, '/');

        return $base.'/'.ltrim($path, '/');
    }

    private function send(string $method, string $url, ?array $body, array $extraHeaders): HayBTechResponse
    {
        if (! extension_loaded('curl')) {
            throw new HayBTechException("L'extension PHP 'curl' est requise.");
        }

        $ch = curl_init();

        $mergedHeaders = array_merge($this->defaultHeaders, $extraHeaders);
        $headerLines   = array_map(
            static fn (string $k, string $v): string => "{$k}: {$v}",
            array_keys($mergedHeaders),
            array_values($mergedHeaders),
        );

        $jsonBody = $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : null;

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_TIMEOUT        => (int) ($this->options['timeout'] ?? 30),
            CURLOPT_CONNECTTIMEOUT => (int) ($this->options['connect_timeout'] ?? 10),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        match ($method) {
            'POST'   => curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $jsonBody]),
            'DELETE' => curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'),
            default  => null,
        };

        $rawResponse = curl_exec($ch);
        $httpStatus  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false || $curlError !== '') {
            throw new HayBTechException("Impossible de joindre l'API HayBTech: {$curlError}");
        }

        $decoded = json_decode((string) $rawResponse, true);

        if ($decoded === null) {
            throw new HayBTechException("Réponse API invalide (JSON).");
        }

        if ($httpStatus >= 400) {
            $errorBlock = $decoded['error'] ?? [];
            $code       = is_array($errorBlock) ? (string) ($errorBlock['code']    ?? '') : '';
            $message    = is_array($errorBlock) ? (string) ($errorBlock['message'] ?? '') : '';

            // We pass a sanitized version of the body to the exception to avoid
            // accidental leakage of sensitive merchant/payer data in log files.
            throw new ApiException($message, $httpStatus, $code, $this->sanitizeResponse($decoded));
        }

        return new HayBTechResponse($decoded, $httpStatus);
    }

    /**
     * Remove sensitive fields from the response before throwing an exception.
     */
    private function sanitizeResponse(array $data): array
    {
        $sensitive = ['secret', 'password', 'token', 'key', 'pin', 'cvv'];
        
        array_walk_recursive($data, function (&$value, $key) use ($sensitive) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $value = '********';
            }
        });

        return $data;
    }
}
