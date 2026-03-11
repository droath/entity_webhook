<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Plugin\WebhookVerification;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Attribute\WebhookVerification;

/**
 * Verifies webhook requests by comparing an API key from a header or query
 * parameter.
 *
 * The API key can be extracted from either:
 * - An HTTP header (e.g. X-API-Key, Authorization)
 * - A query parameter (e.g. ?token=...)
 *
 * Comparison is done in constant time using hash_equals() to prevent
 * timing attacks. An empty configured key always fails.
 */
#[WebhookVerification(
  id: 'api_key_verification',
  label: new TranslatableMarkup('API Key Verification'),
  description: new TranslatableMarkup('Verifies requests using an API key from a configurable header or query parameter.'),
)]
class ApiKeyVerification extends WebhookVerificationBase {
  /** @var string */
  private const string SOURCE_HEADER = 'header';

  /** @var string */
  private const string SOURCE_QUERY = 'query';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'api_key' => NULL,
      'query_param' => 'token',
      'source' => self::SOURCE_HEADER,
      'header_name' => 'X-API-Key',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function verify(Request $request): bool {
    $configuredKey = $this->configuration['api_key'];

    if ($configuredKey === '') {
      return FALSE;
    }
    $providedKey = $this->extractKeyFromRequest($request);

    if ($providedKey === NULL || $providedKey === '') {
      return FALSE;
    }

    return hash_equals($configuredKey, $providedKey);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('API key'),
      '#description' => new TranslatableMarkup('The expected API key value.'),
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];

    $form['source'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Key location'),
      '#description' => new TranslatableMarkup('Where to read the API key from the incoming request.'),
      '#options' => [
        self::SOURCE_HEADER => new TranslatableMarkup('HTTP header'),
        self::SOURCE_QUERY => new TranslatableMarkup('Query parameter'),
      ],
      '#default_value' => $this->configuration['source'],
      '#required' => TRUE,
    ];

    $form['header_name'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Header name'),
      '#description' => new TranslatableMarkup('The HTTP header containing the API key (e.g. X-API-Key).'),
      '#default_value' => $this->configuration['header_name'],
      '#states' => [
        'visible' => [
          ':input[name="source"]' => ['value' => self::SOURCE_HEADER],
        ],
      ],
    ];

    $form['query_param'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Query parameter name'),
      '#description' => new TranslatableMarkup('The query parameter containing the API key (e.g. token).'),
      '#default_value' => $this->configuration['query_param'],
      '#states' => [
        'visible' => [
          ':input[name="source"]' => ['value' => self::SOURCE_QUERY],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['api_key'] = $form_state->getValue('api_key');
    $this->configuration['source'] = $form_state->getValue('source');
    $this->configuration['header_name'] = $form_state->getValue('header_name');
    $this->configuration['query_param'] = $form_state->getValue('query_param');
  }

  /**
   * Extracts the API key from the request based on the configured source.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request.
   *
   * @return string|null
   *   The extracted key, or NULL if not present.
   */
  private function extractKeyFromRequest(Request $request): ?string {
    return match ($this->configuration['source']) {
      self::SOURCE_HEADER => $request->headers->get($this->configuration['header_name']),
      self::SOURCE_QUERY => $request->query->get($this->configuration['query_param']),
      default => throw new \UnexpectedValueException(
        sprintf('Unknown API key source: %s', $this->configuration['source']),
      ),
    };
  }
}
