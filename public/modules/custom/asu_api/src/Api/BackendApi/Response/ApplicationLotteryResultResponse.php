<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\ApplicationRequestException;
use Psr\Http\Message\ResponseInterface;

class ApplicationLotteryResultResponse extends Response {

  /**
   * Lottery result.
   *
   * @var array
   */
  private array $result;

  public function __construct(array $result) {
    $this->result = $result;
  }

  public function getContent() {
    return $this->result;
  }

  public static function createFromHttpResponse(ResponseInterface $response): self
  {
    if (!self::requestOk($response)) {
      throw new ApplicationRequestException('Bad status code: ' . $response->getStatusCode());
    }
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self($content);
  }
}