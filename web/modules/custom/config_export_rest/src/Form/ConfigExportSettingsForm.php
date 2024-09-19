<?php

namespace Drupal\config_export_rest\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures export settings for Config Export REST API.
 */
class ConfigExportSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['config_export_rest.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_export_rest_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('config_export_rest.settings');

    // Get a list of available configurations.
    $config_storage = \Drupal::service('config.storage');
    $all_config_names = $config_storage->listAll(); // List all configurations.

    // Replace dots with underscores for safe storage and display.
    $safe_config_names = array_map(function($name) {
      return str_replace('.', '_', $name);
    }, $all_config_names);

    // Get previously selected configurations from the config, and convert dots to underscores for comparison.
    $selected_configs = $config->get('export_configurations') ?: [];
    $selected_configs_safe = array_map(function($name) {
      return str_replace('.', '_', $name);
    }, $selected_configs);

    // Create checkboxes for each configuration.
    $form['export_configurations'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select Configurations to Export'),
      '#options' => array_combine($safe_config_names, $safe_config_names),
      '#default_value' => $selected_configs_safe, // Set previously selected values.
      '#description' => $this->t('Select the configurations to be exposed via the REST API.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Filter out unselected checkboxes (values that are empty).
    $selected_configs = array_filter($form_state->getValue('export_configurations'));
  
    // Convert underscores back to dots before saving.
    $selected_configs = array_map(function($name) {
      return str_replace('_', '.', $name);
    }, $selected_configs);
  
    // Log the selected configurations to verify what's being saved.
    \Drupal::logger('config_export_rest')->info('Selected configurations: @configs', ['@configs' => print_r($selected_configs, TRUE)]);
  
    // Save the selected configurations.
    $this->config('config_export_rest.settings')
      ->set('export_configurations', $selected_configs)
      ->save();
  
    // Clear relevant caches to ensure changes take effect immediately.
    drupal_flush_all_caches();  // Clears all caches. Alternatively, you can use targeted cache invalidation.
  
    parent::submitForm($form, $form_state);
  }
  
  

}
