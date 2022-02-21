<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class TriggerProjectLotteryResponse extends Response {

  private array $content;

  public function __construct(array $content) {
    $this->content = $content;
  }

  public function getRequestContent(){
    return $this->content;
  }

  public static function createFromHttpResponse(ResponseInterface $response): self
  {
    if (!self::requestOk($response)) {
      throw new RequestException('Bad status code: ' . $response->getStatusCode());
    }
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self($content);
  }

}
