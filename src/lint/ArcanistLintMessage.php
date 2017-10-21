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

    if (isset($dict['line'])) {
      $message->setLine($dict['line']);
    }

    if (isset($dict['char'])) {
      $message->setChar($dict['char']);
    }

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
      $message->setBypassChangedLineFiltering(
        $dict['bypassChangedLineFiltering']);
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
    $maximum_bytes = 255;
    $actual_bytes = strlen($name);

    if ($actual_bytes > $maximum_bytes) {
      throw new Exception(
        pht(
          'Parameter ("%s") passed to "%s" when constructing a lint message '.
          'must be a string with a maximum length of %s bytes, but is %s '.
          'bytes in length.',
          $name,
          'setName()',
          new PhutilNumber($maximum_bytes),
          new PhutilNumber($actual_bytes)));
    }

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

  public function newTrimmedMessage() {
    if (!$this->isPatchable()) {
      return clone $this;
    }

    // If the original and replacement text have a similar prefix or suffix,
    // we trim it to reduce the size of the diff we show to the user.

    $replacement = $this->getReplacementText();
    $original = $this->getOriginalText();

    $replacement_length = strlen($replacement);
    $original_length = strlen($original);

    $minimum_length = min($original_length, $replacement_length);

    $prefix_length = 0;
    for ($ii = 0; $ii < $minimum_length; $ii++) {
      if ($original[$ii] !== $replacement[$ii]) {
        break;
      }
      $prefix_length++;
    }

    // NOTE: The two strings can't be the same because the message won't be
    // "patchable" if they are, so we don't need a special check for the case
    // where the entire string is a shared prefix.

    // However, if the two strings are in the form "ABC" and "ABBC", we may
    // find a prefix and a suffix with a combined length greater than the
    // total size of the smaller string if we don't limit the search.
    $max_suffix = ($minimum_length - $prefix_length);

    $suffix_length = 0;
    for ($ii = 1; $ii <= $max_suffix; $ii++) {
      $original_char = $original[$original_length - $ii];
      $replacement_char = $replacement[$replacement_length - $ii];
      if ($original_char !== $replacement_char) {
        break;
      }
      $suffix_length++;
    }

    if ($suffix_length) {
      $original = substr($original, 0, -$suffix_length);
      $replacement = substr($replacement, 0, -$suffix_length);
    }

    $line = $this->getLine();
    $char = $this->getChar();

    if ($prefix_length) {
      $prefix = substr($original, 0, $prefix_length);

      // NOTE: Prior to PHP7, `substr("a", 1)` returned false instead of
      // the empty string. Cast these to force the PHP7-ish behavior we
      // expect.
      $original = (string)substr($original, $prefix_length);
      $replacement = (string)substr($replacement, $prefix_length);

      // If we've removed a prefix, we need to push the character and line
      // number for the warning forward to account for the characters we threw
      // away.
      for ($ii = 0; $ii < $prefix_length; $ii++) {
        $char++;
        if ($prefix[$ii] == "\n") {
          $line++;
          $char = 1;
        }
      }
    }

    return id(clone $this)
      ->setOriginalText($original)
      ->setReplacementText($replacement)
      ->setLine($line)
      ->setChar($char);
  }

}
