<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

/**
 * Defines the interface for the JSONPath extractor service.
 *
 * This service extracts values from decoded JSON payload arrays using
 * JSONPath expressions. It is used during webhook processing to map
 * incoming payload data to Drupal entity fields.
 */
interface JsonPathExtractorInterface {
  /**
   * Extracts a value from a payload using a JSONPath expression.
   *
   * When the expression matches a single scalar value, that value is returned
   * directly. When the expression matches multiple values (e.g., a wildcard
   * expression), an array of matched values is returned. Returns NULL when
   * no match is found.
   *
   * @param array<string, mixed> $payload
   *   The decoded JSON payload to extract from.
   * @param string $expression
   *   A JSONPath expression (e.g., '$.user.name', '$.tags[*]').
   *
   * @return mixed
   *   The extracted scalar value, an array of values for multi-match
   *   expressions, or NULL if the expression matches nothing.
   */
  public function extract(array $payload, string $expression): mixed;
}
