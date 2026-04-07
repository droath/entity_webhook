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
use Drupal\entity_webhook\Service\WebhookProcessorInterface;
use Drupal\entity_webhook\Queue\WebhookQueueServiceInterface;
use Drupal\entity_webhook\Service\VerificationChainInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\entity_webhook\Validator\WebhookRequestValidatorInterface;
use Drupal\entity_webhook\Plugin\WebhookVerification\WebhookVerificationManagerInterface;

/**
 * Handles incoming webhook HTTP POST requests.
 *
 * Validates the request synchronously (JSON parsing, entity existence,
 * verification plugin checks), then either processes the payload immediately
 * (sync mode) or enqueues it for asynchronous processing (async mode).
 * Returns immediate feedback via HTTP status codes.
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
   * @param \Drupal\entity_webhook\Service\WebhookProcessorInterface $processor
   *   The webhook processor service for synchronous processing.
   */
  public function __construct(
    protected readonly WebhookRequestValidatorInterface $validator,
    protected readonly WebhookQueueServiceInterface $queueService,
    protected readonly WebhookVerificationManagerInterface $verificationManager,
    protected readonly VerificationChainInterface $verificationChain,
    protected readonly LoggerChannelInterface $logger,
    protected readonly WebhookProcessorInterface $processor,
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
      $container->get('entity_webhook.webhook_processor'),
    );
  }

  /**
   * Receives a webhook POST request, validates it, and processes or queues it.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request.
   * @param string $endpoint_name
   *   The WebhookEndpoint machine name from the route parameter.
   * @param string $source_type
   *   The WebhookSourceType machine name from the route parameter.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   200 on success (sync: result data, async: queued status),
   *   400 on invalid JSON, 403 on verification failure,
   *   404 on unknown endpoint/source, 422 on sync processing failure.
   */
  public function receive(
    Request $request,
    string $endpoint_name,
    string $source_type,
  ): Response {
    $logContext = ['@source' => $source_type, '@endpoint' => $endpoint_name];

    $this->logger->info(
      'Webhook received for endpoint @endpoint, source @source.',
      $logContext,
    );

    $payload = $this->loadOrFail(
      fn () => $this->validator->parsePayload($request),
      'Invalid JSON payload.',
      Response::HTTP_BAD_REQUEST,
    );

    if ($payload instanceof JsonResponse) {
      return $payload;
    }

    $endpoint = $this->loadOrFail(
      fn () => $this->validator->loadEndpoint($endpoint_name),
      'Endpoint not found.',
      Response::HTTP_NOT_FOUND,
    );

    if ($endpoint instanceof JsonResponse) {
      return $endpoint;
    }

    $sourceTypeEntity = $this->loadOrFail(
      fn () => $this->validator->loadSourceType($source_type),
      'Source type not found.',
      Response::HTTP_NOT_FOUND,
    );

    if ($sourceTypeEntity instanceof JsonResponse) {
      return $sourceTypeEntity;
    }

    if (!$this->validator->isSourceTypeAllowed($endpoint, $source_type)) {
      return $this->errorResponse(
        'Source type is not allowed for this endpoint.',
        Response::HTTP_NOT_FOUND,
      );
    }

    if (!$this->runVerification($request, $sourceTypeEntity)) {
      $this->logger->warning(
        'Webhook verification failed for endpoint @endpoint, source @source.',
        $logContext,
      );

      return $this->errorResponse(
        'Webhook verification failed.',
        Response::HTTP_FORBIDDEN,
      );
    }

    $item = new WebhookQueueItem(
      endpointId: $endpoint_name,
      sourceType: $source_type,
      payload: $payload,
      receivedAt: new \DateTimeImmutable(),
      source: 'webhook',
    );

    if ($endpoint->isSync()) {
      return $this->processSynchronously($item, $logContext);
    }

    return $this->enqueueAsynchronously($item, $logContext);
  }

  /**
   * Processes the item synchronously and returns the result as a response.
   *
   * @param \Drupal\entity_webhook\Queue\WebhookQueueItem $item
   *   The queue item to process immediately.
   * @param array<string, string> $logContext
   *   Log context for endpoint/source logging.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   200 with result data on success, 422 with error on failure.
   */
  private function processSynchronously(WebhookQueueItem $item, array $logContext): JsonResponse {
    $result = $this->processor->process($item);

    if ($result->success) {
      $this->logger->info(
        'Webhook processed synchronously for endpoint @endpoint, source @source.',
        $logContext,
      );

      return new JsonResponse($result->toResponseArray(), Response::HTTP_OK);
    }

    $this->logger->warning(
      'Synchronous webhook processing failed for endpoint @endpoint, source @source.',
      $logContext,
    );

    return new JsonResponse($result->toResponseArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
  }

  /**
   * Enqueues the item for asynchronous processing and returns immediately.
   *
   * @param \Drupal\entity_webhook\Queue\WebhookQueueItem $item
   *   The queue item to enqueue.
   * @param array<string, string> $logContext
   *   Log context for endpoint/source logging.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   200 with queued status.
   */
  private function enqueueAsynchronously(WebhookQueueItem $item, array $logContext): JsonResponse {
    $this->queueService->enqueue($item);

    $this->logger->info(
      'Webhook payload queued for endpoint @endpoint, source @source.',
      $logContext,
    );

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
   * @throws \Drupal\Component\Plugin\Exception\PluginException
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
   * Loads a resource or returns an error response if not found.
   *
   * @param callable $loader
   *   A callable that returns the loaded resource or null.
   * @param string $message
   *   The error message if resource is not found.
   * @param int $status
   *   The HTTP status code for the error response.
   *
   * @return mixed
   *   The loaded resource, or a JsonResponse on failure.
   */
  private function loadOrFail(callable $loader, string $message, int $status): mixed {
    $result = $loader();

    if ($result === NULL) {
      return $this->errorResponse($message, $status);
    }

    return $result;
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
