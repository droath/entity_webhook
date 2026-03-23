<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\WebhookVerification;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Attribute\WebhookVerification;

/**
 * Verifies webhook requests using HMAC-SHA256 signature validation.
 *
 * The incoming request must include a signature header whose value is an
 * HMAC-SHA256 of the raw request body computed with the configured shared
 * secret. No prefix is added to the computed signature.
 *
 * Two encoding modes are supported:
 * - Hex (default): the signature is the lowercase hex string produced by
 *   hash_hmac('sha256', …).
 * - Base64: the signature is base64-encoded binary output, produced by
 *   base64_encode(hash_hmac('sha256', …, true)).
 *
 * Comparison is always done in constant time via hash_equals() to prevent
 * timing attacks.
 */
#[WebhookVerification(
  id: 'hmac_verification',
  label: new TranslatableMarkup('HMAC Signature Verification'),
  description: new TranslatableMarkup('Verifies requests using an HMAC-SHA256 signature in a configurable header.'),
)]
class HmacVerification extends WebhookVerificationBase {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'secret' => '',
      'header' => 'X-Hub-Signature-256',
      'base64' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function verify(Request $request): bool {
    $secret = $this->configuration['secret'];
    $headerName = $this->configuration['header'];

    if ($secret === '') {
      return FALSE;
    }

    $providedSignature = $request->headers->get($headerName);
    if ($providedSignature === NULL || $providedSignature === '') {
      return FALSE;
    }

    $body = (string) $request->getContent();
    $expectedSignature = $this->computeExpectedSignature($body, $secret);

    return hash_equals($expectedSignature, $providedSignature);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['header'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Signature header'),
      '#description' => new TranslatableMarkup('The HTTP header containing the HMAC signature (e.g. X-Hub-Signature-256, X-Shopify-Hmac-SHA256).'),
      '#default_value' => $this->configuration['header'],
      '#required' => TRUE,
    ];

    $form['secret'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Shared secret'),
      '#description' => new TranslatableMarkup('The secret used to compute the expected HMAC-SHA256 signature.'),
      '#default_value' => $this->configuration['secret'],
      '#required' => TRUE,
    ];

    $form['base64'] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('Base64-encode the signature'),
      '#description' => new TranslatableMarkup('When enabled, the expected signature is base64-encoded binary output instead of a hex string. Use this for services such as Shopify that send a base64-encoded HMAC.'),
      '#default_value' => $this->configuration['base64'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['header'] = $form_state->getValue('header');
    $this->configuration['secret'] = $form_state->getValue('secret');
    $this->configuration['base64'] = (bool) $form_state->getValue('base64');
  }

  /**
   * Computes the expected HMAC-SHA256 signature for the given body and secret.
   *
   * @param string $body
   *   The raw request body.
   * @param string $secret
   *   The shared secret.
   *
   * @return string
   *   The expected signature, either hex-encoded or base64-encoded depending
   *   on the 'base64' configuration value.
   */
  private function computeExpectedSignature(string $body, string $secret): string {
    if ($this->configuration['base64']) {
      return base64_encode(hash_hmac('sha256', $body, $secret, TRUE));
    }

    return hash_hmac('sha256', $body, $secret);
  }
}
