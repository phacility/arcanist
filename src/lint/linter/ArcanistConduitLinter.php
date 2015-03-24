<?php

/**
 * Implements linting via Conduit RPC call.
 *
 * This linter is slow by definition, but allows sophisticated linting that
 * relies on stuff like big indexes of a codebase. Recommended usage is to gate
 * these to the advice lint level.
 *
 * The conduit endpoint should implement a method named the same as the value
 * of `ArcanistConduitLinter::CONDUIT_METHOD`. It takes an array with a key
 * `file_contents` which is an array mapping file paths to their complete
 * contents. It should return an array mapping those same paths to arrays
 * describing the lint for each path.
 *
 * The lint for a path is described as a list of structured dictionaries. The
 * dictionary structure is effectively defined by
 * `ArcanistLintMessage::newFromDictionary`.
 *
 * Effective keys are:
 *   - `path`: must match passed in path.
 *   - `line`
 *   - `char`
 *   - `code`
 *   - `severity`: must match a constant in @{class:ArcanistLintSeverity}.
 *   - `name`
 *   - `description`
 *   - `original` & `replacement`: optional patch information.
 *   - `locations`: other locations of the same error (in the same format).
 *
 * This class is intended for customization via instantiation, not via
 * subclassing.
 */
final class ArcanistConduitLinter extends ArcanistLinter {
  const CONDUIT_METHOD = 'lint.getalllint';

  private $conduitURI;
  private $linterName;
  private $results;

  public function __construct($conduit_uri = null, $linter_name = null) {
    // TODO: Facebook uses this (probably?) but we need to be able to
    // construct it without arguments for ".arclint".
    $this->conduitURI = $conduit_uri;
    $this->linterName = $linter_name;
  }

  public function getLinterName() {
    return $this->linterName;
  }

  public function getLintNameMap() {
    // See @{method:getLintSeverityMap} for rationale.
    throw new ArcanistUsageException(
      pht('%s does not support a name map.', __CLASS__));
  }

  public function getLintSeverityMap() {
    // The rationale here is that this class will only be used for custom
    // linting in installations. No two server endpoints will be the same across
    // different instantiations. Therefore, the server can handle all severity
    // customization directly.
    throw new ArcanistUsageException(
      pht(
        '%s does not support client-side severity customization.',
        __CLASS__));
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  public function willLintPaths(array $paths) {
    // Load all file path data into $this->data.
    array_map(array($this, 'getData'), $paths);

    $conduit = new ConduitClient($this->conduitURI);

    $this->results = $conduit->callMethodSynchronous(
      self::CONDUIT_METHOD,
      array(
        'file_contents' => $this->data,
      ));
  }

  public function lintPath($path) {
    $lints = idx($this->results, $path);

    if (!$lints) {
      return;
    }

    foreach ($lints as $lint) {
      $this->addLintMessage(ArcanistLintMessage::newFromDictionary($lint));
    }
  }

}
