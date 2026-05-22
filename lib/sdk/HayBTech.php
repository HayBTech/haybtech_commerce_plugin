<?php

declare(strict_types=1);

namespace HayBTech;

use HayBTech\Exceptions\HayBTechException;
use HayBTech\Resources\Payments;

/**
 * HayBTech PHP SDK — façade principale.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * UTILISATION RECOMMANDÉE
 * ──────────────────────────────────────────────────────────────────────────
 *
 * 1. Configurer une seule fois (bootstrap / service provider)
 * ────────────────────────────────────────────────────────────
 *   HayBTech::configure(getenv('HAYBTECH_SECRET_KEY'));
 *
 *
 * 2. Utiliser n'importe où dans l'application
 * ────────────────────────────────────────────
 *   $result = HayBTech::payments()->create([
 *       'merchant_ref' => 'CMD-' . uniqid(),
 *       'amount'       => 25000,
 *       'currency'     => 'XOF',
 *       'return_url'   => 'https://monsite.sn/succes',
 *       'cancel_url'   => 'https://monsite.sn/annulation',
 *       'callback_url' => 'https://monsite.sn/webhook',
 *   ]);
 *   header('Location: ' . $result['data']['payment_url']);
 *
 *
 * 3. Webhook
 * ──────────
 *   $event = HayBTech::webhook()::constructEvent($payload, $signature, $secret);
 *   match ($event['event']) {
 *       'payment.success'   => markOrderAsPaid($event['data']['merchant_ref']),
 *       'payment.failed'    => markOrderAsFailed($event['data']['merchant_ref']),
 *       default             => null,
 *   };
 *
 *
 * ──────────────────────────────────────────────────────────────────────────
 * UTILISATION AVANCÉE (client explicite, base_url custom…)
 * ──────────────────────────────────────────────────────────────────────────
 *   $client = HayBTech::client('sk_live_…', ['base_url' => 'https://…']);
 *   $client->payments->create([...]);
 */
final class HayBTech
{
    private static ?HayBTechClient $instance = null;

    /**
     * Configure le SDK une seule fois (service provider, bootstrap…).
     * Toutes les méthodes statiques utilisent ensuite ce client partagé.
     *
     * @param  array{base_url?: string, timeout?: int, connect_timeout?: int}  $options
     */
    public static function configure(string $secretKey, array $options = []): void
    {
        self::$instance = new HayBTechClient($secretKey, $options);
    }

    /**
     * Crée un client explicite (multi-compte, base_url custom, tests…).
     * Si $secretKey est vide, retourne le client partagé configuré via configure().
     *
     * @param  array{base_url?: string, timeout?: int, connect_timeout?: int}  $options
     */
    public static function client(string $secretKey = '', array $options = []): HayBTechClient
    {
        if ($secretKey !== '') {
            return new HayBTechClient($secretKey, $options);
        }

        return self::sharedClient();
    }

    /** @throws HayBTechException */
    public static function payments(): Payments
    {
        return self::sharedClient()->payments;
    }



    /** @throws HayBTechException */
    public static function webhooks(): Resources\Webhooks
    {
        return self::sharedClient()->webhooks;
    }

    /**
     * Retourne la classe Webhook (méthodes statiques, pas d'état nécessaire).
     *
     * @return class-string<Webhook>
     */
    public static function webhook(): string
    {
        return Webhook::class;
    }

    /** @throws HayBTechException */
    private static function sharedClient(): HayBTechClient
    {
        if (self::$instance === null) {
            throw new HayBTechException(
                'SDK non configuré. Appelez HayBTech::configure($secretKey) avant utilisation.'
            );
        }

        return self::$instance;
    }
}
