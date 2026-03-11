<?php

declare(strict_types=1);

namespace Drupal\entity_webhook\Traits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\NestedArray;

/**
 * Provides a helper method for resolving form values during AJAX rebuilds.
 */
trait AjaxFormStateTrait {
  /**
   * Gets a form value checking user input first for AJAX compatibility.
   *
   * During AJAX rebuilds, form state values are not yet processed, so raw user
   * input must be checked first. This ensures dependent form elements are
   * correctly populated during the initial build, AJAX rebuild, validation,
   * and submission phases.
   *
   * @param string|array $key
   *   The form element key, or an array of keys for nested values.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param mixed $default
   *   The default value to return if the key is not found.
   *
   * @return mixed
   *   The resolved value, or the default if not found.
   */
  protected function getFormStateValue(
    string|array $key,
    FormStateInterface $form_state,
    mixed $default = NULL,
  ): mixed {
    $key = is_array($key) ? $key : [$key];

    $sources = [
      $form_state->getUserInput() ?? [],
      $form_state->getValues() ?? [],
    ];

    foreach ($sources as $source) {
      $key_exists = FALSE;
      $value = NestedArray::getValue($source, $key, $key_exists);
      if ($key_exists) {
        return $value;
      }
    }

    return $default;
  }
}
