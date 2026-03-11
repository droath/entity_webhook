<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Validator;

use Symfony\Component\HttpFoundation\Request;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;

/**
 * Validates incoming webhook requests and loads their config entities.
 *
 * Responsible for JSON parsing, endpoint existence checks, source type
 * existence checks, and verifying the source type is associated with
 * the endpoint. Does not perform cryptographic verification.
 */
interface WebhookRequestValidatorInterface {
  /**
   * Parses the JSON body from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request.
   *
   * @return array<string, mixed>|null
   *   The decoded payload, or NULL if the body is not valid JSON.
   */
  public function parsePayload(Request $request): ?array;

  /**
   * Loads a WebhookEndpoint config entity by machine name.
   *
   * @param string $endpointName
   *   The endpoint machine name from the route parameter.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null
   *   The loaded entity, or NULL if not found.
   */
  public function loadEndpoint(string $endpointName): ?WebhookEndpointInterface;

  /**
   * Loads a WebhookSourceType config entity by machine name.
   *
   * @param string $sourceTypeName
   *   The source type machine name from the route parameter.
   *
   * @return \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface|null
   *   The loaded entity, or NULL if not found.
   */
  public function loadSourceType(string $sourceTypeName): ?WebhookSourceTypeInterface;

  /**
   * Returns whether the source type is associated with the given endpoint.
   *
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface $endpoint
   *   The webhook endpoint.
   * @param string $sourceTypeName
   *   The source type machine name to check.
   *
   * @return bool
   *   TRUE if the source type is registered on the endpoint.
   */
  public function isSourceTypeAllowed(WebhookEndpointInterface $endpoint, string $sourceTypeName): bool;
}
