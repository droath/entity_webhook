<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Validator;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;

/**
 * Validates incoming webhook requests and resolves their config entities.
 *
 * Performs JSON parsing and config entity lookups. Cryptographic verification
 * is intentionally omitted here and delegated to verification plugins.
 */
class WebhookRequestValidator implements WebhookRequestValidatorInterface {
  /**
   * Constructs a WebhookRequestValidator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager for loading config entities.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function parsePayload(Request $request): ?array {
    $content = $request->getContent();

    if ($content === '' || $content === FALSE) {
      return NULL;
    }

    try {
      $decoded = json_decode((string) $content, TRUE, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
      return NULL;
    }

    if (!is_array($decoded)) {
      return NULL;
    }

    return $decoded;
  }

  /**
   * {@inheritdoc}
   */
  public function loadEndpoint(string $endpointName): ?WebhookEndpointInterface {
    $entity = $this->entityTypeManager
      ->getStorage('webhook_endpoint')
      ->load($endpointName);

    if (!$entity instanceof WebhookEndpointInterface) {
      return NULL;
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function loadSourceType(string $sourceTypeName): ?WebhookSourceTypeInterface {
    $entity = $this->entityTypeManager
      ->getStorage('webhook_source_type')
      ->load($sourceTypeName);

    if (!$entity instanceof WebhookSourceTypeInterface) {
      return NULL;
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function isSourceTypeAllowed(WebhookEndpointInterface $endpoint, string $sourceTypeName): bool {
    return $endpoint->hasSourceType($sourceTypeName);
  }
}
