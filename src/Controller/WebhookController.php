<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\entity_webhook\Queue\WebhookQueueItem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Drupal\entity_webhook\Queue\WebhookQueueServiceInterface;
use Drupal\entity_webhook\Service\VerificationChainInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_webhook\Validator\WebhookRequestValidatorInterface;
use Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface;

/**
 * Handles incoming webhook HTTP POST requests.
 *
 * Validates the request synchronously (JSON parsing, entity existence,
 * verification plugin checks), then enqueues the payload for asynchronous
 * entity upsert processing. Returns immediate feedback via HTTP status codes.
 */
class WebhookController extends ControllerBase {
  /**
   * Constructs a WebhookController.
   *
   * @param \Drupal\entity_webhook\Validator\WebhookRequestValidatorInterface $validator
   *   The webhook request validator service.
   * @param \Drupal\entity_webhook\Queue\WebhookQueueServiceInterface $queueService
   *   The webhook queue service for enqueuing payloads.
   * @param \Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface $verificationManager
   *   The verification plugin manager.
   * @param \Drupal\entity_webhook\Service\VerificationChainInterface $verificationChain
   *   The verification chain service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel for entity_webhook.
   */
  public function __construct(
    protected readonly WebhookRequestValidatorInterface $validator,
    protected readonly WebhookQueueServiceInterface $queueService,
    protected readonly WebhookVerificationManagerInterface $verificationManager,
    protected readonly VerificationChainInterface $verificationChain,
    protected readonly LoggerChannelInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_webhook.webhook_request_validator'),
      $container->get('entity_webhook.webhook_queue'),
      $container->get('plugin.manager.webhook_verification'),
      $container->get('entity_webhook.verification_chain'),
      $container->get('logger.channel.entity_webhook'),
    );
  }

  /**
   * Receives a webhook POST request, validates it, and enqueues the payload.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request.
   * @param string $endpoint_name
   *   The WebhookEndpoint machine name from the route parameter.
   * @param string $source_type
   *   The WebhookSourceType machine name from the route parameter.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   200 on success, 400 on invalid JSON, 403 on verification failure,
   *   404 on unknown endpoint/source.
   */
  public function receive(Request $request, string $endpoint_name, string $source_type): Response {
    $this->logger->info('Webhook received for endpoint @endpoint, source @source.', [
      '@endpoint' => $endpoint_name,
      '@source' => $source_type,
    ]);

    $payload = $this->validator->parsePayload($request);
    if ($payload === NULL) {
      return $this->errorResponse('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
    }

    $endpoint = $this->validator->loadEndpoint($endpoint_name);
    if ($endpoint === NULL) {
      return $this->errorResponse('Endpoint not found.', Response::HTTP_NOT_FOUND);
    }

    $sourceTypeEntity = $this->validator->loadSourceType($source_type);
    if ($sourceTypeEntity === NULL) {
      return $this->errorResponse('Source type not found.', Response::HTTP_NOT_FOUND);
    }

    if (!$this->validator->isSourceTypeAllowed($endpoint, $source_type)) {
      return $this->errorResponse('Source type not allowed for this endpoint.', Response::HTTP_NOT_FOUND);
    }

    if (!$this->runVerification($request, $sourceTypeEntity)) {
      $this->logger->warning('Webhook verification failed for endpoint @endpoint, source @source.', [
        '@endpoint' => $endpoint_name,
        '@source' => $source_type,
      ]);

      return $this->errorResponse('Webhook verification failed.', Response::HTTP_FORBIDDEN);
    }

    $this->queueService->enqueue(new WebhookQueueItem(
      endpointId: $endpoint_name,
      sourceType: $source_type,
      payload: $payload,
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    ));

    $this->logger->info('Webhook payload queued for endpoint @endpoint, source @source.', [
      '@endpoint' => $endpoint_name,
      '@source' => $source_type,
    ]);

    return new JsonResponse(['status' => 'queued'], Response::HTTP_OK);
  }

  /**
   * Builds and runs the verification chain for the given source type.
   *
   * When no verification plugin is configured, verification passes by default.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request.
   * @param \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $sourceTypeEntity
   *   The source type config entity.
   *
   * @return bool
   *   TRUE if verification passes, FALSE otherwise.
   */
  private function runVerification(Request $request, WebhookSourceTypeInterface $sourceTypeEntity): bool {
    $pluginId = $sourceTypeEntity->getVerificationPlugin();

    if ($pluginId === '') {
      return TRUE;
    }

    $plugin = $this->verificationManager->createInstance(
      $pluginId,
      $sourceTypeEntity->getVerificationConfig(),
    );

    return $this->verificationChain->verify($request, [$plugin]);
  }

  /**
   * Builds a JSON error response with the given message and status code.
   *
   * @param string $message
   *   The human-readable error description.
   * @param int $statusCode
   *   The HTTP status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The error response.
   */
  private function errorResponse(string $message, int $statusCode): JsonResponse {
    return new JsonResponse(['error' => $message], $statusCode);
  }
}
