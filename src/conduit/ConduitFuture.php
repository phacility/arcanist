<?php

final class ConduitFuture extends FutureProxy {

  private $client;
  private $engine;
  private $conduitMethod;

  public function setClient(ConduitClient $client, $method) {
    $this->client = $client;
    $this->conduitMethod = $method;
    return $this;
  }

  protected function getServiceProfilerStartParameters() {
    return array(
      'type' => 'conduit',
      'method' => $this->conduitMethod,
      'size' => $this->getProxiedFuture()->getHTTPRequestByteLength(),
    );
  }

  protected function getServiceProfilerResultParameters() {
    return array();
  }

  protected function didReceiveResult($result) {
    list($status, $body, $headers) = $result;
    if ($status->isError()) {
      throw $status;
    }

    $capabilities = array();
    foreach ($headers as $header) {
      list($name, $value) = $header;
      if (!strcasecmp($name, 'X-Conduit-Capabilities')) {
        $capabilities = explode(' ', $value);
        break;
      }
    }

    if ($capabilities) {
      $this->client->enableCapabilities($capabilities);
    }

    $raw = $body;

    $shield = 'for(;;);';
    if (!strncmp($raw, $shield, strlen($shield))) {
      $raw = substr($raw, strlen($shield));
    }

    $data = null;
    try {
      $data = phutil_json_decode($raw);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          'Host returned HTTP/200, but invalid JSON data in response to '.
          'a Conduit method call.'),
        $ex);
    }

    if ($data['error_code']) {
      $message = pht(
        '<%s> %s',
        $this->conduitMethod,
        $data['error_info']);

      throw new ConduitClientException(
        $data['error_code'],
        $message);
    }

    $result = $data['result'];

    $result = $this->client->didReceiveResponse(
      $this->conduitMethod,
      $result);

    return $result;
  }

}
