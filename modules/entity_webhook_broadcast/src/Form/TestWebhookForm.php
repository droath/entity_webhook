<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity_webhook\Traits\AjaxFormStateTrait;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface;
use Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface;
use Drupal\entity_webhook_broadcast\Service\DeliveryServiceInterface;
use Drupal\entity_webhook_broadcast\Service\PayloadBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the test webhook form for outbound subscriptions.
 *
 * Allows administrators to select a recent entity of the endpoint's target
 * type, preview the built payload JSON, and send a synchronous test delivery.
 */
class TestWebhookForm extends FormBase {
  use AjaxFormStateTrait;

  /** Maximum number of recent entities shown in the entity selector. */
  private const int RECENT_ENTITIES_LIMIT = 20;

  /**
   * Constructs a TestWebhookForm.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\entity_webhook_broadcast\Service\PayloadBuilderInterface $payloadBuilder
   *   The payload builder service.
   * @param \Drupal\entity_webhook_broadcast\Service\DeliveryServiceInterface $deliveryService
   *   The delivery service.
   */
  public function __construct(
    RouteMatchInterface $routeMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PayloadBuilderInterface $payloadBuilder,
    protected readonly DeliveryServiceInterface $deliveryService,
  ) {
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('entity_webhook_broadcast.payload_builder'),
      $container->get('entity_webhook_broadcast.delivery_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_webhook_broadcast_test_webhook';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $subscription = $this->resolveSubscription();
    $endpoint = $this->resolveEndpoint();

    if ($subscription === NULL || $endpoint === NULL) {
      $form['error'] = [
        '#markup' => $this->t('Unable to resolve subscription or endpoint from the current route.'),
      ];

      return $form;
    }

    $form['#prefix'] = '<div id="test-webhook-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['subscription_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Subscription'),
    ];

    $form['subscription_info']['details'] = [
      '#markup' => $this->t(
        '<strong>@label</strong><br>URL: @url<br>Algorithm: @algorithm',
        [
          '@label' => $subscription->label(),
          '@url' => $subscription->getUrl(),
          '@algorithm' => $subscription->getSigningAlgorithm(),
        ],
      ),
    ];

    $entityTypeId = $endpoint->getWatchedEntityType();
    $entityOptions = $this->getRecentEntityOptions($entityTypeId, $endpoint->getEntityBundle());

    $form['entity_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Source Entity'),
      '#description' => $this->t('Select a recent @type entity to use as the payload source.', [
        '@type' => $entityTypeId,
      ]),
      '#options' => $entityOptions,
      '#empty_option' => $this->t('- Select an entity -'),
      '#empty_value' => '',
      '#required' => TRUE,
    ];

    $form['payload_preview'] = [
      '#type' => 'container',
      '#prefix' => '<div id="payload-preview-wrapper">',
      '#suffix' => '</div>',
    ];

    $selectedEntityId = $this->getFormStateValue('entity_id', $form_state);
    if ($selectedEntityId !== NULL && $selectedEntityId !== '') {
      $payload = $this->buildPayloadPreview($entityTypeId, (string) $selectedEntityId, $subscription);
      if ($payload !== NULL) {
        $form['payload_preview']['output'] = [
          '#type' => 'details',
          '#title' => $this->t('Payload Preview'),
          '#open' => TRUE,
        ];
        $form['payload_preview']['output']['json'] = [
          '#type' => 'textarea',
          '#title' => $this->t('JSON Payload'),
          '#value' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
          '#rows' => 15,
          '#attributes' => ['readonly' => 'readonly'],
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('Preview Payload'),
      '#submit' => [],
      '#ajax' => [
        'wrapper' => 'test-webhook-form-wrapper',
        'callback' => [$this, 'ajaxRebuildForm'],
      ],
      '#limit_validation_errors' => [['entity_id']],
    ];

    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Test Webhook'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * AJAX callback that returns the entire form wrapper.
   *
   * @param array<string, mixed> $form
   *   The form array.
   *
   * @return array<string, mixed>
   *   The form element.
   */
  public function ajaxRebuildForm(array $form): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $triggeringElement = $form_state->getTriggeringElement();
    if (($triggeringElement['#parents'] ?? []) !== ['send']) {
      return;
    }

    $subscription = $this->resolveSubscription();
    $endpoint = $this->resolveEndpoint();

    if ($subscription === NULL || $endpoint === NULL) {
      $this->messenger()->addError($this->t('Unable to resolve subscription or endpoint.'));

      return;
    }

    $entityId = (string) $form_state->getValue('entity_id');
    $entityTypeId = $endpoint->getWatchedEntityType();

    $entity = $this->entityTypeManager->getStorage($entityTypeId)->load($entityId);
    if ($entity === NULL) {
      $this->messenger()->addError($this->t('Selected entity could not be loaded.'));

      return;
    }

    $payload = $this->payloadBuilder->build($entity, $subscription);
    $result = $this->deliveryService->deliver($subscription, $payload);

    if ($result->success) {
      $this->messenger()->addStatus(
        $this->t('Test webhook delivered successfully. HTTP @status.', [
          '@status' => $result->httpStatus,
        ]),
      );
    }
    else {
      $this->messenger()->addError(
        $this->t('Test webhook delivery failed. @message', [
          '@message' => $result->errorMessage ?? ('HTTP ' . $result->httpStatus),
        ]),
      );
    }

    $this->displayDeliveryResult($result, $subscription, $payload);
  }

  /**
   * Displays the full delivery result details via messenger.
   *
   * @param \Drupal\entity_webhook_broadcast\Service\DeliveryResult $result
   *   The delivery result.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $subscription
   *   The subscription used for delivery.
   * @param array<string, mixed> $payload
   *   The payload that was sent.
   */
  private function displayDeliveryResult(
    \Drupal\entity_webhook_broadcast\Service\DeliveryResult $result,
    OutboundSubscriptionInterface $subscription,
    array $payload,
  ): void {
    $algorithm = $subscription->getSigningAlgorithm();
    $secret = $subscription->getSecret();

    $hmacInfo = match(TRUE) {
      $algorithm === 'none' => $this->t('Signing disabled.'),
      $secret === NULL => $this->t('No secret configured — request unsigned.'),
      default => $this->t('Signed with @algorithm. Header: X-Webhook-Signature.', [
        '@algorithm' => strtoupper($algorithm),
      ]),
    };

    $details = [
      $this->t('Destination URL: @url', ['@url' => $subscription->getUrl()]),
      $this->t('HTTP Status: @status', ['@status' => $result->httpStatus ?? $this->t('N/A')]),
      $this->t('HMAC: @info', ['@info' => $hmacInfo]),
    ];

    foreach ($details as $detail) {
      $this->messenger()->addStatus($detail);
    }

    if ($result->responseBody !== NULL && $result->responseBody !== '') {
      $this->messenger()->addStatus(
        $this->t('Response body: @body', ['@body' => $result->responseBody]),
      );
    }
  }

  /**
   * Builds a payload preview for the given entity.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string $entityId
   *   The entity ID.
   * @param \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $subscription
   *   The subscription to build the payload for.
   *
   * @return array<string, mixed>|null
   *   The payload array, or NULL if the entity could not be loaded.
   */
  private function buildPayloadPreview(
    string $entityTypeId,
    string $entityId,
    OutboundSubscriptionInterface $subscription,
  ): ?array {
    $entity = $this->entityTypeManager->getStorage($entityTypeId)->load($entityId);
    if ($entity === NULL) {
      return NULL;
    }

    return $this->payloadBuilder->build($entity, $subscription);
  }

  /**
   * Returns an options array of recent entities for the given entity type.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string|null $bundle
   *   Optional bundle filter.
   *
   * @return array<string, string>
   *   Keyed by entity ID, valued by entity label.
   */
  private function getRecentEntityOptions(string $entityTypeId, ?string $bundle): array {
    try {
      $storage = $this->entityTypeManager->getStorage($entityTypeId);
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);

      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->range(0, self::RECENT_ENTITIES_LIMIT);

      if ($bundle !== NULL) {
        $bundleKey = $entityType->getKey('bundle');
        if ($bundleKey) {
          $query->condition($bundleKey, $bundle);
        }
      }

      $idKey = $entityType->getKey('id');
      if ($idKey) {
        $query->sort($idKey, 'DESC');
      }

      $ids = $query->execute();

      if (empty($ids)) {
        return [];
      }

      $entities = $storage->loadMultiple($ids);
      $options = [];

      foreach ($entities as $entity) {
        $label = $entity->label() ?? $entity->id();
        $options[(string) $entity->id()] = $label . ' (' . $entity->id() . ')';
      }

      return $options;
    }
    catch (\Exception) {
      return [];
    }
  }

  /**
   * Resolves the OutboundSubscription from the current route parameter.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface|null
   *   The subscription entity, or NULL if not in the route.
   */
  private function resolveSubscription(): ?OutboundSubscriptionInterface {
    $subscription = $this->routeMatch->getParameter('outbound_subscription');

    return $subscription instanceof OutboundSubscriptionInterface ? $subscription : NULL;
  }

  /**
   * Resolves the OutboundEndpoint from the current route parameter.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null
   *   The endpoint entity, or NULL if not in the route.
   */
  private function resolveEndpoint(): ?OutboundEndpointInterface {
    $endpoint = $this->routeMatch->getParameter('outbound_endpoint');

    return $endpoint instanceof OutboundEndpointInterface ? $endpoint : NULL;
  }

}
