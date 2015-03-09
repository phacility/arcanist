<?php

/**
 * Implements lint rules, like syntax checks for a specific language.
 *
 * @task info Human Readable Information
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


/*  -(  Human Readable Information  )---------------------------------------- */

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

  public function getLinterPriority() {
    return 1.0;
  }

  /**
   * TODO: This should be `final`.
   */
  public function setCustomSeverityMap(array $map) {
    $this->customSeverityMap = $map;
    return $this;
  }

  final public function setCustomSeverityRules(array $rules) {
    $this->customSeverityRules = $rules;
    return $this;
  }

  final public function getActivePath() {
    return $this->activePath;
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
    return $this;
  }

  final public function setPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  /**
   * Filter out paths which this linter doesn't act on (for example, because
   * they are binaries and the linter doesn't apply to binaries).
   */
  final private function filterPaths($paths) {
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
    return $this->filterPaths(array_values($this->paths));
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

  final public function setEngine(ArcanistLintEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  final protected function getEngine() {
    return $this->engine;
  }

  public function getCacheVersion() {
    return 0;
  }

  final public function getLintMessageFullCode($short_code) {
    return $this->getLinterName().$short_code;
  }

  final public function getLintMessageSeverity($code, $severity_code = null) {
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

    if ($severity_code !== null) {
      return $this->getDefaultMessageSeverity($severity_code);
    } else {
      return $this->getDefaultMessageSeverity($code);
    }
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
    return 'Unknown lint message!';
  }

  final protected function addLintMessage(ArcanistLintMessage $message) {
    if (!$this->getEngine()->getCommitHookMode()) {
      $root = $this->getEngine()->getWorkingCopy()->getProjectRoot();
      $path = Filesystem::resolvePath($message->getPath(), $root);
      $message->setPath(Filesystem::readablePath($path, $root));
    }
    $this->messages[] = $message;
    return $message;
  }

  final public function getLintMessages() {
    return $this->messages;
  }

  final protected function raiseLintAtLine(
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

  final protected function raiseLintAtPath($code, $desc) {
    return $this->raiseLintAtLine(null, null, $code, $desc, null, null);
  }

  final protected function raiseLintAtOffset(
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

  public function getVersion() {
    return null;
  }

  public function didRunLinters() {
    // This is a hook.
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
    }

    throw new Exception("Incomplete implementation: {$key}!");
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

  /**
   * Retrieve an old lint configuration value from `.arcconfig` or a similar
   * source.
   *
   * Modern linters should use @{method:getConfig} to read configuration from
   * `.arclint`.
   *
   * @param   string  Configuration key to retrieve.
   * @param   wild    Default value to return if key is not present in config.
   * @return  wild    Configured value, or default if no configuration exists.
   */
  final protected function getDeprecatedConfiguration($key, $default = null) {
    // If we're being called in a context without an engine (probably from
    // `arc linters`), just return the default value.
    if (!$this->engine) {
      return $default;
    }

    $config = $this->getEngine()->getConfigurationManager();

    // Construct a sentinel object so we can tell if we're reading config
    // or not.
    $sentinel = (object)array();
    $result = $config->getConfigFromAnySource($key, $sentinel);

    // If we read config, warn the user that this mechanism is deprecated and
    // discouraged.
    if ($result !== $sentinel) {
      $console = PhutilConsole::getConsole();
      $console->writeErr(
        "**%s**: %s\n",
        pht('Deprecation Warning'),
        pht(
          'Configuration option "%s" is deprecated. Generally, linters should '.
          'now be configured using an `.arclint` file. See "Arcanist User '.
          'Guide: Lint" in the documentation for more information.',
          $key));
      return $result;
    }

    return $default;
  }

}
