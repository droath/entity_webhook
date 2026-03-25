<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\entity_webhook_broadcast\Form\OutboundEndpointForm;

/**
 * Defines the OutboundEndpoint config entity.
 *
 * An OutboundEndpoint watches a specific Drupal entity type, optional bundle,
 * and set of CRUD events. It acts as the top-level parent in the three-tier
 * hierarchy: OutboundEndpoint → OutboundSubscription → OutboundFieldMapping.
 */
#[ConfigEntityType(
  id: 'outbound_endpoint',
  label: new TranslatableMarkup('Outbound Endpoint'),
  label_collection: new TranslatableMarkup('Outbound Endpoints'),
  label_singular: new TranslatableMarkup('outbound endpoint'),
  label_plural: new TranslatableMarkup('outbound endpoints'),
  config_prefix: 'outbound_endpoint',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'status' => 'status',
  ],
  handlers: [
    'list_builder' => OutboundEndpointListBuilder::class,
    'form' => [
      'add' => OutboundEndpointForm::class,
      'edit' => OutboundEndpointForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => ['html' => AdminHtmlRouteProvider::class],
  ],
  links: [
    'add-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/add',
    'edit-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/edit',
    'delete-form' => '/admin/config/services/entity-webhook/broadcast/endpoints/{outbound_endpoint}/delete',
    'collection' => '/admin/config/services/entity-webhook/broadcast/endpoints',
  ],
  admin_permission: 'administer entity_webhook_broadcast',
  label_count: [
    'singular' => '@count outbound endpoint',
    'plural' => '@count outbound endpoints',
  ],
  config_export: [
    'id',
    'label',
    'status',
    'entity_type',
    'entity_bundle',
    'events',
    'conditions',
  ],
)]
class OutboundEndpoint extends ConfigEntityBase implements OutboundEndpointInterface, EntityWithPluginCollectionInterface
{

  /** The endpoint machine name. */
  protected string $id = '';

  /** The human-readable label. */
  protected string $label = '';

  /** The watched entity type machine name. */
  protected string $entity_type = '';

  /** The watched entity bundle machine name, or empty string for all bundles. */
  protected ?string $entity_bundle = NULL;

  /**
   * The CRUD event names to watch.
   *
   * @var string[]
   */
  protected array $events = [];

  /**
   * The raw condition plugin configuration keyed by instance ID.
   *
   * @var array<string, mixed>
   */
  protected array $conditions = [];

  /**
   * The lazy-loaded condition plugin collection.
   */
  private ?ConditionPluginCollection $conditionCollection = NULL;

  /**
   * The condition plugin manager.
   */
  private ?ExecutableManagerInterface $conditionPluginManager = NULL;

  /**
   * {@inheritdoc}
   */
  public function getWatchedEntityType(): string
  {
    return $this->entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundle(): ?string
  {
    return $this->entity_bundle !== '' ? $this->entity_bundle : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents(): array
  {
    return $this->events;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool
  {
    return (bool) $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions(): ConditionPluginCollection
  {
    if ($this->conditionCollection === NULL) {
      $this->conditionCollection = new ConditionPluginCollection(
        $this->conditionPluginManager(),
        $this->conditions,
      );
    }

    return $this->conditionCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveConditions(): array
  {
    return array_filter($this->conditions, function (array $value): bool {
      unset($value['id'], $value['negate'], $value['context_mapping']);
      return !$this->isArrayEmpty($value);
    });
  }

  /**
   * Recursively checks if an array is empty.
   *
   * @param array<mixed> $array
   *   The array to check.
   *
   * @return bool
   *   TRUE if the array has no meaningful non-empty values.
   */
  private function isArrayEmpty(array $array): bool
  {
    foreach (NestedArray::filter($array) as $value) {
      if (!empty($value)) {
        return FALSE;
      }
      if (is_array($value)) {
        return $this->isArrayEmpty($value);
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array
  {
    return ['conditions' => $this->getConditions()];
  }

  /**
   * Gets the condition plugin manager via lazy static resolution.
   *
   * @return \Drupal\Core\Executable\ExecutableManagerInterface
   *   The condition plugin manager.
   */
  private function conditionPluginManager(): ExecutableManagerInterface
  {
    if ($this->conditionPluginManager === NULL) {
      $this->conditionPluginManager = \Drupal::service('plugin.manager.condition');
    }

    return $this->conditionPluginManager;
  }

}
