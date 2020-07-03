<?php

final class ArcanistPromptResponse
  extends Phobject {

  private $prompt;
  private $response;
  private $configurationSource;

  public static function newFromConfig($map) {

    PhutilTypeSpec::checkMap(
      $map,
      array(
        'prompt' => 'string',
        'response' => 'string',
      ));

    return id(new self())
      ->setPrompt($map['prompt'])
      ->setResponse($map['response']);
  }

  public function getStorageDictionary() {
    return array(
      'prompt' => $this->getPrompt(),
      'response' => $this->getResponse(),
    );
  }

  public function setPrompt($prompt) {
    $this->prompt = $prompt;
    return $this;
  }

  public function getPrompt() {
    return $this->prompt;
  }

  public function setResponse($response) {
    $this->response = $response;
    return $this;
  }

  public function getResponse() {
    return $this->response;
  }

  public function setConfigurationSource(
    ArcanistConfigurationSource $configuration_source) {
    $this->configurationSource = $configuration_source;
    return $this;
  }

  public function getConfigurationSource() {
    return $this->configurationSource;
  }

}
