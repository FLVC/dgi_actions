<?php

namespace Drupal\dgi_actions_purl\Plugin\Action;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dgi_actions\Plugin\Action\DeleteIdentifier;
use Drupal\dgi_actions\Plugin\Action\HttpActionDeleteTrait;
use Drupal\dgi_actions\Utility\IdentifierUtils;
use Drupal\dgi_actions_purl\Utility\PurlTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete or tombstone a PURL.
 *
 * @Action(
 *   id = "dgi_actions_delete_purl",
 *   label = @Translation("Delete a PURL"),
 *   type = "entity"
 * )
 */
class DeletePurl extends DeleteIdentifier {

  use HttpActionDeleteTrait;
  use PurlTrait;

  private int $purlId = 0;
  private string $purlPath;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\dgi_actions\Utility\IdentifierUtils $utils
   *   Identifier utils.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client to be used for the request.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, IdentifierUtils $utils, EntityTypeManagerInterface $entity_type_manager, ClientInterface $client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $utils, $entity_type_manager);
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.dgi_actions'),
      $container->get('dgi_actions.utils'),
      $container->get('entity_type.manager'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestType(): string {
    return 'DELETE';
  }

  /**
   * {@inheritdoc}
   */
  protected function getUri(): string {
    return "{$this->getHost()}/api/purl/{$this->purlId}";
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestParams(): array {

    return [
      'headers' => [
        'Content-Type' => 'application/json;charset=UTF-8',
        'KiwiApiKey' => $this->getApikey(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function delete(): void {
    // check for existing purlId
    $this->logger->info("DEBUG in delete");
    $field = $this->getIdentifier()->get('field');
    if ($this->getEntity()->hasField($field)) {
      $this->logger->info("DEBUG entity has identifier field");
      $field_value = $this->getEntity()->get($field)->getString();
      if (!empty($field_value)) {
        $this->logger->info("DEBUG entity has identifier value {$field_value}");
      }
      else {
        $this->logger->info("DEBUG entity has empty identifier field");
        return;
      }
    }
    else {
      $this->logger->info("DEBUG entity is missing identifier field");
      return;
    }
    $purlList =  $this->getEntity()->get($field)->getValue();
    foreach ($purlList as $purl) {
      $this->logger->info("DEBUG run delete for identifier {$purl['uri']}");
      $this->purlPath = parse_url($purl['uri'], PHP_URL_PATH);
      $this->purlId = $this->getPurlId($this->purlPath);
      $this->logger->info("purlPath {$this->purlPath} has purlId {$this->purlId}");
      if ($this->purlId > 0) {
        $this->handleDeleteResponse($this->purlRequest());
      }
      else {
        $this->logger->info("skip delete - purlPath {$this->purlPath} does not exist");
      }
    }
    return;
  }

  /**
   * {@inheritdoc}
   */
  protected function handleDeleteResponse(ResponseInterface $response): void {
    $body = json_decode($response->getBody(), TRUE);
    if ($body['status'] == 2) {
      $this->logger->info('PURL deleted for @type/@id: @purlPath.', [
        '@type' => $this->getEntity()->getEntityTypeId(),
        '@id' => $this->getEntity()->id(),
        '@purlPath' => $body['purlPath'],
      ]);
    }
    else {
      $this->logger->info('Delete failed for PURL @type/@id: @purlPath.', [
        '@type' => $this->getEntity()->getEntityTypeId(),
        '@id' => $this->getEntity()->id(),
        '@purlPath' => $body['purlPath'],
      ]);
    }

    return;
  }

}
