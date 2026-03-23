<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

/**
 * Value object representing the result of a single webhook delivery attempt.
 */
final readonly class DeliveryResult {

  /**
   * Constructs a DeliveryResult.
   *
   * @param bool $success
   *   TRUE if the delivery received a 2xx HTTP response.
   * @param int|null $httpStatus
   *   The HTTP response status code, or NULL if the request never completed.
   * @param string|null $responseBody
   *   The truncated response body, or NULL if unavailable.
   * @param string|null $errorMessage
   *   A description of the failure, or NULL on success.
   */
  public function __construct(
    public readonly bool $success,
    public readonly ?int $httpStatus,
    public readonly ?string $responseBody,
    public readonly ?string $errorMessage,
  ) {
  }

  /**
   * Creates a successful DeliveryResult.
   *
   * @param int $httpStatus
   *   The 2xx HTTP response status code.
   * @param string|null $responseBody
   *   The truncated response body.
   *
   * @return self
   *   A successful result.
   */
  public static function success(int $httpStatus, ?string $responseBody = NULL): self {
    return new self(
      success: TRUE,
      httpStatus: $httpStatus,
      responseBody: $responseBody,
      errorMessage: NULL,
    );
  }

  /**
   * Creates a failed DeliveryResult from an HTTP error response.
   *
   * @param int $httpStatus
   *   The non-2xx HTTP response status code.
   * @param string|null $responseBody
   *   The truncated response body.
   *
   * @return self
   *   A failed result.
   */
  public static function httpFailure(int $httpStatus, ?string $responseBody = NULL): self {
    return new self(
      success: FALSE,
      httpStatus: $httpStatus,
      responseBody: $responseBody,
      errorMessage: "HTTP {$httpStatus}",
    );
  }

  /**
   * Creates a failed DeliveryResult from a connection or transport error.
   *
   * @param string $errorMessage
   *   The error description.
   *
   * @return self
   *   A failed result with no HTTP status.
   */
  public static function connectionFailure(string $errorMessage): self {
    return new self(
      success: FALSE,
      httpStatus: NULL,
      responseBody: NULL,
      errorMessage: $errorMessage,
    );
  }

}
