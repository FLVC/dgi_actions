<?php

namespace Drupal\dgi_actions_purl\Plugin\Action;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dgi_actions\Plugin\Action\HttpActionUpdateTrait;
use Drupal\dgi_actions\Plugin\Action\UpdateIdentifier;
use Drupal\dgi_actions\Utility\IdentifierUtils;
use Drupal\dgi_actions_purl\Utility\PurlTrait;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates a PURL.
 *
 * @Action(
 *   id = "dgi_actions_update_purl",
 *   label = @Translation("Update a PURL"),
 *   type = "entity"
 * )
 */
class UpdatePurl extends UpdateIdentifier {

  use HttpActionUpdateTrait;
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
  protected function getUri(): string {
    // request URI depends on whether PURL exists
    // add purlId if PURL exists
    $uri = "{$this->getHost()}/api/purl";
    if ($this->purlId > 0) {
        $uri .= "/{$this->purlId}";
    }
    $this->logger->info("DEBUG update URI = {$uri}");
    return $uri;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestType(): string {
    // request type depends on whether PURL exists
    // create if PURL does not exist
    // update if PURL exists
    $requestType = 'POST';
    if ($this->purlId > 0) {
        $requestType = 'PUT';
    }
    $this->logger->info("DEBUG update RequestType = {$requestType}");
    return $requestType;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestParams(): array {

    $externalUrl = $this->entity->toUrl()->setAbsolute()->setOption('alias', TRUE)->toString(TRUE)->getGeneratedUrl();
    $path = parse_url($externalUrl, PHP_URL_PATH);
    $path = trim($path, '/');

    $data = [];
    // need to get purlPath value from entity
    $data['purlPath'] = $this->purlPath;
    $data['type'] = '301';
    $data['target'] = $this->getTarget() . '/' . $path;
    $data['institutionCode'] = $this->getInstitution();

    $body = json_encode($data, JSON_UNESCAPED_SLASHES);

    return [
      'headers' => [
        'Content-Type' => 'application/json;charset=UTF-8',
        'KiwiApiKey' => $this->getApikey(),
      ],
      'body' => $body,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function update(): void {
    // check for existing purlId
    $this->logger->info("DEBUG in update");
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
      $this->logger->info("DEBUG run update for identifier {$purl['uri']}");
      $this->purlPath = parse_url($purl['uri'], PHP_URL_PATH);
      $this->purlId = $this->getPurlId($this->purlPath);
      $this->logger->info("purlPath {$this->purlPath} has purlId {$this->purlId}");

      $this->handleUpdateResponse($this->purlRequest());
    }
    return;
  }

  /**
   * {@inheritdoc}
   */
  protected function handleUpdateResponse(ResponseInterface $response): void {
    $body = json_decode($response->getBody(), TRUE);
    $this->logger->info('PURL updated for @type/@id: @purlPath.', [
      '@type' => $this->getEntity()->getEntityTypeId(),
      '@id' => $this->getEntity()->id(),
      '@purlPath' => $body['purlPath'],
    ]);

    return;
  }

}
