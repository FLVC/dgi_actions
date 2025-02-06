<?php

namespace Drupal\dgi_actions_purl\Plugin\ServiceDataType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\dgi_actions\Plugin\ServiceDataTypeBase;

/**
 * Mints a PURL from FLVC.
 *
 * @ServiceDataType(
 *   id = "purl",
 *   label = @Translation("PURL"),
 *   description = @Translation("Service information for FLVC PURLs.")
 * )
 */
class Purl extends ServiceDataTypeBase {

  /**
   * PURL service data plugin constructor.
   *
   * @param array $configuration
   *   Array containing default configuration for the plugin.
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $plugin_definition
   *   Array describing the plugin definition.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'host' => NULL,
      'apikey' => NULL,
      'target' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['host'] = [
      '#type' => 'url',
      '#title' => $this->t('PURL Service Hostname'),
      '#description' => $this->t('Host address for the PURL service endpoints.'),
      '#default_value' => $this->configuration['host'],
      '#required' => TRUE,
    ];
    $form['apikey'] = [
      '#type' => 'password',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('API key for the PURL service endpoints.'),
      '#default_value' => $this->configuration['apikey'],
      '#required' => TRUE,
    ];
    $form['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PURL Domain'),
      '#description' => $this->t('Domain for the PURL service.'),
      '#default_value' => $this->configuration['domain'],
      '#required' => TRUE,
    ];
    $form['institution'] = [
      '#type' => 'textfield',
      '#title' => $this->t('PURL Institution Code'),
      '#description' => $this->t('Institution code for the PURL service.'),
      '#default_value' => $this->configuration['institution'],
      '#required' => TRUE,
    ];
    $form['target'] = [
      '#type' => 'url',
      '#title' => $this->t('Target Hostname'),
      '#description' => $this->t('PURL target site hostname.'),
      '#default_value' => $this->configuration['target'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['host'] = $form_state->getValue('host');
    $this->configuration['apikey'] = $form_state->getValue('apikey');
    $this->configuration['domain'] = $form_state->getValue('domain');
    $this->configuration['institution'] = $form_state->getValue('institution');
    $this->configuration['target'] = $form_state->getValue('target');
  }

}
