<?php

namespace Drupal\weather_update\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides a block to display weather data.
 *
 * @Block(
 *   id = "weather_block",
 *   admin_label = @Translation("Weather Block")
 * )
 */
class WeatherBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a WeatherBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the block.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, CacheBackendInterface $cache, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->cache = $cache;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('cache.default'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Retrieve the configuration settings.
    $config = $this->configFactory->get('weather_update.settings');
    $api_key = $config->get('weather_api_key');
    $base_url = $config->get('weather_base_url');
    $city = 'London';  // You can modify the city or make it configurable.
    
    if (!$api_key || !$base_url) {
      return [
        '#markup' => $this->t('API key or base URL is missing from configuration.'),
      ];
    }

    // Check cache for weather data.
    if ($cache = $this->cache->get('weather_update.data')) {
      return $this->renderWeather($cache->data);
    }

    // API request URL.
    $url = "{$base_url}/current.json?key={$api_key}&q={$city}";

    try {
      $response = $this->httpClient->request('GET', $url);
      $data = json_decode($response->getBody()->getContents(), TRUE);

      // Ensure valid response structure.
      if (!isset($data['current'])) {
        throw new \Exception('Invalid weather data received.');
      }

      // Cache the data for 1 hour (3600 seconds).
      $weather = [
        'temperature' => $data['current']['temp_c'],
        'wind' => $data['current']['wind_kph'],
        'precipitation' => $data['current']['precip_mm'],
      ];
      $this->cache->set('weather_update.data', $weather, time() + 3600);

      return $this->renderWeather($weather);

    } catch (\Exception $e) {
      \Drupal::logger('weather_update')->error($e->getMessage());
      return [
        '#markup' => $this->t('Unable to retrieve weather data.'),
      ];
    }
  }

  /**
   * Renders weather data.
   *
   * @param array $weather
   *   The weather data array.
   *
   * @return array
   *   A render array.
   */
  protected function renderWeather(array $weather) {
    return [
      '#theme' => 'weather_update',
      '#temperature' => $weather['temperature'],
      '#wind' => $weather['wind'],
      '#precipitation' => $weather['precipitation'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 3600;
  }

}
