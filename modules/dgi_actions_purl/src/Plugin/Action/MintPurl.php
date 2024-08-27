<?php

namespace Drupal\dgi_actions_purl\Plugin\Action;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dgi_actions\Plugin\Action\HttpActionMintTrait;
use Drupal\dgi_actions\Plugin\Action\MintIdentifier;
use Drupal\dgi_actions\Utility\IdentifierUtils;
use Drupal\dgi_actions_purl\Utility\PurlTrait;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mints a PURL.
 *
 * @Action(
 *   id = "dgi_actions_mint_purl",
 *   label = @Translation("Mint a PURL"),
 *   type = "entity"
 * )
 */
class MintPurl extends MintIdentifier {

  use HttpActionMintTrait;
  use PurlTrait;

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
    return "{$this->getHost()}/api/purl";
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestType(): string {
    return 'POST';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestParams(): array {

    $externalUrl = $this->entity->toUrl()->setAbsolute()->setOption('alias', TRUE)->toString(TRUE)->getGeneratedUrl();
    $path = parse_url($externalUrl, PHP_URL_PATH);
    $path = trim($path, '/');

    $data = [];
    $data['purlPath'] = $this->getDomain() . '/' . $path;
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
  protected function mint(): string {
    $this->logger->info("DEBUG in mint for missing identifier");
    return $this->getIdentifierFromResponse($this->purlRequest());
  }

  /**
   * {@inheritdoc}
   */
  protected function getIdentifierFromResponse(ResponseInterface $response): string {
    $body = json_decode($response->getBody(), TRUE);
    $this->logger->info('PURL minted for @type/@id: @purlPath.', [
      '@type' => $this->getEntity()->getEntityTypeId(),
      '@id' => $this->getEntity()->id(),
      '@purlPath' => $body['purlPath'],
    ]);
    return $this->getHost() . $body['purlPath'];
  }

}
