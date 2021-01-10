<?php

/**
 * Implements lint rules, like syntax checks for a specific language.
 *
 * @task info Human Readable Information
 * @task state Runtime State
 * @task exec Executing Linters
 */
abstract class ArcanistLinter extends Phobject {

  const GRANULARITY_FILE = 1;
  const GRANULARITY_DIRECTORY = 2;
  const GRANULARITY_REPOSITORY = 3;
  const GRANULARITY_GLOBAL = 4;

  private $id;
  protected $paths = array();
  private $filteredPaths = null;
  protected $data = array();
  protected $engine;
  protected $activePath;
  protected $messages = array();

  protected $stopAllLinters = false;

  private $customSeverityMap = array();
  private $customSeverityRules = array();


/* -(  Human Readable Information  )----------------------------------------- */


  /**
   * Return an optional informative URI where humans can learn more about this
   * linter.
   *
   * For most linters, this should return a link to the project home page. This
   * is shown on `arc linters`.
   *
   * @return string|null Optionally, return an informative URI.
   * @task info
   */
  public function getInfoURI() {
    return null;
  }

  /**
   * Return a brief human-readable description of the linter.
   *
   * These should be a line or two, and are shown on `arc linters`.
   *
   * @return string|null Optionally, return a brief human-readable description.
   * @task info
   */
  public function getInfoDescription() {
    return null;
  }

  /**
   * Return arbitrary additional information.
   *
   * Linters can use this method to provide arbitrary additional information to
   * be included in the output of `arc linters`.
   *
   * @return map<string, string>  A mapping of header to body content for the
   *                              additional information sections.
   * @task info
   */
  public function getAdditionalInformation() {
    return array();
  }

  /**
   * Return a human-readable linter name.
   *
   * These are used by `arc linters`, and can let you give a linter a more
   * presentable name.
   *
   * @return string Human-readable linter name.
   * @task info
   */
  public function getInfoName() {
    return nonempty(
      $this->getLinterName(),
      $this->getLinterConfigurationName(),
      get_class($this));
  }


/* -(  Runtime State  )------------------------------------------------------ */


  /**
   * @task state
   */
  final public function getActivePath() {
    return $this->activePath;
  }


  /**
   * @task state
   */
  final public function setActivePath($path) {
    $this->stopAllLinters = false;
    $this->activePath = $path;
    return $this;
  }


  /**
   * @task state
   */
  final public function setEngine(ArcanistLintEngine $engine) {
    $this->engine = $engine;
    return $this;
  }


  /**
   * @task state
   */
  final protected function getEngine() {
    return $this->engine;
  }


  /**
   * Set the internal ID for this linter.
   *
   * This ID is assigned automatically by the @{class:ArcanistLintEngine}.
   *
   * @param string Unique linter ID.
   * @return this
   * @task state
   */
  final public function setLinterID($id) {
    $this->id = $id;
    return $this;
  }


  /**
   * Get the internal ID for this linter.
   *
   * Retrieves an internal linter ID managed by the @{class:ArcanistLintEngine}.
   * This ID is a unique scalar which distinguishes linters in a list.
   *
   * @return string Unique linter ID.
   * @task state
   */
  final public function getLinterID() {
    return $this->id;
  }


/* -(  Executing Linters  )-------------------------------------------------- */


  /**
   * Hook called before a list of paths are linted.
   *
   * Parallelizable linters can start multiple requests in parallel here,
   * to improve performance. They can implement @{method:didLintPaths} to
   * collect results.
   *
   * Linters which are not parallelizable should normally ignore this callback
   * and implement @{method:lintPath} instead.
   *
   * @param list<string> A list of paths to be linted
   * @return void
   * @task exec
   */
  public function willLintPaths(array $paths) {
    return;
  }


  /**
   * Hook called for each path to be linted.
   *
   * Linters which are not parallelizable can do work here.
   *
   * Linters which are parallelizable may want to ignore this callback and
   * implement @{method:willLintPaths} and @{method:didLintPaths} instead.
   *
   * @param string Path to lint.
   * @return void
   * @task exec
   */
  public function lintPath($path) {
    return;
  }


  /**
   * Hook called after a list of paths are linted.
   *
   * Parallelizable linters can collect results here.
   *
   * Linters which are not paralleizable should normally ignore this callback
   * and implement @{method:lintPath} instead.
   *
   * @param list<string> A list of paths which were linted.
   * @return void
   * @task exec
   */
  public function didLintPaths(array $paths) {
    return;
  }

  public function getLinterPriority() {
    return 1.0;
  }

  public function setCustomSeverityMap(array $map) {
    $this->customSeverityMap = $map;
    return $this;
  }

  public function addCustomSeverityMap(array $map) {
    $this->customSeverityMap = $this->customSeverityMap + $map;
    return $this;
  }

  public function setCustomSeverityRules(array $rules) {
    $this->customSeverityRules = $rules;
    return $this;
  }

  final public function getProjectRoot() {
    $engine = $this->getEngine();
    if (!$engine) {
      throw new Exception(
        pht(
          'You must call %s before you can call %s.',
          'setEngine()',
          __FUNCTION__.'()'));
    }

    $working_copy = $engine->getWorkingCopy();
    if (!$working_copy) {
      return null;
    }

    return $working_copy->getProjectRoot();
  }

  final public function getOtherLocation($offset, $path = null) {
    if ($path === null) {
      $path = $this->getActivePath();
    }

    list($line, $char) = $this->getEngine()->getLineAndCharFromOffset(
      $path,
      $offset);

    return array(
      'path' => $path,
      'line' => $line + 1,
      'char' => $char,
    );
  }

  final public function stopAllLinters() {
    $this->stopAllLinters = true;
    return $this;
  }

  final public function didStopAllLinters() {
    return $this->stopAllLinters;
  }

  final public function addPath($path) {
    $this->paths[$path] = $path;
    $this->filteredPaths = null;
    return $this;
  }

  final public function setPaths(array $paths) {
    $this->paths = $paths;
    $this->filteredPaths = null;
    return $this;
  }

  /**
   * Filter out paths which this linter doesn't act on (for example, because
   * they are binaries and the linter doesn't apply to binaries).
   *
   * @param  list<string>
   * @return list<string>
   */
  private function filterPaths(array $paths) {
    $engine = $this->getEngine();

    $keep = array();
    foreach ($paths as $path) {
      if (!$this->shouldLintDeletedFiles() && !$engine->pathExists($path)) {
        continue;
      }

      if (!$this->shouldLintDirectories() && $engine->isDirectory($path)) {
        continue;
      }

      if (!$this->shouldLintBinaryFiles() && $engine->isBinaryFile($path)) {
        continue;
      }

      if (!$this->shouldLintSymbolicLinks() && $engine->isSymbolicLink($path)) {
        continue;
      }

      $keep[] = $path;
    }

    return $keep;
  }

  final public function getPaths() {
    if ($this->filteredPaths === null) {
      $this->filteredPaths = $this->filterPaths(array_values($this->paths));
    }

    return $this->filteredPaths;
  }

  final public function addData($path, $data) {
    $this->data[$path] = $data;
    return $this;
  }

  final protected function getData($path) {
    if (!array_key_exists($path, $this->data)) {
      $this->data[$path] = $this->getEngine()->loadData($path);
    }
    return $this->data[$path];
  }

  public function getCacheVersion() {
    return 0;
  }


  final public function getLintMessageFullCode($short_code) {
    return $this->getLinterName().$short_code;
  }

  final public function getLintMessageSeverity($code) {
    $map = $this->customSeverityMap;
    if (isset($map[$code])) {
      return $map[$code];
    }

    foreach ($this->customSeverityRules as $rule => $severity) {
      if (preg_match($rule, $code)) {
        return $severity;
      }
    }

    $map = $this->getLintSeverityMap();
    if (isset($map[$code])) {
      return $map[$code];
    }

    return $this->getDefaultMessageSeverity($code);
  }

  protected function getDefaultMessageSeverity($code) {
    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  final public function isMessageEnabled($code) {
    return ($this->getLintMessageSeverity($code) !==
            ArcanistLintSeverity::SEVERITY_DISABLED);
  }

  final public function getLintMessageName($code) {
    $map = $this->getLintNameMap();
    if (isset($map[$code])) {
      return $map[$code];
    }
    return pht('Unknown lint message!');
  }

  final protected function addLintMessage(ArcanistLintMessage $message) {
    $root = $this->getProjectRoot();
    $path = Filesystem::resolvePath($message->getPath(), $root);
    $message->setPath(Filesystem::readablePath($path, $root));

    $this->messages[] = $message;
    return $message;
  }

  final public function getLintMessages() {
    return $this->messages;
  }

  final public function raiseLintAtLine(
    $line,
    $char,
    $code,
    $description,
    $original = null,
    $replacement = null) {

    $message = id(new ArcanistLintMessage())
      ->setPath($this->getActivePath())
      ->setLine($line)
      ->setChar($char)
      ->setCode($this->getLintMessageFullCode($code))
      ->setSeverity($this->getLintMessageSeverity($code))
      ->setName($this->getLintMessageName($code))
      ->setDescription($description)
      ->setOriginalText($original)
      ->setReplacementText($replacement);

    return $this->addLintMessage($message);
  }

  final public function raiseLintAtPath($code, $desc) {
    return $this->raiseLintAtLine(null, null, $code, $desc, null, null);
  }

  final public function raiseLintAtOffset(
    $offset,
    $code,
    $description,
    $original = null,
    $replacement = null) {

    $path = $this->getActivePath();
    $engine = $this->getEngine();
    if ($offset === null) {
      $line = null;
      $char = null;
    } else {
      list($line, $char) = $engine->getLineAndCharFromOffset($path, $offset);
    }

    return $this->raiseLintAtLine(
      $line + 1,
      $char + 1,
      $code,
      $description,
      $original,
      $replacement);
  }

  public function canRun() {
    return true;
  }

  abstract public function getLinterName();

  public function getVersion() {
    return null;
  }

  final protected function isCodeEnabled($code) {
    $severity = $this->getLintMessageSeverity($code);
    return $this->getEngine()->isSeverityEnabled($severity);
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array();
  }

  public function getCacheGranularity() {
    return self::GRANULARITY_FILE;
  }

  /**
   * If this linter is selectable via `.arclint` configuration files, return
   * a short, human-readable name to identify it. For example, `"jshint"` or
   * `"pep8"`.
   *
   * If you do not implement this method, the linter will not be selectable
   * through `.arclint` files.
   */
  public function getLinterConfigurationName() {
    return null;
  }

  public function getLinterConfigurationOptions() {
    if (!$this->canCustomizeLintSeverities()) {
      return array();
    }

    return array(
      'severity' => array(
        'type' => 'optional map<string|int, string>',
        'help' => pht(
          'Provide a map from lint codes to adjusted severity levels: error, '.
          'warning, advice, autofix or disabled.'),
      ),
      'severity.rules' => array(
        'type' => 'optional map<string, string>',
        'help' => pht(
          'Provide a map of regular expressions to severity levels. All '.
          'matching codes have their severity adjusted.'),
      ),
      'standard' => array(
        'type' => 'optional string | list<string>',
        'help' => pht('The coding standard(s) to apply.'),
      ),
    );
  }

  public function setLinterConfigurationValue($key, $value) {
    $sev_map = array(
      'error'    => ArcanistLintSeverity::SEVERITY_ERROR,
      'warning'  => ArcanistLintSeverity::SEVERITY_WARNING,
      'autofix'  => ArcanistLintSeverity::SEVERITY_AUTOFIX,
      'advice'   => ArcanistLintSeverity::SEVERITY_ADVICE,
      'disabled' => ArcanistLintSeverity::SEVERITY_DISABLED,
    );

    switch ($key) {
      case 'severity':
        if (!$this->canCustomizeLintSeverities()) {
          break;
        }

        $custom = array();
        foreach ($value as $code => $severity) {
          if (empty($sev_map[$severity])) {
            $valid = implode(', ', array_keys($sev_map));
            throw new Exception(
              pht(
                'Unknown lint severity "%s". Valid severities are: %s.',
                $severity,
                $valid));
          }
          $code = $this->getLintCodeFromLinterConfigurationKey($code);
          $custom[$code] = $severity;
        }

        $this->setCustomSeverityMap($custom);
        return;

      case 'severity.rules':
        if (!$this->canCustomizeLintSeverities()) {
          break;
        }

        foreach ($value as $rule => $severity) {
          if (@preg_match($rule, '') === false) {
            throw new Exception(
              pht(
                'Severity rule "%s" is not a valid regular expression.',
                $rule));
          }
          if (empty($sev_map[$severity])) {
            $valid = implode(', ', array_keys($sev_map));
            throw new Exception(
              pht(
                'Unknown lint severity "%s". Valid severities are: %s.',
                $severity,
                $valid));
          }
        }
        $this->setCustomSeverityRules($value);
        return;

      case 'standard':
        $standards = (array)$value;

        foreach ($standards as $standard_name) {
          $standard = ArcanistLinterStandard::getStandard(
            $standard_name,
            $this);

          foreach ($standard->getLinterConfiguration() as $k => $v) {
            $this->setLinterConfigurationValue($k, $v);
          }
          $this->addCustomSeverityMap($standard->getLinterSeverityMap());
        }

        return;


    }

    throw new Exception(pht('Incomplete implementation: %s!', $key));
  }

  protected function canCustomizeLintSeverities() {
    return true;
  }

  protected function shouldLintBinaryFiles() {
    return false;
  }

  protected function shouldLintDeletedFiles() {
    return false;
  }

  protected function shouldLintDirectories() {
    return false;
  }

  protected function shouldLintSymbolicLinks() {
    return false;
  }

  /**
   * Map a configuration lint code to an `arc` lint code. Primarily, this is
   * intended for validation, but can also be used to normalize case or
   * otherwise be more permissive in accepted inputs.
   *
   * If the code is not recognized, you should throw an exception.
   *
   * @param string  Code specified in configuration.
   * @return string  Normalized code to use in severity map.
   */
  protected function getLintCodeFromLinterConfigurationKey($code) {
    return $code;
  }

}
