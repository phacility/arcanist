<?php

final class UberSubmitQueueClient extends Phobject {

    private $uri;
    private $host;
    private $conduitToken;
    private $timeout;

    public function __construct($uri, $conduitToken, $timeout=10) {
        $this->uri = new PhutilURI($uri);
        if (!strlen($this->uri->getDomain())) {
            throw new Exception(
                pht("SubmitQueue URI '%s' must include a valid host.", $uri));
        }
        $this->host = $this->uri->getDomain();
        $this->conduitToken = $conduitToken;
        $this->timeout = $timeout;
    }

    public function getHost() {
        return $this->host;
    }

    public function submitMergeRequest($remoteUrl, $diffId, $revisionId, $shouldShadow, $targetOnto) {
        $params = array(
          'remote' => $remoteUrl,
          'diffId' => $diffId,
          'revisionId' => $revisionId,
          'targetOnto' => $targetOnto,
          'conduitToken' => $this->conduitToken,
        );
        if ($shouldShadow) {
          $params['shouldShadow'] = "true";
        }
        return $this->callMethodSynchronous("POST", "/merge_requests", $params);
    }

  public function submitMergeStackRequest($remoteUrl, $stack, $shouldShadow, $targetOnto) {
    $params = array(
      'remote' => $remoteUrl,
      'targetOnto' => $targetOnto,
      'conduitToken' => $this->conduitToken,
      'stack' => json_encode($stack)
    );
    if ($shouldShadow) {
      $params['shouldShadow'] = "true";
    }
    return $this->callMethodSynchronous("POST", "/merge_requests", $params);
  }

    private function callMethodSynchronous($method, $api, array $params) {
        return $this->callMethod($method, $api, $params)->resolve();
    }

    private function callMethod($method, $api, array $params) {
        $req = id(clone $this->uri)->setPath('/api'.$api.'?'.http_build_query($params));
        // Always use the cURL-based HTTPSFuture, for proxy support and other
        // protocol edge cases that HTTPFuture does not support.
        $core_future = new HTTPSFuture($req);
        $core_future->addHeader('Host', $this->getHost());

        $core_future->setMethod($method);
        $core_future->setTimeout($this->timeout);

        $json_future = new UberSubmitQueueFuture($core_future);
        $json_future->isReady();

        return $json_future;
    }
}
