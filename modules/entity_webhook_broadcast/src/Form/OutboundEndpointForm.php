<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_webhook\Traits\AjaxFormStateTrait;
use Drupal\entity_webhook\Traits\ConfigEntityFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the add/edit form for OutboundEndpoint config entities.
 */
class OutboundEndpointForm extends EntityForm {
  use AjaxFormStateTrait;
  use ConfigEntityFormTrait;

  /**
   * Constructs an OutboundEndpointForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The entity type bundle info service.
   */
  public function __construct(
    protected EntityTypeBundleInfoInterface $bundleInfo,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $entity */
    $entity = $this->entity;

    $form['#prefix'] = '<div id="outbound-endpoint-form">';
    $form['#suffix'] = '</div>';

    $form += $this->buildLabelIdElements(
      '\Drupal\entity_webhook_broadcast\Entity\OutboundEndpoint::load',
      $entity,
    );

    $selectedEntityType = (string) $this->getFormStateValue(
      'entity_type',
      $form_state,
      $entity->getWatchedEntityType(),
    );

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#options' => $this->getContentEntityTypeOptions(),
      '#default_value' => $selectedEntityType,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
      '#ajax' => [
        'method' => 'replaceWith',
        'wrapper' => 'outbound-endpoint-form',
        'callback' => [$this, 'ajaxRebuildForm'],
      ],
    ];

    if ($selectedEntityType !== '') {
      $bundleOptions = $this->getBundleOptions($selectedEntityType);
      $selectedBundle = (string) $this->getFormStateValue(
        'entity_bundle',
        $form_state,
        $entity->getEntityBundle() ?? '',
      );
      $form['entity_bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Entity Bundle'),
        '#description' => $this->t('Optionally limit to a specific bundle. Leave empty to match all bundles.'),
        '#options' => $bundleOptions,
        '#empty_option' => $this->t('- All bundles -'),
        '#empty_value' => '',
        '#default_value' => $selectedBundle,
      ];
    }

    $form['events'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Events'),
      '#description' => $this->t('Select the entity lifecycle events that will trigger dispatch.'),
      '#options' => [
        'insert' => $this->t('Insert (entity created)'),
        'update' => $this->t('Update (entity saved)'),
        'delete' => $this->t('Delete (entity deleted)'),
      ],
      '#default_value' => $entity->getEvents(),
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('When disabled, no webhooks are dispatched for this endpoint.'),
      '#default_value' => $entity->isEnabled(),
    ];

    return $form;
  }

  /**
   * AJAX callback that returns the entire form for a full rebuild.
   *
   * @param array<string, mixed> $form
   *   The form array.
   *
   * @return array<string, mixed>
   *   The entire form element.
   */
  public function ajaxRebuildForm(array $form): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $events = array_keys(array_filter((array) $form_state->getValue('events')));
    $form_state->setValue('events', $events);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\entity_webhook_broadcast\Entity\OutboundEndpointInterface $entity */
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Endpoint %label has been created.', ['%label' => $entity->label()])
        : $this->t('Endpoint %label has been updated.', ['%label' => $entity->label()]),
    );

    $form_state->setRedirectUrl($entity->toUrl('collection'));

    return $status;
  }

  /**
   * Returns an options array of content entity type IDs to labels.
   *
   * @return array<string, string>
   *   Keyed by entity type ID, valued by label.
   */
  protected function getContentEntityTypeOptions(): array {
    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $id => $definition) {
      if ($definition->getGroup() === 'content') {
        $options[$id] = (string) $definition->getLabel();
      }
    }
    asort($options);

    return $options;
  }

  /**
   * Returns an options array of bundle IDs to labels for a given entity type.
   *
   * @param string $entityTypeId
   *   The entity type machine name.
   *
   * @return array<string, string>
   *   Keyed by bundle ID, valued by label string.
   */
  protected function getBundleOptions(string $entityTypeId): array {
    return array_map(
      static fn($bundleData) => (string) $bundleData['label'],
      $this->bundleInfo->getBundleInfo($entityTypeId),
    );
  }

}
