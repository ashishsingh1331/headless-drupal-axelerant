<?php

namespace Drupal\article_tag_update\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;

/**
 * Provides a REST resource to update article tags via PATCH.
 *
 * @RestResource(
 *   id = "article_tag_update_resource",
 *   label = @Translation("Article Tag Update Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/article/tags/update"
 *   }
 * )
 */
class ArticleTagUpdateResource extends ResourceBase {

  /**
   * Constructs a new ArticleTagUpdateResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, [], $logger);
  }

  /**
   * Responds to PATCH requests to add tags to an article node.
   *
   * @param array $data
   *   The data sent in the request body (node ID and tag names).
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the updated article.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have permission to edit the node.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the specified node ID does not exist or is not an article.
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when the request does not contain valid data (node ID and tags).
   */
  public function patch($data) {
    // Validate the data.
    if (empty($data['nid']) || !is_numeric($data['nid'])) {
      throw new BadRequestHttpException('Node ID (nid) is required and must be a valid number.');
    }

    if (empty($data['tags']) || !is_array($data['tags'])) {
      throw new BadRequestHttpException('Invalid request format. Tag data is required.');
    }

    $nid = $data['nid'];

    // Load the article node.
    $node = Node::load($nid);
    if (!$node || $node->getType() !== 'article') {
      throw new NotFoundHttpException('Article not found.');
    }

    // Check if the user has permission to update the node.
    $account = \Drupal::currentUser();
    if (!$account->hasPermission('edit any article content')) {
      throw new AccessDeniedHttpException('You do not have permission to edit this article.');
    }

    // Load existing tags from the node and append only existing ones.
    $existing_tags = $node->get('field_tags')->getValue();
    $tag_tids = array_column($existing_tags, 'target_id');

    // Process and add only existing tags (no new tags will be created).
    foreach ($data['tags'] as $tag_name) {
      $tag_name = trim($tag_name);
      
      // Correct approach to load taxonomy terms by name.
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
        'name' => $tag_name,
        'vid' => 'tags',
      ]);

      if ($terms) {
        // Add existing term ID if it exists.
        $term = reset($terms);
        $tag_tids[] = $term->id();
      } else {
        // Skip the tag if it doesn't exist (no new term will be created).
        continue;
      }
    }

    // Remove duplicates and update the node.
    $tag_tids = array_unique($tag_tids);
    $node->set('field_tags', $tag_tids);
    $node->save();

    return new ResourceResponse(['message' => 'Tags updated successfully.']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('article_tag_update')
    );
  }

}
