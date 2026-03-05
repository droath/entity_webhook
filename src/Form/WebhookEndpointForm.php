<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the add/edit form for WebhookEndpoint config entities.
 */
class WebhookEndpointForm extends EntityForm {

  /**
   * Constructs a WebhookEndpointForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_webhook\Entity\WebhookEndpointInterface $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\entity_webhook\Entity\WebhookEndpoint::load',
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['target_entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Target Entity Type'),
      '#options' => $this->getContentEntityTypeOptions(),
      '#default_value' => $entity->getTargetEntityTypeId(),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $sourceTypeOptions = $this->getSourceTypeOptions();

    $form['source_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Source Types'),
      '#options' => $sourceTypeOptions,
      '#default_value' => $entity->getSourceTypeIds(),
      '#description' => $this->t('Select which source types this endpoint accepts.'),
    ];

    if (empty($sourceTypeOptions)) {
      $form['source_types']['#description'] = $this->t('No source types available. <a href=":url">Create a source type</a> first.', [
        ':url' => '/admin/config/services/entity-webhook/source-types/add',
      ]);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\entity_webhook\Entity\WebhookEndpoint $entity */
    $entity = $this->entity;

    $selectedSourceTypes = array_values(array_filter(
      (array) $form_state->getValue('source_types'),
    ));
    $entity->set('source_types', $selectedSourceTypes);

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
   * Returns an options array of available WebhookSourceType IDs to labels.
   *
   * @return array<string, string>
   *   Keyed by source type ID, valued by label.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getSourceTypeOptions(): array {
    $options = [];
    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface[] $sourceTypes */
    $sourceTypes = $this->entityTypeManager
      ->getStorage('webhook_source_type')
      ->loadMultiple();

    foreach ($sourceTypes as $sourceType) {
      $options[$sourceType->id()] = (string) $sourceType->label();
    }

    return $options;
  }

}
