<?php

namespace Drupal\dgi_actions\Plugin\Action;

use Psr\Http\Message\ResponseInterface;

/**
 * Utilities for updating from an HTTP service.
 */
trait HttpActionUpdateTrait {

  use HttpActionTrait;

  /**
   * Updates the identifier from the service.
   */
  protected function update(): void {
    $this->handleUpdateResponse($this->sendRequest($this->buildRequest()));
  }

  /**
   * Handles the response from the update request.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The Guzzle HTTP Response Object.
   */
  abstract protected function handleUpdateResponse(ResponseInterface $response): void;

}
