<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_webhook\Kernel\Plugin;

use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationInterface;
use Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManager;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the FieldValueMutationManager plugin manager.
 *
 * @group entity_webhook
 */
class FieldValueMutationManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_webhook'];

  /**
   * Tests that the plugin manager service is registered and accessible.
   */
  public function testPluginManagerServiceIsRegistered(): void {
    $manager = $this->container->get('plugin.manager.field_value_mutation');

    $this->assertSame(FieldValueMutationManager::class, get_class($manager));
  }

  /**
   * Tests that the plugin manager discovers the two bundled mutation plugins.
   */
  public function testPluginManagerDiscoversBundledPlugins(): void {
    /** @var \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManager $manager */
    $manager = $this->container->get('plugin.manager.field_value_mutation');

    $definitions = $manager->getDefinitions();

    $this->assertArrayHasKey('string_replace', $definitions);
    $this->assertArrayHasKey('regex_replace', $definitions);
  }

  /**
   * Tests that getOptions() returns an associative array of plugin IDs to labels.
   */
  public function testGetOptionsReturnsAssociativeArrayOfPluginIdsToLabels(): void {
    /** @var \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.field_value_mutation');

    $options = $manager->getOptions();

    $this->assertIsArray($options);
    $this->assertNotEmpty($options);

    foreach ($options as $pluginId => $label) {
      $this->assertIsString($pluginId, 'Each key must be a string plugin ID.');
      $this->assertIsString($label, 'Each value must be a plain string label.');
    }
  }

  /**
   * Tests that getOptions() returns labels as plain strings, not TranslatableMarkup objects.
   */
  public function testGetOptionsLabelsArePlainStrings(): void {
    /** @var \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.field_value_mutation');

    $options = $manager->getOptions();

    foreach ($options as $label) {
      $this->assertNotInstanceOf(
        \Drupal\Core\StringTranslation\TranslatableMarkup::class,
        $label,
        'Labels must be cast to plain strings by getOptions().',
      );
    }
  }

  /**
   * Tests that getOptions() returns options sorted alphabetically by label.
   */
  public function testGetOptionsIsSortedAlphabeticallyByLabel(): void {
    /** @var \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.field_value_mutation');

    $options = $manager->getOptions();
    $labels = array_values($options);
    $sorted = $labels;
    sort($sorted);

    $this->assertSame($sorted, $labels, 'Labels must be sorted alphabetically.');
  }

  /**
   * Tests that createInstance returns a usable FieldValueMutationInterface plugin.
   */
  public function testCreateInstanceReturnsPluginInstance(): void {
    /** @var \Drupal\entity_webhook\Plugin\FieldValueMutation\FieldValueMutationManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.field_value_mutation');

    $plugin = $manager->createInstance('string_replace', [
      'search' => 'foo',
      'replace' => 'bar',
    ]);

    $this->assertInstanceOf(FieldValueMutationInterface::class, $plugin);
    $this->assertSame('bar baz', $plugin->mutate('foo baz'));
  }

}
