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
 * The incoming request must include a signature header containing a
 * "sha256=" prefixed hex-encoded HMAC of the raw request body, computed
 * with the configured shared secret. Comparison is done in constant time
 * using hash_equals() to prevent timing attacks.
 *
 * Compatible with GitHub (X-Hub-Signature-256) and Shopify
 * (X-Shopify-Hmac-SHA256) webhook signatures.
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
    $expectedSignature = 'sha256=' . hash_hmac('sha256', $body, $secret);

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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['header'] = $form_state->getValue('header');
    $this->configuration['secret'] = $form_state->getValue('secret');
  }
}
