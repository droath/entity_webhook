<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_webhook\Entity\WebhookEndpointInterface;
use Drupal\entity_webhook\Entity\WebhookSourceTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form to remove a source type from a webhook endpoint.
 */
final class EndpointSourceTypeRemoveForm extends ConfirmFormBase {
  /** The webhook endpoint being modified. */
  protected ?WebhookEndpointInterface $endpoint = NULL;

  /** The source type machine name to remove. */
  protected string $sourceTypeId = '';

  /** The loaded source type entity, populated in buildForm(). */
  private ?WebhookSourceTypeInterface $sourceType = NULL;

  /**
   * Constructs an EndpointSourceTypeRemoveForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
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
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_webhook_endpoint_source_type_remove';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Remove %source from %endpoint?', [
      '%source' => $this->sourceType?->label() ?? $this->sourceTypeId,
      '%endpoint' => $this->endpoint?->label() ?? '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.webhook_endpoint.source_types', [
      'webhook_endpoint' => $this->endpoint?->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\entity_webhook\Entity\WebhookEndpointInterface|null $webhook_endpoint
   *   The webhook endpoint from the route parameter.
   * @param string|null $source_type
   *   The source type machine name from the route parameter.
   *
   * @return array<string, mixed>
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WebhookEndpointInterface $webhook_endpoint = NULL, ?string $source_type = NULL): array {
    $this->endpoint = $webhook_endpoint;
    $this->sourceTypeId = $source_type ?? '';

    /** @var \Drupal\entity_webhook\Entity\WebhookSourceTypeInterface|null $loadedSourceType */
    $loadedSourceType = $this->entityTypeManager->getStorage('webhook_source_type')->load($this->sourceTypeId);
    $this->sourceType = $loadedSourceType;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->endpoint === NULL) {
      return;
    }

    $this->endpoint->removeSourceType($this->sourceTypeId);
    $this->endpoint->save();

    $this->messenger()->addStatus($this->t('Source type %source has been removed from %endpoint.', [
      '%source' => $this->sourceType?->label() ?? $this->sourceTypeId,
      '%endpoint' => $this->endpoint->label(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }
}
