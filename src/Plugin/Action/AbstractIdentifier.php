<?php

namespace Drupal\dgi_actions\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Session\AccountInterface;

/**
 * Initial Test Object, will be deleted before module submission.
 */
abstract class AbstractIdentifier extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * Logger.
   *
   * @var Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Http Client connection.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entity_type_manager;


  /**
   * Entity Type Bundle Info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entity_type_bundle_info;

  /**
   * Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entity_field_manager;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config_factory;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\Client $client
   *   Http Client connection.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Entity type manager.
   * @param Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   Entity field manager.
   * @param Drupal\Core\Config\ConfigFactory
   *   Config factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Client $client,
    EntityTypeManager $entity_type_manager,
    EntityTypeBundleInfo $entity_type_bundle_info,
    LoggerInterface $logger,
    EntityFieldManager $entity_field_manager,
    ConfigFactory $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->logger = $logger;
    $this->entityFieldManager = $entity_field_manager;
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
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('logger.factory')->get('dgi_actions'),
      $container->get('entity_field.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $object->access('read', $account, $return_as_object);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  abstract public function execute();

  /**
   * {@inheritdoc}
   */
  abstract public function buildConfigurationForm(array $form, FormStateInterface $form_state);
}
