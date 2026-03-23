<?php

declare(strict_types=1);

namespace Drupal\entity_webhook_broadcast\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the OutboundDeliveryLog content entity.
 *
 * Records every webhook delivery attempt for audit and retry management.
 * A log entry is created with status 'pending' at dispatch time. The queue
 * worker updates it to 'success', 'failed', or 'abandoned' after each attempt.
 * The cron retry processor queries 'pending' entries whose next_retry_at has
 * passed and re-enqueues them for processing.
 */
#[ContentEntityType(
  id: 'outbound_delivery_log',
  label: new TranslatableMarkup('Outbound Delivery Log'),
  label_collection: new TranslatableMarkup('Outbound Delivery Logs'),
  label_singular: new TranslatableMarkup('outbound delivery log'),
  label_plural: new TranslatableMarkup('outbound delivery logs'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => OutboundDeliveryLogListBuilder::class,
    'views_data' => OutboundDeliveryLogViewsData::class,
  ],
  admin_permission: 'administer entity_webhook_broadcast',
  base_table: 'outbound_delivery_log',
  label_count: [
    'singular' => '@count outbound delivery log',
    'plural' => '@count outbound delivery logs',
  ],
)]
class OutboundDeliveryLog extends ContentEntityBase implements OutboundDeliveryLogInterface, EntityChangedInterface
{

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array
  {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['subscription'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Subscription'))
      ->setDescription(new TranslatableMarkup('The OutboundSubscription config entity ID.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Entity Type'))
      ->setDescription(new TranslatableMarkup('The source Drupal entity type machine name.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['entity_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Entity ID'))
      ->setDescription(new TranslatableMarkup('The source entity ID.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['event'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Event'))
      ->setDescription(new TranslatableMarkup("The CRUD event name: 'insert', 'update', or 'delete'."))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32);

    $fields['payload_hash'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Payload Hash'))
      ->setDescription(new TranslatableMarkup('SHA-256 hash of the serialized payload.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64);

    $fields['attempt'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Attempt'))
      ->setDescription(new TranslatableMarkup('The current delivery attempt number (1-based).'))
      ->setRequired(TRUE)
      ->setDefaultValue(1);

    $fields['max_attempts'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Max Attempts'))
      ->setDescription(new TranslatableMarkup('Maximum delivery attempts, copied from the subscription.'))
      ->setRequired(TRUE)
      ->setDefaultValue(5);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setDescription(new TranslatableMarkup("Delivery status: 'pending', 'success', 'failed', or 'abandoned'."))
      ->setRequired(TRUE)
      ->setSetting('max_length', 32)
      ->setDefaultValue('pending');

    $fields['http_status'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('HTTP Status'))
      ->setDescription(new TranslatableMarkup('The HTTP response status code from the last attempt.'))
      ->setRequired(FALSE);

    $fields['payload'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Payload'))
      ->setDescription(new TranslatableMarkup('JSON-encoded webhook payload for retry re-enqueue.'))
      ->setRequired(FALSE);

    $fields['response_body'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Response Body'))
      ->setDescription(new TranslatableMarkup('The first 1KB of the response body from the last attempt.'))
      ->setRequired(FALSE);

    $fields['next_retry_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Next Retry At'))
      ->setDescription(new TranslatableMarkup('Unix timestamp when this delivery should be retried.'))
      ->setRequired(FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('When this delivery log was first created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Changed'))
      ->setDescription(new TranslatableMarkup('When this delivery log was last updated.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscriptionId(): string
  {
    return (string)$this->get('subscription')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityType(): string
  {
    return (string)$this->get('entity_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityId(): string
  {
    return (string)$this->get('entity_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getEvent(): string
  {
    return (string)$this->get('event')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPayloadHash(): string
  {
    return (string)$this->get('payload_hash')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPayload(): array
  {
    $value = $this->get('payload')->value;
    if ($value === NULL || $value === '') {
      return [];
    }
    try {
      return (array)json_decode($value, TRUE, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setPayload(array $payload): static
  {
    try {
      $this->set('payload', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    } catch (\JsonException) {
      $this->set('payload', NULL);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttempt(): int
  {
    return (int)$this->get('attempt')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxAttempts(): int
  {
    return (int)$this->get('max_attempts')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string
  {
    return (string)$this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpStatus(): ?int
  {
    $value = $this->get('http_status')->value;
    return $value !== NULL ? (int)$value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseBody(): ?string
  {
    $value = $this->get('response_body')->value;
    return $value !== NULL ? (string)$value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextRetryAt(): ?int
  {
    $value = $this->get('next_retry_at')->value;
    return $value !== NULL ? (int)$value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int
  {
    return (int)$this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $status): static
  {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHttpStatus(?int $httpStatus): static
  {
    $this->set('http_status', $httpStatus);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setResponseBody(?string $responseBody): static
  {
    $this->set('response_body', $responseBody);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setNextRetryAt(?int $timestamp): static
  {
    $this->set('next_retry_at', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function incrementAttempt(): static
  {
    $this->set('attempt', $this->getAttempt() + 1);
    return $this;
  }

}
