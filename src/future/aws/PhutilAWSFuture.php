<?php

abstract class PhutilAWSFuture extends FutureProxy {

  private $future;
  private $accessKey;
  private $secretKey;
  private $region;
  private $httpMethod = 'GET';
  private $path = '/';
  private $endpoint;
  private $data = '';
  private $headers = array();

  abstract public function getServiceName();

  public function __construct() {
    parent::__construct(null);
  }

  public function setAccessKey($access_key) {
    $this->accessKey = $access_key;
    return $this;
  }

  public function getAccessKey() {
    return $this->accessKey;
  }

  public function setSecretKey(PhutilOpaqueEnvelope $secret_key) {
    $this->secretKey = $secret_key;
    return $this;
  }

  public function getSecretKey() {
    return $this->secretKey;
  }

  public function getRegion() {
    return $this->region;
  }

  public function setRegion($region) {
    $this->region = $region;
    return $this;
  }

  public function setEndpoint($endpoint) {
    $this->endpoint = $endpoint;
    return $this;
  }

  public function getEndpoint() {
    return $this->endpoint;
  }

  public function setHTTPMethod($method) {
    $this->httpMethod = $method;
    return $this;
  }

  public function getHTTPMethod() {
    return $this->httpMethod;
  }

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getData() {
    return $this->data;
  }

  protected function getParameters() {
    return array();
  }

  public function addHeader($key, $value) {
    $this->headers[] = array($key, $value);
    return $this;
  }

  protected function getProxiedFuture() {
    if (!$this->future) {
      $params = $this->getParameters();
      $method = $this->getHTTPMethod();
      $host = $this->getEndpoint();
      $path = $this->getPath();
      $data = $this->getData();

      $uri = id(new PhutilURI("https://{$host}/", $params))
        ->setPath($path);

      $future = id(new HTTPSFuture($uri, $data))
        ->setMethod($method);

      foreach ($this->headers as $header) {
        list($key, $value) = $header;
        $future->addHeader($key, $value);
      }

      $this->signRequest($future);

      $this->future = $future;
    }

    return $this->future;
  }

  protected function signRequest(HTTPSFuture $future) {
    $access_key = $this->getAccessKey();
    $secret_key = $this->getSecretKey();

    $region = $this->getRegion();

    id(new PhutilAWSv4Signature())
      ->setRegion($region)
      ->setService($this->getServiceName())
      ->setAccessKey($access_key)
      ->setSecretKey($secret_key)
      ->setSignContent($this->shouldSignContent())
      ->signRequest($future);
  }

  protected function shouldSignContent() {
    return false;
  }

  protected function didReceiveResult($result) {
    list($status, $body, $headers) = $result;

    try {
      $xml = @(new SimpleXMLElement($body));
    } catch (Exception $ex) {
      phlog($ex);
      $xml = null;
    }

    if ($status->isError() || !$xml) {
      if (!($status instanceof HTTPFutureHTTPResponseStatus)) {
        throw $status;
      }

      $params = array(
        'body' => $body,
      );
      if ($xml) {
        $params['RequestID'] = $xml->RequestID[0];

        // NOTE: The S3 and EC2 APIs return slightly different error responses.

        // In S3 responses, there's a simple top-level "<Error>" element.
        $s3_error = $xml->Error;
        if ($s3_error) {
          $params['Errors'][] = array(
            phutil_string_cast($s3_error->Code),
            phutil_string_cast($s3_error->Message),
          );
        }

        // In EC2 responses, there's an "<Errors>" element with "<Error>"
        // children.
        $ec2_errors = $xml->Errors[0];
        if ($ec2_errors) {
          foreach ($ec2_errors as $error) {
            $params['Errors'][] = array(
              phutil_string_cast($error->Code),
              phutil_string_cast($error->Message),
            );
          }
        }
      }

      throw new PhutilAWSException($status->getStatusCode(), $params);
    }

    return $xml;
  }

}
