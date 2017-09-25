<?php

abstract class ArcanistLintRenderer extends Phobject {

  private $output;

  final public function getRendererKey() {
    return $this->getPhobjectClassConstant('RENDERERKEY');
  }

  final public static function getAllRenderers() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getRendererKey')
      ->execute();
  }

  final public function setOutputPath($path) {
    $this->output = $path;
    return $this;
  }


  /**
   * Does this renderer support applying lint patches?
   *
   * @return bool True if patches should be applied when using this renderer.
   */
  public function supportsPatching() {
    return false;
  }

  public function willRenderResults() {
    return null;
  }

  public function didRenderResults() {
    return null;
  }

  public function renderResultCode($result_code) {
    return null;
  }

  public function handleException(Exception $ex) {
    throw $ex;
  }

  abstract public function renderLintResult(ArcanistLintResult $result);

  protected function writeOut($message) {
    if ($this->output) {
      Filesystem::appendFile($this->output, $message);
    } else {
      echo $message;
    }

    return $this;
  }

}
