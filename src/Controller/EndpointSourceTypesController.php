<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for managing source types on a webhook endpoint.
 */
final class EndpointSourceTypesController extends ControllerBase {
  /**
   * Constructs an EndpointSourceTypesController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Returns the page title for the source types list.
   *
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface $webhook_endpoint
   *   The webhook endpoint.
   *
   * @return string
   *   The translated page title including the endpoint label.
   */
  public function getTitle(WebhookEndpointInterface $webhook_endpoint): string {
    return (string) $this->t('Source types for @endpoint', [
      '@endpoint' => $webhook_endpoint->label(),
    ]);
  }

  /**
   * Lists source types assigned to the given endpoint.
   *
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface $webhook_endpoint
   *   The webhook endpoint.
   *
   * @return array<string, mixed>
   *   A render array for the source types table.
   */
  public function listSourceTypes(WebhookEndpointInterface $webhook_endpoint): array {
    $storage = $this->entityTypeManager->getStorage('webhook_source_type');
    $rows = [];

    foreach ($webhook_endpoint->getSourceTypeIds() as $sourceTypeId) {
      $sourceType = $storage->load($sourceTypeId);
      if (!$sourceType instanceof WebhookSourceTypeInterface) {
        continue;
      }
      $rows[] = $this->buildSourceTypeRow($webhook_endpoint, $sourceType);
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Label'),
        $this->t('Machine name'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No source types configured for this endpoint. <a href=":url">Add a source type</a>.', [
        ':url' => Url::fromRoute('entity.webhook_endpoint.source_types.add', [
          'webhook_endpoint' => $webhook_endpoint->id(),
        ])->toString(),
      ]),
    ];
  }

  /**
   * Builds a single table row for a source type.
   *
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface $endpoint
   *   The webhook endpoint.
   * @param \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface $sourceType
   *   The source type entity.
   *
   * @return array<string, mixed>
   *   The table row array.
   */
  private function buildSourceTypeRow(WebhookEndpointInterface $endpoint, WebhookSourceTypeInterface $sourceType): array {
    $sourceTypeId = $sourceType->id();

    return [
      'label' => $sourceType->label(),
      'id' => $sourceTypeId,
      'operations' => [
        'data' => [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute('entity.webhook_source_type.edit_form', [
                'webhook_endpoint' => $endpoint->id(),
                'webhook_source_type' => $sourceTypeId,
              ]),
              'weight' => 0,
            ],
            'manage_field_mappings' => [
              'title' => $this->t('Manage field mappings'),
              'url' => Url::fromRoute('entity.webhook_field_mapping.collection', [
                'webhook_endpoint' => $endpoint->id(),
                'webhook_source_type' => $sourceTypeId,
              ]),
              'weight' => 5,
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.webhook_endpoint.source_types.remove', [
                'webhook_endpoint' => $endpoint->id(),
                'source_type' => $sourceTypeId,
              ]),
              'weight' => 10,
            ],
          ],
        ],
      ],
    ];
  }
}
