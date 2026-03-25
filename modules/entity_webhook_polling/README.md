# Entity Webhook Polling

Provides polling-based webhook ingestion for entity operations via configurable
cron expressions and provider plugins. This submodule extends the Entity
Webhook module by allowing Drupal to actively fetch data from external APIs on
a schedule, rather than waiting for inbound webhook requests.

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
- [Maintainers](#maintainers)


## Requirements

This module requires the following:

- [Entity Webhook](https://www.drupal.org/project/entity_webhook) (parent
  module)
- [dragonmantank/cron-expression](https://github.com/dragonmantank/cron-expression)
  ^3.3 — included as a dependency of the parent module


## Installation

Enable the module after installing the parent Entity Webhook module:

```bash
drush en entity_webhook_polling
```


## Configuration

1. Ensure at least one webhook endpoint and source type exist in the parent
   Entity Webhook module.
2. Navigate to **Administration > Configuration > Services > Entity Webhook
   Polling** (`/admin/config/services/entity-webhook-polling`).
3. Click **Add polling configuration**.
4. Select the webhook endpoint and source type to poll for.
5. Enter a cron expression defining the polling schedule (e.g.,
   `*/15 * * * *` for every 15 minutes).
6. Select and configure a polling provider plugin for the external API.
7. Save the configuration.

The "Administer Entity Webhook" permission is required to manage polling
configuration.


## How It Works

1. During each Drupal cron run, the polling manager evaluates all polling
   configurations against their cron expressions.
2. For configurations that are due, the configured polling provider plugin
   fetches data from the external API.
3. Each fetched record is hashed (SHA-256) and compared against previously
   stored hashes in the `entity_webhook_polling_state` database table.
4. Only changed or new records are queued for processing.
5. Queued records are processed through the same pipeline as inbound webhooks
   (value extraction, entity lookup/creation, field assignment, save).

This approach avoids redundant entity updates when external data has not
changed.


## Extending

### Polling Provider Plugins

Create custom polling providers to integrate with specific external APIs.
Polling providers are responsible for fetching data and returning it as an
array of webhook-like payloads.

Implement a polling provider plugin using the Drupal plugin system with the
appropriate attribute. The provider's `fetch()` method should return an array
of payloads that match the field mapping configuration of the associated source
type.


## Maintainers

- Travis Tomka - [droath](https://www.drupal.org/u/droath)
