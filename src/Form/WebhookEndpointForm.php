<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_webhook\Traits\AjaxFormStateTrait;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\entity_webhook\Traits\ConfigEntityFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the add/edit form for WebhookEndpoint config entities.
 */
class WebhookEndpointForm extends EntityForm {
  use AjaxFormStateTrait;
  use ConfigEntityFormTrait;

  /**
   * Constructs a WebhookEndpointForm.
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

    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $entity */
    $entity = $this->entity;

    $form['#prefix'] = '<div id="webhook-endpoint-form">';
    $form['#suffix'] = '</div>';

    $form += $this->buildLabelIdElements(
      '\Drupal\entity_webhook\Entity\WebhookEndpoint::load',
      $entity,
    );

    $target_entity_type = $entity->getTargetEntityTypeId();

    $form['target_entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Target Entity Type'),
      '#options' => $this->getContentEntityTypeOptions(),
      '#default_value' => $target_entity_type,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
      '#ajax' => [
        'method' => 'replaceWith',
        'wrapper' => 'webhook-endpoint-form',
        'callback' => [$this, 'ajaxRebuildForm'],
      ],
    ];

    $selectedEntityType = (string) $this->getFormStateValue(
      'target_entity_type',
      $form_state,
      $entity->getTargetEntityTypeId(),
    );

    if (!empty($selectedEntityType)) {
      $bundleOptions = $this->getBundleOptions($selectedEntityType);
      $selectedBundle = $this->getFormStateValue(
        'target_entity_bundle',
        $form_state,
        $entity->getTargetEntityBundle(),
      );
      $form['target_entity_bundle'] = [
        '#type' => 'select',
        '#required' => TRUE,
        '#title' => $this->t('Target Bundle'),
        '#description' => $this->t(
          'Optionally limit webhook processing to a specific bundle.',
        ),
        '#options' => $bundleOptions,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $selectedBundle,
      ];
    }

    return $form;
  }

  /**
   * AJAX callback that returns the entire form.
   *
   * Returning the full form (instead of just the bundle wrapper) ensures
   * that Drupal rebuilds and revalidates the form with fresh values, which
   * prevents stale bundle values from causing validation errors.
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
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\entity_webhook\Entity\WebhookEndpoint $entity */
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
   *   Keyed by bundle ID, valued by label string. Empty if no entity type
   *   given.
   */
  protected function getBundleOptions(string $entityTypeId): array {
    return array_map(
      static function ($bundleData) {
        return (string) $bundleData['label'];
      },
      $this->bundleInfo->getBundleInfo($entityTypeId),
    );
  }
}
