<?php

namespace Drupal\haybtech_commerce\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the HayBTech offsite payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "haybtech_offsite",
 *   label = "HayBTech (Orange Money, Wave, Free Money)",
 *   display_label = "HayBTech",
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_payment\PluginForm\OffsitePayment\PaymentOffsiteForm",
 *   },
 * )
 */
class HayBTech extends OffsitePaymentGatewayBase {

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration() {
        return [
            'test_secret_key' => '',
            'live_secret_key' => '',
            'webhook_secret' => '',
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);

        $form['test_secret_key'] = [
            '#type' => 'password',
            '#title' => $this->t('Test Secret Key'),
            '#default_value' => $this->configuration['test_secret_key'],
            '#required' => TRUE,
        ];

        $form['live_secret_key'] = [
            '#type' => 'password',
            '#title' => $this->t('Live Secret Key'),
            '#default_value' => $this->configuration['live_secret_key'],
            '#required' => TRUE,
        ];

        $form['webhook_secret'] = [
            '#type' => 'password',
            '#title' => $this->t('Webhook Secret'),
            '#default_value' => $this->configuration['webhook_secret'],
            '#required' => TRUE,
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['test_secret_key'] = $values['test_secret_key'];
            $this->configuration['live_secret_key'] = $values['live_secret_key'];
            $this->configuration['webhook_secret'] = $values['webhook_secret'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onReturn(OrderInterface $order, Request $request) {
        // Drupal automatically handles return URLs. 
        // We verify the status via a backend API call just in case the webhook was delayed.
        $this->verifyTransaction($order);
    }

    /**
     * Verifies the transaction status directly via the API.
     * This acts as a fallback or double-check for the webhook.
     */
    protected function verifyTransaction(OrderInterface $order) {
        try {
            $this->loadSdk();
            
            // Assuming we fetch the status using the order ID as merchant_ref
            $response = \HayBTech\HayBTech::status()->get($order->id());
            
            if (isset($response['data']['status']) && $response['data']['status'] === 'success') {
                $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
                
                // Check if payment was already created by the webhook to avoid duplicates
                $query = $payment_storage->getQuery()
                    ->condition('order_id', $order->id())
                    ->condition('payment_gateway', $this->parentEntity->id())
                    ->accessCheck(FALSE);
                $payment_ids = $query->execute();
                
                if (empty($payment_ids)) {
                    $payment = $payment_storage->create([
                        'state' => 'completed',
                        'amount' => $order->getTotalPrice(),
                        'payment_gateway' => $this->parentEntity->id(),
                        'order_id' => $order->id(),
                        'remote_id' => $response['data']['id'] ?? uniqid('haybtech_'),
                        'remote_state' => 'success',
                    ]);
                    $payment->save();
                }
            }
        } catch (\Exception $e) {
            // Log securely but do not crash the user experience
            \Drupal::logger('haybtech')->error('Verification error on return: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onCancel(OrderInterface $order, Request $request) {
        parent::onCancel($order, $request);
    }

    /**
     * {@inheritdoc}
     */
    public function onNotify(Request $request) {
        // This is where the webhook (IPN) is handled.
        // SECURITY: Signature verification is done inside the SDK.
        $payload = $request->getContent();
        $signature = $request->headers->get('X-HayBTech-Signature');
        
        try {
            // Load SDK safely before using it
            $this->loadSdk();
            
            $event = \HayBTech\Webhook::constructEvent($payload, $signature, $this->configuration['webhook_secret']);
            
            $order_id = $event['data']['merchant_ref'] ?? null;
            $order = \Drupal\commerce_order\Entity\Order::load($order_id);

            if ($order && $event['event'] === 'payment.success') {
                $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
                $payment = $payment_storage->create([
                    'state' => 'completed',
                    'amount' => $order->getTotalPrice(),
                    'payment_gateway' => $this->parentEntity->id(),
                    'order_id' => $order->id(),
                    'remote_id' => $event['data']['id'],
                    'remote_state' => 'success',
                ]);
                $payment->save();
            }
        } catch (\Exception $e) {
            \Drupal::logger('haybtech')->error('Webhook error: ' . $e->getMessage());
            return new \Symfony\Component\HttpFoundation\Response('Invalid Signature', 403);
        }

        return new \Symfony\Component\HttpFoundation\Response('OK', 200);
    }
    /**
     * Safely loads the SDK whether using Composer or manual ZIP installation.
     */
    protected function loadSdk() {
        if (!class_exists('\HayBTech\HayBTech')) {
            $sdk_path = dirname(__DIR__, 4) . '/lib/sdk/HayBTech.php';
            if (file_exists($sdk_path)) {
                require_once $sdk_path;
            } else {
                throw new \Exception('HayBTech SDK is not installed via Composer and not found in the lib directory.');
            }
        }
        
        $mode = $this->getMode();
        $secret_key = $mode === 'test' ? $this->configuration['test_secret_key'] : $this->configuration['live_secret_key'];
        
        \HayBTech\HayBTech::configure($secret_key);
    }
}
