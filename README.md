# Entity Webhook

Provides webhook ingestion and entity upsert functionality for Drupal content
entities. Receive JSON payloads from external services, extract field values
using JSONPath expressions, and automatically create or update entities — all
through a configuration-driven admin UI.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/entity_webhook).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/entity_webhook).


## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Submodules](#submodules)
- [How It Works](#how-it-works)
- [Extending](#extending)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Maintainers](#maintainers)


## Requirements

This module requires the following:

- PHP 8.3 or higher
- Drupal 10.3 or 11
- [softcreatr/jsonpath](https://github.com/SoftCreatR/JSONPath) ^0.8 —
  JSONPath expression parsing
- [dragonmantank/cron-expression](https://github.com/dragonmantank/cron-expression)
  ^3.3 — Cron expression parsing (used by the Polling submodule)


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

```bash
composer require drupal/entity_webhook
```


## Configuration

1. Navigate to **Administration > Configuration > Services > Entity Webhook**
   (`/admin/config/services/entity-webhook/endpoints`).
2. The "Administer Entity Webhook" permission is required to manage
   configuration. Assign it at **Administration > People > Permissions**.

### Creating an Endpoint

1. Click **Add endpoint**.
2. Enter a label and select the target entity type (e.g., Node, User,
   Commerce Order) and optionally a bundle.
3. Save the endpoint.

### Adding a Source Type

Each endpoint can have one or more source types, representing different payload
formats from external services.

1. From the endpoint's page, navigate to **Source types** and click
   **Add source type**.
2. Enter a label and configure a verification plugin:
   - **HMAC Signature** — Validates a request signature header using a shared
     secret (HMAC-SHA256).
   - **Domain Whitelist** — Restricts requests to specific IP addresses or
     domains.
   - **API Key** — Validates a key sent in a header or query parameter.
3. Save the source type.

### Mapping Fields

1. From a source type, navigate to **Field mappings** and click
   **Add field mapping**.
2. Select the target entity field.
3. Configure the value resolver (how to extract the value from the payload):
   - **JSON Path** — A JSONPath expression (e.g., `$.data.attributes.title`).
   - **Static Value** — A hardcoded value.
   - **JSON Composite** — Combines multiple JSONPath expressions.
4. Optionally select a **field value mutation** plugin to transform the
   extracted value (e.g., `timestamp_format`, `map_values`,
   `price_cents_to_decimal`).
5. Mark one or more mappings as **identifier** fields. These are used to look
   up existing entities for updates. Multiple identifiers create a composite
   key lookup.
6. Save the field mapping.

### Webhook URL

Once configured, external services should POST JSON payloads to:

```
https://example.com/webhook/{endpoint_name}/{source_type}
```

This endpoint is publicly accessible (no Drupal authentication required).
Security is enforced through the configured verification plugins.


## Submodules

### Entity Webhook Polling

Provides scheduled polling of external APIs via cron, feeding data into the
same processing pipeline as real-time webhooks.

**Features:**
- Configurable cron expressions for polling schedules (e.g., `*/15 * * * *`).
- Hash-based change detection to avoid redundant entity updates.
- Pluggable polling provider system for custom API integrations.

**Configuration:**

1. Enable the **Entity Webhook Polling** submodule.
2. Navigate to **Administration > Configuration > Services > Entity Webhook
   Polling** (`/admin/config/services/entity-webhook-polling`).
3. Create a polling configuration, selecting an existing webhook endpoint and
   source type.
4. Set a cron expression to define the polling schedule.
5. Select and configure a polling provider plugin for the external API.

Polling runs during standard Drupal cron. Changed or new records are detected
via SHA-256 hashing and queued for processing.

### Entity Webhook Broadcast

Provides outbound webhook broadcasting when entities are created, updated, or
deleted in Drupal.

**Features:**
- Watches entity CRUD events on configurable entity types and bundles.
- Condition-based event filtering.
- Queue-based asynchronous delivery.
- HMAC signing for outbound request integrity.
- Exponential backoff retry for failed deliveries.
- Delivery logging for auditing.

**Configuration:**

1. Enable the **Entity Webhook Broadcast** submodule.
2. Navigate to **Administration > Configuration > Services > Entity Webhook
   Broadcast** (`/admin/config/services/entity-webhook-broadcast`).
3. Create an outbound endpoint, selecting the entity type and bundle to watch.
4. Add one or more outbound subscriptions with:
   - A destination URL for webhook delivery.
   - HMAC signing configuration (algorithm and shared secret).
   - Retry settings (max attempts and base delay for exponential backoff).
5. Configure outbound field mappings to define the JSON payload structure using
   value resolvers:
   - **Entity Field** — Direct field value extraction.
   - **Entity Reference Field** — Extracts data from referenced entities.
   - **Static Value** — Hardcoded values.


## How It Works

### Inbound Webhook Flow

1. An external service POSTs a JSON payload to
   `/webhook/{endpoint_name}/{source_type}`.
2. The controller validates JSON parsing and runs verification plugins
   synchronously. Invalid requests receive an immediate HTTP 400 or 403.
3. Valid payloads are enqueued for asynchronous processing (HTTP 200 returned).
4. During cron, the queue worker extracts field values using the configured
   resolvers, looks up or creates the target entity, and saves it.
5. Pre-save and post-save events are dispatched, allowing custom subscribers to
   modify the entity or react to the completed upsert.

### Polling Flow

1. Drupal cron evaluates each polling configuration's cron expression.
2. For due configurations, the polling provider fetches data from the external
   API.
3. Fetched records are compared against stored hashes. Only changed or new
   records are queued.
4. Queued records follow the same processing pipeline as inbound webhooks.

### Broadcast Flow

1. An entity lifecycle hook fires (insert, update, or delete).
2. The outbound dispatcher checks for matching subscriptions and evaluates
   endpoint conditions.
3. Matching deliveries are queued for asynchronous processing.
4. During cron, payloads are built from field mappings and sent via HTTP POST
   with HMAC signatures.
5. Failed deliveries are retried with exponential backoff.


## Extending

The module provides several plugin types for custom integrations.

### Webhook Verification Plugins

Create custom request verification logic by implementing a verification plugin
with the `WebhookVerification` attribute. Built-in plugins: `hmac_signature`,
`domain_whitelist`, `api_key`.

### Value Resolver Plugins

Extract values from incoming payloads in custom ways by implementing a value
resolver plugin. Built-in resolvers: `json_path`, `static_value`,
`json_composite`.

### Field Value Mutation Plugins

Transform extracted values before entity field assignment. Built-in mutations:
`array_reshape`, `json_encode`, `map_values`, `price_cents_to_decimal`,
`regex_replace`, `string_replace`, `timestamp_format`.

### Polling Provider Plugins

Implement custom polling providers to fetch data from specific external APIs.
Providers return webhook-like payloads that feed into the standard processing
pipeline.

### Outbound Value Resolver Plugins

Extract entity data for outbound payloads. Built-in resolvers: `entity_field`,
`entity_reference_field`, `static_value`.

### Events

- **`entity_webhook.pre_save`** — Fired before entity save. Subscribers can
  modify the entity or call `abort()` to prevent the save.
- **`entity_webhook.post_save`** — Fired after a successful entity save.
  Subscribers can react to the completed upsert (e.g., trigger workflows).


## Troubleshooting

- **Webhook returns HTTP 403:** The verification plugin is rejecting the
  request. Check that the shared secret, API key, or allowed domains match the
  sending service's configuration.
- **Webhook returns HTTP 400:** The request body is not valid JSON.
- **Webhook returns HTTP 404:** The endpoint name or source type in the URL
  does not match any configuration.
- **Entities are not being created or updated:** Payloads are processed
  asynchronously via Drupal's queue system during cron. Verify that cron is
  running. Check the queue at **Administration > Configuration > System >
  Queue UI** if the `queue_ui` module is installed.
- **Polling is not running:** Verify that the cron expression is valid and that
  Drupal cron is running at a frequency that matches or exceeds the polling
  schedule.


## FAQ

**Q: Which entity types are supported?**

A: Any Drupal content entity type (nodes, users, taxonomy terms, commerce
orders, custom entities, etc.). The target entity type is selected when
creating an endpoint.

**Q: Can I use multiple identifier fields for entity lookup?**

A: Yes. Mark multiple field mappings as "identifier" to create a composite key
lookup. The entity must match all identifier values to be updated rather than
created.

**Q: How are payloads processed?**

A: Verification happens synchronously during the HTTP request. Entity
processing is handled asynchronously via Drupal's queue system during cron for
resilience and performance.

**Q: Can I transform values before they are saved?**

A: Yes. Each field mapping can have an optional field value mutation plugin
applied (e.g., converting timestamps, mapping values via a lookup table, or
regex replacements).


## Maintainers

- Travis Tomka - [droath](https://www.drupal.org/u/droath)
