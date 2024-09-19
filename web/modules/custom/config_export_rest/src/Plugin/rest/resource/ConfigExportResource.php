<?php

namespace Drupal\config_export_rest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;

/**
 * Provides a Config Export REST Resource.
 *
 * @RestResource(
 *   id = "config_export_rest_resource",
 *   label = @Translation("Config Export REST Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/config-export/{config_name}",
 *     "https://www.drupal.org/link-relations/create" = "/api/config-export"
 *   }
 * )
 */
class ConfigExportResource extends ResourceBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new ConfigExportResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, [], $logger);
    $this->configFactory = $config_factory;
  }

  /**
   * Responds to GET requests.
   *
   * @param string $config_name
   *   The configuration name passed in the URL.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the configuration data.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws exception if the user does not have permission.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Throws exception if the configuration is not found.
   */
  public function get($config_name = null) {
    // Ensure only admins can access this resource.
    $account = \Drupal::currentUser();
    if (!$account->hasPermission('administer site configuration')) {
      throw new AccessDeniedHttpException();
    }

    // Load the selected configurations from the config_export_rest settings.
    $selected_configs = \Drupal::config('config_export_rest.settings')->get('export_configurations') ?: [];

    // Check if the requested configuration is in the selected configurations.
    if (!in_array($config_name, $selected_configs)) {
      throw new AccessDeniedHttpException('The requested configuration does not exist.');
    }

    // Check if the config name exists and retrieve the config.
    $config = $this->configFactory->get($config_name);
    if ($config) {
      $config_data = $config->getRawData();
      return new ResourceResponse($config_data);
    }
    else {
      throw new NotFoundHttpException('The requested configuration does not exist.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('config_export_rest'),  // Inject logger service.
      $container->get('config.factory')  // Inject config factory service.
    );
  }

}
