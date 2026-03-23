<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity_webhook\Traits\ConfigEntityFormTrait;
use Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the add/edit form for OutboundSubscription config entities.
 */
class OutboundSubscriptionForm extends EntityForm {
  use ConfigEntityFormTrait;

  /**
   * Constructs an OutboundSubscriptionForm.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    RouteMatchInterface $routeMatch,
  ) {
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $entity */
    $entity = $this->entity;

    $form += $this->buildLabelIdElements(
      '\Drupal\entity_webhook_broadcast\Entity\OutboundSubscription::load',
      $entity,
    );

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Destination URL'),
      '#description' => $this->t('The URL to POST webhook payloads to. Must be a valid HTTP or HTTPS URL.'),
      '#default_value' => $entity->getUrl(),
      '#required' => TRUE,
      '#maxlength' => 2048,
    ];

    $form['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('HMAC Secret'),
      '#description' => $this->t('The shared secret used to sign outbound webhook payloads. Leave empty to disable signing.'),
      '#default_value' => $entity->getSecret(),
      '#maxlength' => 255,
    ];

    $form['signing_algorithm'] = [
      '#type' => 'select',
      '#title' => $this->t('Signing Algorithm'),
      '#description' => $this->t('The HMAC algorithm used to sign the request body.'),
      '#options' => [
        'sha256' => $this->t('SHA-256 (recommended)'),
        'sha1' => $this->t('SHA-1'),
        'none' => $this->t('None (no signing)'),
      ],
      '#default_value' => $entity->getSigningAlgorithm(),
      '#required' => TRUE,
    ];

    $form['retry'] = [
      '#type' => 'details',
      '#title' => $this->t('Retry Policy'),
      '#open' => TRUE,
    ];

    $form['retry']['retry_max_attempts'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Retry Attempts'),
      '#description' => $this->t('Number of total delivery attempts before the item is abandoned.'),
      '#default_value' => $entity->getRetryMaxAttempts(),
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 20,
    ];

    $form['retry']['retry_base_delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Base Retry Delay (seconds)'),
      '#description' => $this->t('Base delay for exponential backoff. Delay doubles with each attempt.'),
      '#default_value' => $entity->getRetryBaseDelay(),
      '#required' => TRUE,
      '#min' => 1,
    ];

    $form['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#description' => $this->t('When inactive, this subscription will not receive dispatched webhooks.'),
      '#default_value' => $entity->isActive(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundSubscriptionInterface $entity */
    $entity = $this->entity;

    $endpoint = $this->resolveEndpoint();
    if ($entity->isNew() && $endpoint !== NULL) {
      $entity->set('endpoint_id', $endpoint->id());
    }

    $status = parent::save($form, $form_state);

    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Subscription %label has been created.', ['%label' => $entity->label()])
        : $this->t('Subscription %label has been updated.', ['%label' => $entity->label()]),
    );

    if ($endpoint !== NULL) {
      $form_state->setRedirectUrl($entity->toUrl('collection'));
    }

    return $status;
  }

  /**
   * Resolves the parent OutboundEndpoint from the current route parameter.
   *
   * @return \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface|null
   *   The endpoint entity, or NULL if not in the route.
   */
  protected function resolveEndpoint(): ?OutboundEndpointInterface {
    $endpoint = $this->routeMatch->getParameter('outbound_endpoint');

    return $endpoint instanceof OutboundEndpointInterface ? $endpoint : NULL;
  }

}
