<?php

namespace Drupal\dgi_actions_purl\Drush\Commands;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\dgi_actions\Entity\IdentifierInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Commandfile for reporting identifiers and expected target locations.
 */
class ReportIdentifierLocationCommands extends DrushCommands {

  use DependencySerializationTrait;

  /**
   * Constructs a new ReportIdentifierLocationCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Http\Client\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ClientInterface $httpClient,
    private readonly MessengerInterface $messenger,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('http_client'),
      $container->get('messenger'),
    );
  }

  /**
   * Report identifiers with expected target locations.
   */
  #[CLI\Command(name: 'dgi_actions_purl:report-identifier-locations', aliases: ['dapurl:ril'])]
  #[CLI\Usage(name: 'dgi_actions_purl:report-identifier-locations', description: 'Reports identifiers and expected target locations.')]
  public function reportIdentifierLocations(): void {
    // Create a batch to find and iterate through all configured entities
    // within an identifier that have the field populated.
    $batch = [
      'title' => dt('Generating identifiers...'),
      'operations' => [],
    ];
    foreach ($this->entityTypeManager->getStorage('dgiactions_identifier')->loadMultiple() as $identifier) {
      printf($identifier->label());
      printf("\n");
      $batch['operations'][] = [
        [$this, 'reportBatch'],
        [
          $identifier,
        ],
      ];
    }
    if (!empty($batch['operations'])) {
      printf("starting batch\n");
      drush_op('batch_set', $batch);
      drush_op('drush_backend_batch_process');
    }
    else {
      $this->logger()->error('No identifiers found.');
    }

  }

  /**
   * Batch for reporting identifiers and expected target locations.
   *
   * @param \Drupal\dgi_actions\Entity\IdentifierInterface $identifier
   *   The DGI Actions Identifier ID to be used for the generation.
   * @param array $context
   *   Batch context.
   */
  public function reportBatch(IdentifierInterface $identifier, &$context): void {
    $institution = $identifier->getServiceData()->getData()['institution'];
    $report_path = \Drupal::service('file_system')->getTempDirectory() . '/' . $institution . '.' . $identifier->id() . '.report.csv';
    $tab_path = \Drupal::service('file_system')->getTempDirectory() . '/' . $institution . '.' . $identifier->id() . '.load.tsv';
    $reportfile = fopen($report_path, "a");
    $tabfile = fopen($tab_path, "a");
    $sandbox =& $context['sandbox'];
    $entity_type = $identifier->getEntity();
    $entity_id_key = $this->entityTypeManager->getDefinition($entity_type)->getKeys()['id'];
    $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    $query = $entity_storage->getQuery()
      ->condition($identifier->getField(), NULL, 'IS NOT NULL')
      ->accessCheck(FALSE);
    if (!isset($sandbox['total'])) {
      $context['results'] = [
        'failed' => [],
        'success' => [],
      ];
      $count_query = clone $query;
      $sandbox['total'] = $count_query->count()->execute();
      if ($sandbox['total'] === 0) {
        $context['message'] = dt('Batch empty.');
        $context['finished'] = 1;
        return;
      }
      $sandbox['last_id'] = FALSE;
      $sandbox['completed'] = 0;
    }

    if ($sandbox['last_id']) {
      $query->condition($entity_id_key, $sandbox['last_id'], '>');
    }
    $query->sort($entity_id_key);
    $query->range(0, 100);
    foreach ($query->execute() as $result) {
      try {
        $sandbox['last_id'] = $result;
        $entity = $this->entityTypeManager->getStorage($entity_type)->load($result);
        if (!$entity) {
          $this->messenger->addError(dt('Failed to load {entity} {entity_id}; skipping.', [
            'entity' => $entity_type,
            'entity_id' => $result,
          ]));
          continue;
        }
        // need to handle array of PURL identifiers
        $identifier_list =  $entity->get($identifier->getField())->getValue();
        foreach ($identifier_list as $identifier_field) {
          $identifier_location = $identifier_field['uri'];
          $identifier_location = str_replace('http:', 'https:', $identifier_location);
          $response = $this->httpClient->request('HEAD', $identifier_location, [
            'allow_redirects' => FALSE,
            'http_errors' => FALSE,
          ]);
          $current_target = $response->getHeaderLine('Location');
          $externalUrl = $entity->toUrl()->setAbsolute()->setOption('alias', TRUE)->toString(TRUE)->getGeneratedUrl();
          $path = parse_url($externalUrl, PHP_URL_PATH);
          $path = trim($path, '/');
          $expected_target = $identifier->getServiceData()->getData()['target'] . '/' . $path;
          $identifier_path = parse_url($identifier_location, PHP_URL_PATH);
          fwrite($reportfile, t("@purl,@loc1,@loc2\n", ['@purl' => $identifier_path, '@loc1' => $expected_target, '@loc2' => $current_target]));
          fwrite($tabfile, t("@purl\t302\t@inst\t@target\n", ['@purl' => $identifier_path, '@inst' => $institution, '@target' => $expected_target]));
        }
      }
      catch (\Exception $e) {
        $this->messenger->addError(dt('Encountered an exception: {exception}', [
          'exception' => $e,
        ]));
      }
      $sandbox['completed']++;
      $context['finished'] = $sandbox['completed'] / $sandbox['total'];
    }
    $this->messenger->addMessage(t('completed:@completed,total:@total', ['@completed' => $sandbox['completed'], '@total' => $sandbox['total']]));
    fclose($reportfile);
    fclose($tabfile);
  }

}
