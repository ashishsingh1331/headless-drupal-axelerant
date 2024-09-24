<?php

namespace Drupal\rate_limit_api\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Subscribes to API requests and enforces rate limiting.
 */
class RateLimitSubscriber implements EventSubscriberInterface {

  /**
   * The cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a RateLimitSubscriber object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend interface.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory interface.
   */
  public function __construct(CacheBackendInterface $cache, LoggerChannelFactoryInterface $logger_factory) {
    $this->cache = $cache;
    $this->logger = $logger_factory->get('rate_limit_api');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest'];
    return $events;
  }

  /**
   * Handles rate limiting for the /api/articles path.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    
    // Check if the request path is exactly /api/articles or starts with it.
    if (strpos($path, '/api/') !== 0) {
      return;
    }

    // Add log to track that the rate limit check is being applied.
    $this->logger->info('Rate limiting applied for API request to /api/articles.');

    $ip = $request->getClientIp();

    // Get the rate limit configuration from the settings.
    $config = \Drupal::config('rate_limit_api.settings');
    $limit_per_minute = $config->get('limit_per_minute') ?? 60;

    // Generate a cache ID based on the IP address.
    $cache_id = 'rate_limit:' . $ip;
    $cache = $this->cache->get($cache_id);

    if ($cache) {
      $data = $cache->data;
      $requests = $data['requests'];
      $timestamp = $data['timestamp'];

      if (time() - $timestamp < 60) {
        if ($requests >= $limit_per_minute) {
          // Return a 429 response if the rate limit is exceeded.
          $response = new JsonResponse([
            'error' => 'Rate limit exceeded',
            'retry_after' => 60 - (time() - $timestamp),
          ], Response::HTTP_TOO_MANY_REQUESTS);
          $event->setResponse($response);
          return;
        } else {
          $requests++;
        }
      } else {
        $requests = 1;
        $timestamp = time();
      }
    } else {
      $requests = 1;
      $timestamp = time();
    }

    // Save the updated request count and timestamp to the cache.
    $this->cache->set($cache_id, ['requests' => $requests, 'timestamp' => $timestamp], time() + 60);
  }
}
