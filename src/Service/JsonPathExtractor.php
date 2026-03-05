<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Service;

use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;

/**
 * Extracts values from JSON payloads using JSONPath expressions.
 *
 * Wraps the softcreatr/jsonpath library to provide a simple, testable service
 * for field mapping during webhook payload processing.
 */
class JsonPathExtractor implements JsonPathExtractorInterface {
  /**
   * {@inheritdoc}
   */
  public function extract(array $payload, string $expression): mixed {
    try {
      $results = (new JSONPath($payload))->find($expression);

      return $this->normalizeResults($results);
    } catch (JSONPathException) {
      return NULL;
    }
  }

  /**
   * Normalizes the JSONPath result set into a scalar, array, or null.
   *
   * @param \Flow\JSONPath\JSONPath $results
   *   The raw result set from the JSONPath library.
   *
   * @return mixed
   *   A scalar value, an array for multi-match expressions, or NULL.
   */
  private function normalizeResults(JSONPath $results): mixed {
    $data = $results->getData();

    if (empty($data)) {
      return NULL;
    }

    if (count($data) === 1) {
      return $data[0];
    }

    return array_values($data);
  }
}
