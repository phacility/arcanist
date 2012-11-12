<?php

/**
 * Implements linting via Conduit RPC call.
 * Slow by definition, but allows sophisticated linting that relies on
 * stuff like big indexes of a codebase.
 * Recommended usage is to gate these to the advice lint level.
 *
 * The conduit endpoint should implement a method named the same as
 * the value of ArcanistConduitLinter::CONDUIT_METHOD.
 *
 * It takes an array with a key 'file_contents' which is an array mapping
 * file paths to their complete contents.
 *
 * It should return an array mapping those same paths to arrays describing the
 * lint for each path.
 *
 * The lint for a path is described as a list of structured dictionaries.
 *
 * The dictionary structure is effectively defined by
 * ArcanistLintMessage::newFromDictionary.
 *
 * Effective keys are:
 *  'path' => must match passed in path.
 *  'line'
 *  'char'
 *  'code'
 *  'severity' => Must match a constant in ArcanistLintSeverity.
 *  'name'
 *  'description'
 *  'original' & 'replacement' => optional patch information
 *
 * This class is intended for customization via instantiation, not via
 * subclassing.
 */
final class ArcanistConduitLinter extends ArcanistLinter {
  const CONDUIT_METHOD = 'lint.getalllint';

  private $conduitURI;
  private $linterName;
  private $lintByPath; // array(/pa/th/ => <lint>), valid after willLintPaths().

  public function __construct($conduit_uri, $linter_name) {
    $this->conduitURI = $conduit_uri;
    $this->linterName = $linter_name;
  }

  public function willLintPaths(array $paths) {
    // Load all file path data into $this->data.
    array_map(array($this, 'getData'), $paths);

    $conduit = new ConduitClient($this->conduitURI);

    $this->lintByPath = $conduit->callMethodSynchronous(
      self::CONDUIT_METHOD,
      array(
        'file_contents' => $this->data,
      )
    );
  }

  public function lintPath($path) {
    $lint_for_path = idx($this->lintByPath, $path);
    if (!$lint_for_path) {
      return;
    }

    foreach ($lint_for_path as $lint) {
      $this->addLintMessage(ArcanistLintMessage::newFromDictionary($lint));
    }
  }

  public function getLinterName() {
    return $this->linterName;
  }

  public function getLintSeverityMap() {
    // The rationale here is that this class will only be used for custom
    // linting in installations. No two server endpoints will be the same across
    // different instantiations. Therefore, the server can handle all severity
    // customization directly.
    throw new ArcanistUsageException(
      'ArcanistConduitLinter does not support client-side severity '.
      'customization.'
    );
  }

  public function getLintNameMap() {
    // See getLintSeverityMap for rationale.
    throw new ArcanistUsageException(
      'ArcanistConduitLinter does not support a name map.'
    );
  }
}
