# HayBTech Commerce (Drupal)

Official Drupal Commerce payment gateway module for HayBTech -- accept mobile money payments on your Drupal Commerce store.

[![Drupal](https://img.shields.io/badge/Drupal-9%20%7C%2010-0678BE.svg)](https://www.drupal.org/)
[![Commerce](https://img.shields.io/badge/Commerce-2.x-green.svg)](https://www.drupal.org/project/commerce)
[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## Features

- Native Drupal Commerce 2.x offsite payment gateway
- Admin configuration form with test/live key management
- Automatic webhook (IPN) verification with HMAC-SHA256 signatures
- Full support for Test and Live modes
- Zero external Composer dependencies (embedded SDK)
- Handles `onReturn`, `onCancel`, and `onNotify` lifecycle events

---

## Requirements

| Requirement       | Version        |
|:------------------|:---------------|
| Drupal            | 9.x or 10.x   |
| Commerce          | 2.x            |
| Commerce Payment  | 2.x            |
| Commerce Order    | 2.x            |
| PHP               | 8.1+           |

---

## Installation

### Via Composer

```bash
composer require haybtech/commerce
drush en haybtech_commerce -y
drush cr
```

### Manual

1. Copy the `haybtech_commerce` directory to `<drupal-root>/modules/custom/`.
2. Enable via admin: **Extend > HayBTech Commerce > Install**.
3. Or via Drush:

```bash
drush en haybtech_commerce -y
drush cr
```

---

## Configuration

1. Go to **Commerce > Configuration > Payment gateways**.
2. Click **Add payment gateway**.
3. Select **HayBTech ** as the plugin.
4. Configure:

| Field                  | Description                                    |
|:-----------------------|:-----------------------------------------------|
| **Display name**       | Name shown to customers at checkout            |
| **Mode**               | Test or Live                                   |
| **Test Secret Key**    | Your `sk_test_...` key                         |
| **Live Secret Key**    | Your `sk_live_...` key                         |
| **Webhook Secret**     | Your `whsec_...` signing secret                |

5. Click **Save**.

---

## Webhook Setup

1. In your **HayBTech Dashboard**, go to **Settings > Webhooks**.
2. Add a new endpoint:

```
https://your-site.com/payment/notify/haybtech_offsite
```

3. Copy the webhook secret into the gateway configuration.

---

## How It Works

1. Customer selects **HayBTech** at checkout.
2. Drupal Commerce redirects to the HayBTech hosted payment page.
3. Customer pays via mobile money.
4. HayBTech sends a signed IPN (webhook) to your `onNotify` endpoint.
5. The module verifies the HMAC-SHA256 signature, creates a Commerce Payment entity, and marks the order as completed.
6. Customer is redirected back via the `onReturn` handler.

---


---

## Module Structure

```
haybtech_commerce/
  haybtech_commerce.info.yml          # Module metadata and dependencies
  src/
    Plugin/Commerce/PaymentGateway/
      HayBTech.php                    # Offsite gateway (config, onReturn, onNotify)
  lib/
    sdk/                              # Embedded HayBTech PHP SDK
      HayBTech.php, HayBTechClient.php, HayBTechResponse.php,
      Webhook.php
      Resources/
        Payments.php, Webhooks.php
      Exceptions/
        ApiException.php, HayBTechException.php, SignatureException.php
```

---

## Security

- **HMAC-SHA256 Webhook Verification** with constant-time comparison
- **Zero External Dependencies** -- embedded SDK, no supply chain risk
- **Secret Masking** in logs and debug output
- **1 MB Payload Limit** to prevent DoS
- **CRLF Guard** against HTTP header injection
- **Drupal Logging** -- errors are logged via `\Drupal::logger('haybtech')`

---

## Troubleshooting

| Issue                    | Solution                                                      |
|:-------------------------|:--------------------------------------------------------------|
| Module not appearing     | Run `drush cr` to clear caches                                |
| Gateway not in list      | Verify `commerce_payment` and `commerce_order` are enabled    |
| Webhooks returning 403   | Check webhook secret matches the HayBTech dashboard           |
| Orders not completing    | Check Drupal logs at **Reports > Recent log messages**        |

---

MIT License
# haybtech_commerce_plugin
# haybtech_commerce_plugin
