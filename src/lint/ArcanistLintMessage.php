<?php

/**
 * Message emitted by a linter, like an error or warning.
 */
final class ArcanistLintMessage extends Phobject {

  protected $path;
  protected $line;
  protected $char;
  protected $code;
  protected $severity;
  protected $name;
  protected $description;
  protected $originalText;
  protected $replacementText;
  protected $appliedToDisk;
  protected $dependentMessages = array();
  protected $otherLocations = array();
  protected $obsolete;
  protected $granularity;
  protected $bypassChangedLineFiltering;

  public static function newFromDictionary(array $dict) {
    $message = new ArcanistLintMessage();

    $message->setPath($dict['path']);
    $message->setLine($dict['line']);
    $message->setChar($dict['char']);
    $message->setCode($dict['code']);
    $message->setSeverity($dict['severity']);
    $message->setName($dict['name']);
    $message->setDescription($dict['description']);
    if (isset($dict['original'])) {
      $message->setOriginalText($dict['original']);
    }
    if (isset($dict['replacement'])) {
      $message->setReplacementText($dict['replacement']);
    }
    $message->setGranularity(idx($dict, 'granularity'));
    $message->setOtherLocations(idx($dict, 'locations', array()));
    if (isset($dict['bypassChangedLineFiltering'])) {
      $message->bypassChangedLineFiltering($dict['bypassChangedLineFiltering']);
    }
    return $message;
  }

  public function toDictionary() {
    return array(
      'path'        => $this->getPath(),
      'line'        => $this->getLine(),
      'char'        => $this->getChar(),
      'code'        => $this->getCode(),
      'severity'    => $this->getSeverity(),
      'name'        => $this->getName(),
      'description' => $this->getDescription(),
      'original'    => $this->getOriginalText(),
      'replacement' => $this->getReplacementText(),
      'granularity' => $this->getGranularity(),
      'locations'   => $this->getOtherLocations(),
      'bypassChangedLineFiltering' => $this->shouldBypassChangedLineFiltering(),
    );
  }

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setLine($line) {
    $this->line = $this->validateInteger($line, 'setLine');
    return $this;
  }

  public function getLine() {
    return $this->line;
  }

  public function setChar($char) {
    $this->char = $this->validateInteger($char, 'setChar');
    return $this;
  }

  public function getChar() {
    return $this->char;
  }

  public function setCode($code) {
    $code = (string)$code;

    $maximum_bytes = 128;
    $actual_bytes = strlen($code);

    if ($actual_bytes > $maximum_bytes) {
      throw new Exception(
        pht(
          'Parameter ("%s") passed to "%s" when constructing a lint message '.
          'must be a scalar with a maximum string length of %s bytes, but is '.
          '%s bytes in length.',
          $code,
          'setCode()',
          new PhutilNumber($maximum_bytes),
          new PhutilNumber($actual_bytes)));
    }

    $this->code = $code;
    return $this;
  }

  public function getCode() {
    return $this->code;
  }

  public function setSeverity($severity) {
    $this->severity = $severity;
    return $this;
  }

  public function getSeverity() {
    return $this->severity;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setOriginalText($original) {
    $this->originalText = $original;
    return $this;
  }

  public function getOriginalText() {
    return $this->originalText;
  }

  public function setReplacementText($replacement) {
    $this->replacementText = $replacement;
    return $this;
  }

  public function getReplacementText() {
    return $this->replacementText;
  }

  /**
   * @param dict Keys 'path', 'line', 'char', 'original'.
   */
  public function setOtherLocations(array $locations) {
    assert_instances_of($locations, 'array');
    $this->otherLocations = $locations;
    return $this;
  }

  public function getOtherLocations() {
    return $this->otherLocations;
  }

  public function isError() {
    return $this->getSeverity() == ArcanistLintSeverity::SEVERITY_ERROR;
  }

  public function isWarning() {
    return $this->getSeverity() == ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function isAutofix() {
    return $this->getSeverity() == ArcanistLintSeverity::SEVERITY_AUTOFIX;
  }

  public function hasFileContext() {
    return ($this->getLine() !== null);
  }

  public function setObsolete($obsolete) {
    $this->obsolete = $obsolete;
    return $this;
  }

  public function getObsolete() {
    return $this->obsolete;
  }

  public function isPatchable() {
    return ($this->getReplacementText() !== null) &&
           ($this->getReplacementText() !== $this->getOriginalText());
  }

  public function didApplyPatch() {
    if ($this->appliedToDisk) {
      return $this;
    }
    $this->appliedToDisk = true;
    foreach ($this->dependentMessages as $message) {
      $message->didApplyPatch();
    }
    return $this;
  }

  public function isPatchApplied() {
    return $this->appliedToDisk;
  }

  public function setGranularity($granularity) {
    $this->granularity = $granularity;
    return $this;
  }

  public function getGranularity() {
    return $this->granularity;
  }

  public function setDependentMessages(array $messages) {
    assert_instances_of($messages, __CLASS__);
    $this->dependentMessages = $messages;
    return $this;
  }

  public function setBypassChangedLineFiltering($bypass_changed_lines) {
    $this->bypassChangedLineFiltering = $bypass_changed_lines;
    return $this;
  }

  public function shouldBypassChangedLineFiltering() {
    return $this->bypassChangedLineFiltering;
  }

  /**
   * Validate an integer-like value, returning a strict integer.
   *
   * Further on, the pipeline is strict about types. We want to be a little
   * less strict in linters themselves, since they often parse command line
   * output or XML and will end up with string representations of numbers.
   *
   * @param mixed Integer or digit string.
   * @return int Integer.
   */
  private function validateInteger($value, $caller) {
    if ($value === null) {
      // This just means that we don't have any information.
      return null;
    }

    // Strings like "234" are fine, coerce them to integers.
    if (is_string($value) && preg_match('/^\d+\z/', $value)) {
      $value = (int)$value;
    }

    if (!is_int($value)) {
      throw new Exception(
        pht(
          'Parameter passed to "%s" must be an integer.',
          $caller.'()'));
    }

    return $value;
  }

}
