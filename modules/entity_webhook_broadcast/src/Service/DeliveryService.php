<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Service;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Delivers webhook payloads via HTTP POST with optional HMAC signing.
 *
 * Uses the Guzzle HTTP client to POST a JSON-encoded payload to the
 * subscription's configured URL. Computes an HMAC signature header when the
 * subscription has a non-empty secret and a signing algorithm other than 'none'.
 * All transport and timeout errors are caught and returned as DeliveryResult
 * failures to prevent queue worker exceptions from consuming items silently.
 */
class DeliveryService implements DeliveryServiceInterface {

  /** Maximum number of response body bytes to store. */
  private const int RESPONSE_BODY_LIMIT = 1024;

  /** HTTP request timeout in seconds. */
  private const int REQUEST_TIMEOUT = 30;

  /** The signature header name sent with signed requests. */
  private const string SIGNATURE_HEADER = 'X-Webhook-Signature';

  /**
   * Constructs a DeliveryService.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerChannelInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function deliver(OutboundSubscriptionInterface $subscription, array $payload): DeliveryResult {
    try {
      $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
    catch (\JsonException $e) {
      return DeliveryResult::connectionFailure('Payload serialization failed: ' . $e->getMessage());
    }

    $headers = $this->buildHeaders($body, $subscription);

    try {
      $response = $this->httpClient->request('POST', $subscription->getUrl(), [
        'headers' => $headers,
        'body' => $body,
        'timeout' => self::REQUEST_TIMEOUT,
        'http_errors' => FALSE,
      ]);

      $statusCode = $response->getStatusCode();
      $responseBody = substr((string) $response->getBody(), 0, self::RESPONSE_BODY_LIMIT);

      if ($statusCode >= 200 && $statusCode < 300) {
        return DeliveryResult::success($statusCode, $responseBody);
      }

      return DeliveryResult::httpFailure($statusCode, $responseBody);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Webhook delivery transport error to @url: @message', [
        '@url' => $subscription->getUrl(),
        '@message' => $e->getMessage(),
      ]);

      return DeliveryResult::connectionFailure($e->getMessage());
    }
  }

  /**
   * Builds the HTTP headers for the outgoing request.
   *
   * @param string $body
   *   The JSON-encoded request body.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $subscription
   *   The subscription providing signing configuration.
   *
   * @return array<string, string>
   *   The headers array.
   */
  private function buildHeaders(string $body, OutboundSubscriptionInterface $subscription): array {
    $headers = ['Content-Type' => 'application/json'];

    $signature = $this->computeSignature($body, $subscription);
    if ($signature !== NULL) {
      $headers[self::SIGNATURE_HEADER] = $signature;
    }

    return $headers;
  }

  /**
   * Computes the HMAC signature for the request body.
   *
   * @param string $body
   *   The JSON-encoded request body.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $subscription
   *   The subscription providing the secret and signing algorithm.
   *
   * @return string|null
   *   The hex-encoded HMAC signature, or NULL if signing is disabled.
   */
  private function computeSignature(string $body, OutboundSubscriptionInterface $subscription): ?string {
    $secret = $subscription->getSecret();
    $algorithm = $subscription->getSigningAlgorithm();

    if ($secret === NULL || $algorithm === 'none') {
      return NULL;
    }

    return hash_hmac($algorithm, $body, $secret);
  }

}
