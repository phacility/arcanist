<?php

/**
 * Implements lint rules, like syntax checks for a specific language.
 *
 * @group linter
 * @stable
 */
abstract class ArcanistLinter {

  const GRANULARITY_FILE = 1;
  const GRANULARITY_DIRECTORY = 2;
  const GRANULARITY_REPOSITORY = 3;
  const GRANULARITY_GLOBAL = 4;

  protected $paths  = array();
  protected $data   = array();
  protected $engine;
  protected $activePath;
  protected $messages = array();

  protected $stopAllLinters = false;

  private $customSeverityMap = array();
  private $customSeverityRules = array();
  private $config = array();

  public function getLinterPriority() {
    return 1.0;
  }

  public function setCustomSeverityMap(array $map) {
    $this->customSeverityMap = $map;
    return $this;
  }

  public function setCustomSeverityRules(array $rules) {
    $this->customSeverityRules = $rules;
    return $this;
  }

  public function setConfig(array $config) {
    $this->config = $config;
    return $this;
  }

  protected function getConfig($key, $default = null) {
    return idx($this->config, $key, $default);
  }

  public function getActivePath() {
    return $this->activePath;
  }

  public function getOtherLocation($offset, $path = null) {
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

  public function stopAllLinters() {
    $this->stopAllLinters = true;
    return $this;
  }

  public function didStopAllLinters() {
    return $this->stopAllLinters;
  }

  public function addPath($path) {
    $this->paths[$path] = $path;
    return $this;
  }

  public function setPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  /**
   * Filter out paths which this linter doesn't act on (for example, because
   * they are binaries and the linter doesn't apply to binaries).
   */
  private function filterPaths($paths) {
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

      $keep[] = $path;
    }

    return $keep;
  }

  public function getPaths() {
    return $this->filterPaths(array_values($this->paths));
  }

  public function addData($path, $data) {
    $this->data[$path] = $data;
    return $this;
  }

  protected function getData($path) {
    if (!array_key_exists($path, $this->data)) {
      $this->data[$path] = $this->getEngine()->loadData($path);
    }
    return $this->data[$path];
  }

  public function setEngine(ArcanistLintEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  protected function getEngine() {
    return $this->engine;
  }

  public function getCacheVersion() {
    return 0;
  }

  public function getLintMessageFullCode($short_code) {
    return $this->getLinterName().$short_code;
  }

  public function getLintMessageSeverity($code) {
    $map = $this->customSeverityMap;
    if (isset($map[$code])) {
      return $map[$code];
    }

    $map = $this->getLintSeverityMap();
    if (isset($map[$code])) {
      return $map[$code];
    }

    foreach ($this->customSeverityRules as $rule => $severity) {
      if (preg_match($rule, $code)) {
        return $severity;
      }
    }

    return $this->getDefaultMessageSeverity($code);
  }

  protected function getDefaultMessageSeverity($code) {
    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  public function isMessageEnabled($code) {
    return ($this->getLintMessageSeverity($code) !==
            ArcanistLintSeverity::SEVERITY_DISABLED);
  }

  public function getLintMessageName($code) {
    $map = $this->getLintNameMap();
    if (isset($map[$code])) {
      return $map[$code];
    }
    return "Unknown lint message!";
  }

  protected function addLintMessage(ArcanistLintMessage $message) {
    if (!$this->getEngine()->getCommitHookMode()) {
      $root = $this->getEngine()->getWorkingCopy()->getProjectRoot();
      $path = Filesystem::resolvePath($message->getPath(), $root);
      $message->setPath(Filesystem::readablePath($path, $root));
    }
    $this->messages[] = $message;
    return $message;
  }

  public function getLintMessages() {
    return $this->messages;
  }

  protected function raiseLintAtLine(
    $line,
    $char,
    $code,
    $desc,
    $original = null,
    $replacement = null) {

    $message = id(new ArcanistLintMessage())
      ->setPath($this->getActivePath())
      ->setLine($line)
      ->setChar($char)
      ->setCode($this->getLintMessageFullCode($code))
      ->setSeverity($this->getLintMessageSeverity($code))
      ->setName($this->getLintMessageName($code))
      ->setDescription($desc)
      ->setOriginalText($original)
      ->setReplacementText($replacement);

    return $this->addLintMessage($message);
  }

  protected function raiseLintAtPath(
    $code,
    $desc) {

    return $this->raiseLintAtLine(null, null, $code, $desc, null, null);
  }

  protected function raiseLintAtOffset(
    $offset,
    $code,
    $desc,
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
      $desc,
      $original,
      $replacement);
  }

  public function willLintPath($path) {
    $this->stopAllLinters = false;
    $this->activePath = $path;
  }

  public function canRun() {
    return true;
  }

  public function willLintPaths(array $paths) {
    return;
  }

  abstract public function lintPath($path);
  abstract public function getLinterName();

  public function didRunLinters() {
    // This is a hook.
  }

  protected function isCodeEnabled($code) {
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
    return array(
      'severity' => 'optional map<string, string>',
      'severity.rules' => 'optional map<string, string>',
    );
  }

  public function setLinterConfigurationValue($key, $value) {
    $sev_map = array(
      'error' => ArcanistLintSeverity::SEVERITY_ERROR,
      'warning' => ArcanistLintSeverity::SEVERITY_WARNING,
      'autofix' => ArcanistLintSeverity::SEVERITY_AUTOFIX,
      'advice' => ArcanistLintSeverity::SEVERITY_ADVICE,
      'disabled' => ArcanistLintSeverity::SEVERITY_DISABLED,
    );

    switch ($key) {
      case 'severity':
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
    }

    throw new Exception("Incomplete implementation: {$key}!");
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
