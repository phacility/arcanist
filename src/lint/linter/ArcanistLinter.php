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
  private $config = array();

  public function setCustomSeverityMap(array $map) {
    $this->customSeverityMap = $map;
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

  public function getPaths() {
    return array_values($this->paths);
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

  protected function newLintAtLine($line, $char, $code, $desc) {
    return id(new ArcanistLintMessage())
      ->setPath($this->getActivePath())
      ->setLine($line)
      ->setChar($char)
      ->setCode($this->getLintMessageFullCode($code))
      ->setSeverity($this->getLintMessageSeverity($code))
      ->setName($this->getLintMessageName($code))
      ->setDescription($desc);
  }

  protected function raiseLintAtLine(
    $line,
    $char,
    $code,
    $desc,
    $original = null,
    $replacement = null) {

    $message = $this->newLintAtLine($line, $char, $code, $desc)
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

  abstract public function willLintPaths(array $paths);
  abstract public function lintPath($path);
  abstract public function getLinterName();

  public function didRunLinters() {
    // This is a hook.
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

}
