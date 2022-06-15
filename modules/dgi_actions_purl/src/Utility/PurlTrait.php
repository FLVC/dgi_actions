<?php

namespace Drupal\dgi_actions_purl\Utility;

use Drupal\Core\Entity\EntityInterface;
use Drupal\dgi_actions\Plugin\Action\HttpActionTrait;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

/**
 * Utilities when interacting with the PURL API.
 */
trait PurlTrait {

  use HttpActionTrait;

  /**
   * Identifier entity describing the operation to be done.
   *
   * @var \Drupal\dgi_actions\Entity\IdentifierInterface
   */
  protected $identifier;

  /**
   * Current actioned Entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Gets the entity being used.
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Gets the PURL host.
   */
  public function getHost(): string {
    return $this->getIdentifier()->getServiceData()->getData()['host'];
  }

  /**
   * Gets the PURL API key.
   */
  public function getApikey(): string {
    return $this->getIdentifier()->getServiceData()->getData()['apikey'];
  }

  /**
   * Gets the PURL domain.
   */
  public function getDomain(): string {
    return $this->getIdentifier()->getServiceData()->getData()['domain'];
  }

  /**
   * Gets the PURL institution code.
   */
  public function getInstitution(): string {
    return $this->getIdentifier()->getServiceData()->getData()['institution'];
  }

  /**
   * Gets the PURL target.
   */
  public function getTarget(): string {
    return $this->getIdentifier()->getServiceData()->getData()['target'];
  }

  /**
   * Gets the UUID for the entity.
   */
  public function getUuid(): ?string {
    // XXX: Should this be something different?
    return $this->getEntity()->uuid();
  }

  /**
   * Returns the PURL REST API endpoint.
   *
   * @return string
   *   The URL to be used for PURL requests.
   */
  protected function getUri(): string {
    return "{$this->getIdentifier()->getServiceData()->getData()['host']}/api/purl";
  }

  /**
   * Helper that wraps the normal requests to get more verbosity for errors.
   */
  protected function purlRequest() {
    try {
      $request = $this->buildRequest();
      return $this->sendRequest($request);
    }
    catch (RequestException $e) {
      // Wrap the exception with a bit of extra info from PURL API for
      // verbosity's sake.
      $message = $e->getMessage();
      $response = $e->getResponse();
      if ($response) {
        $purl_message = $this->mapPurlResponseCodes($response);

        if ($purl_message) {
          $message .= "PURL API Message: $purl_message";
        }
      }
      throw new RequestException($message, $e->getRequest(), $response, $e);
    }

  }

  /**
   * Maps PURL application response codes to error messages if they exist.
   *
   * @param \GuzzleHttp\Psr7\Response $response
   *   The response of the HTTP request to the PURL API.
   *
   * @return bool|string
   *   FALSE if no data or the code does not exist in our mapping, otherwise a
   *   string describing what that message actually means.
   */
  protected function mapPurlResponseCodes(Response $response) {
    $mapping = [
      '201' => t('Successful PURL request'),
      '400' => t('Invalid PURL'),
      '401' => t('Invalid API key'),
      '404' => t('PURL not found'),
    ];

    $body = $response->getBody();
    if ($body) {
      $json = json_decode($body, TRUE);
      if (isset($json['responseCode'])) {
        return $mapping[$json['responseCode']] ?? FALSE;
      }
    }
    return FALSE;
  }

}
