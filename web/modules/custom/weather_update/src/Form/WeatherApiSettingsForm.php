<?php

namespace Drupal\weather_update\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class WeatherApiSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['weather_update.settings'];
  }

  public function getFormId() {
    return 'weather_api_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('weather_update.settings');

    $form['weather_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Weather API Key'),
      '#default_value' => $config->get('weather_api_key'),
      '#description' => $this->t('Enter your WeatherAPI.com API key.'),
      '#required' => TRUE,
    ];

    $form['weather_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Weather API Base URL'),
      '#default_value' => $config->get('weather_base_url') ?? 'https://api.weatherapi.com/v1',
      '#description' => $this->t('Enter the base URL for WeatherAPI.com. Default is https://api.weatherapi.com/v1'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('weather_update.settings')
      ->set('weather_api_key', $form_state->getValue('weather_api_key'))
      ->set('weather_base_url', $form_state->getValue('weather_base_url'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
