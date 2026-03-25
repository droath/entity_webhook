# Entity Webhook Broadcast

Provides outbound webhook broadcasting for entity CRUD events with queue-based
asynchronous delivery, HMAC signing, and exponential backoff retry. This
submodule extends the Entity Webhook module by enabling Drupal to notify
external services when entities are created, updated, or deleted.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/entity_webhook).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/entity_webhook).


## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Extending](#extending)
- [Troubleshooting](#troubleshooting)
- [Maintainers](#maintainers)


## Requirements

This module requires the following:

- [Entity Webhook](https://www.drupal.org/project/entity_webhook) (parent
  module)


## Installation

Enable the module after installing the parent Entity Webhook module:

```bash
drush en entity_webhook_broadcast
```


## Configuration

1. Navigate to **Administration > Configuration > Services > Entity Webhook
   Broadcast** (`/admin/config/services/entity-webhook-broadcast`).
2. Click **Add outbound endpoint**.
3. Select the entity type and bundle to watch for CRUD events.
4. Optionally configure conditions to filter which events trigger broadcasts.
5. Save the outbound endpoint.

### Adding Subscriptions

Each outbound endpoint can have one or more subscriptions (destination URLs).

1. From the outbound endpoint, navigate to **Subscriptions** and click
   **Add subscription**.
2. Enter the destination webhook URL.
3. Configure HMAC signing:
   - Select the signing algorithm.
   - Enter the shared secret.
4. Configure retry settings:
   - Maximum number of retry attempts.
   - Base delay in seconds for exponential backoff.
5. Save the subscription.

### Mapping Outbound Fields

Define which entity data is included in the outbound JSON payload.

1. From a subscription, navigate to **Field mappings** and click
   **Add field mapping**.
2. Select an outbound value resolver:
   - **Entity Field** — Extracts the value of an entity field directly.
   - **Entity Reference Field** — Extracts data from a referenced entity.
   - **Static Value** — Includes a hardcoded value.
3. Optionally apply a field value mutation to transform the value before
   sending.
4. Save the field mapping.

The "Administer Entity Webhook" permission is required to manage broadcast
configuration.


## How It Works

1. When an entity matching an outbound endpoint's configuration is created,
   updated, or deleted, the outbound dispatcher is triggered.
2. The dispatcher evaluates the endpoint's conditions to determine if the
   event should be broadcast.
3. For each matching subscription, a delivery task is queued for asynchronous
   processing.
4. During cron, the queue worker builds the JSON payload from the configured
   field mappings and value resolvers.
5. The payload is sent as an HTTP POST request to the subscription's
   destination URL, signed with the configured HMAC algorithm and secret.
6. The delivery result is logged in the
   `entity_webhook_broadcast_outbound_delivery_log` database table.
7. Failed deliveries are retried with exponential backoff up to the configured
   maximum number of attempts.


## Extending

### Outbound Value Resolver Plugins

Create custom value resolvers to extract entity data in specialized ways.
Implement an outbound value resolver plugin whose `resolve()` method receives
the entity and returns the extracted value for inclusion in the outbound
payload.

### Condition Plugins

Use Drupal's condition plugin system to control which entity events trigger
outbound broadcasts. Conditions are configured per outbound endpoint.


## Troubleshooting

- **Broadcasts are not being sent:** Deliveries are processed asynchronously
  during cron. Verify that cron is running. Check the delivery log table for
  status information.
- **Destination service reports invalid signature:** Verify that the HMAC
  shared secret and algorithm configured in the subscription match the
  receiving service's expectations.
- **Too many retries:** Adjust the maximum retry attempts and base delay in
  the subscription configuration. Check the delivery log for specific error
  responses.


## Maintainers

- Travis Tomka - [droath](https://www.drupal.org/u/droath)
