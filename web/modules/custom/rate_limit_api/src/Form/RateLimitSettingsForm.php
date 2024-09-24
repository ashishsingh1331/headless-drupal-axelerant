<?php

namespace Drupal\rate_limit_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure rate limit API settings for this site.
 */
class RateLimitSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['rate_limit_api.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rate_limit_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('rate_limit_api.settings');

    $form['limit_per_minute'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit Per Minute'),
      '#default_value' => $config->get('limit_per_minute') ?? 60,
      '#description' => $this->t('Set the number of allowed requests per minute per IP address.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('rate_limit_api.settings')
      ->set('limit_per_minute', $form_state->getValue('limit_per_minute'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
