<?php

/**
 * Manages lint execution. When you run 'arc lint' or 'arc diff', Arcanist
 * attempts to run lint rules using a lint engine.
 *
 * Lint engines are high-level strategic classes which do not contain any
 * actual linting rules. Linting rules live in `Linter` classes. The lint
 * engine builds and configures linters.
 *
 * Most modern linters can be configured with an `.arclint` file, which is
 * managed by the builtin @{class:ArcanistConfigurationDrivenLintEngine}.
 * Consult the documentation for more information on these files.
 *
 * In the majority of cases, you do not need to write a custom lint engine.
 * For example, to add new rules for a new language, write a linter instead.
 * However, if you have a very advanced or specialized use case, you can write
 * a custom lint engine by extending this class; custom lint engines are more
 * powerful but much more complex than the builtin engines.
 *
 * The lint engine is given a list of paths (generally, the paths that you
 * modified in your change) and determines which linters to run on them. The
 * linters themselves are responsible for actually analyzing file text and
 * finding warnings and errors. For example, if the modified paths include some
 * JS files and some Python files, you might want to run JSLint on the JS files
 * and PyLint on the Python files.
 *
 * You can also run multiple linters on a single file. For instance, you might
 * run one linter on all text files to make sure they don't have trailing
 * whitespace, or enforce tab vs space rules, or make sure there are enough
 * curse words in them.
 *
 * You can test an engine like this:
 *
 *   arc lint --engine YourLintEngineClassName --lintall some_file.py
 *
 * ...which will show you all the lint issues raised in the file.
 *
 * See @{article@phabricator:Arcanist User Guide: Customizing Lint, Unit Tests
 * and Workflows} for more information about configuring lint engines.
 */
abstract class ArcanistLintEngine extends Phobject {

  protected $workingCopy;
  protected $paths = array();
  protected $fileData = array();

  protected $charToLine = array();
  protected $lineToFirstChar = array();
  private $cachedResults;
  private $cacheVersion;
  private $repositoryVersion;
  private $results = array();
  private $stopped = array();
  private $minimumSeverity = ArcanistLintSeverity::SEVERITY_DISABLED;

  private $changedLines = array();

  private $configurationManager;

  private $linterResources = array();

  public function __construct() {}

  final public function setConfigurationManager(
    ArcanistConfigurationManager $configuration_manager) {
    $this->configurationManager = $configuration_manager;
    return $this;
  }

  final public function getConfigurationManager() {
    return $this->configurationManager;
  }

  final public function setWorkingCopy(
    ArcanistWorkingCopyIdentity $working_copy) {
    $this->workingCopy = $working_copy;
    return $this;
  }

  final public function getWorkingCopy() {
    return $this->workingCopy;
  }

  final public function setPaths($paths) {
    $this->paths = $paths;
    return $this;
  }

  public function getPaths() {
    return $this->paths;
  }

  final public function setPathChangedLines($path, $changed) {
    if ($changed === null) {
      $this->changedLines[$path] = null;
    } else {
      $this->changedLines[$path] = array_fill_keys($changed, true);
    }
    return $this;
  }

  final public function getPathChangedLines($path) {
    return idx($this->changedLines, $path);
  }

  final public function setFileData($data) {
    $this->fileData = $data + $this->fileData;
    return $this;
  }

  final public function loadData($path) {
    if (!isset($this->fileData[$path])) {
      $disk_path = $this->getFilePathOnDisk($path);
      $this->fileData[$path] = Filesystem::readFile($disk_path);
    }
    return $this->fileData[$path];
  }

  public function pathExists($path) {
    $disk_path = $this->getFilePathOnDisk($path);
    return Filesystem::pathExists($disk_path);
  }

  final public function isDirectory($path) {
    $disk_path = $this->getFilePathOnDisk($path);
    return is_dir($disk_path);
  }

  final public function isBinaryFile($path) {
    try {
      $data = $this->loadData($path);
    } catch (Exception $ex) {
      return false;
    }

    return ArcanistDiffUtils::isHeuristicBinaryFile($data);
  }

  final public function isSymbolicLink($path) {
    return is_link($this->getFilePathOnDisk($path));
  }

  final public function getFilePathOnDisk($path) {
    return Filesystem::resolvePath(
      $path,
      $this->getWorkingCopy()->getProjectRoot());
  }

  final public function setMinimumSeverity($severity) {
    $this->minimumSeverity = $severity;
    return $this;
  }

  final public function run() {
    $linters = $this->buildLinters();
    if (!$linters) {
      throw new ArcanistNoEffectException(pht('No linters to run.'));
    }

    foreach ($linters as $key => $linter) {
      $linter->setLinterID($key);
    }

    $linters = msort($linters, 'getLinterPriority');
    foreach ($linters as $linter) {
      $linter->setEngine($this);
    }

    $have_paths = false;
    foreach ($linters as $linter) {
      if ($linter->getPaths()) {
        $have_paths = true;
        break;
      }
    }

    if (!$have_paths) {
      throw new ArcanistNoEffectException(pht('No paths are lintable.'));
    }

    $versions = array($this->getCacheVersion());

    foreach ($linters as $linter) {
      $version = get_class($linter).':'.$linter->getCacheVersion();

      $symbols = id(new PhutilSymbolLoader())
        ->setType('class')
        ->setName(get_class($linter))
        ->selectSymbolsWithoutLoading();
      $symbol = idx($symbols, 'class$'.get_class($linter));
      if ($symbol) {
        $version .= ':'.md5_file(
          phutil_get_library_root($symbol['library']).'/'.$symbol['where']);
      }

      $versions[] = $version;
    }

    $this->cacheVersion = crc32(implode("\n", $versions));

    $runnable = $this->getRunnableLinters($linters);

    $this->stopped = array();

    $exceptions = $this->executeLinters($runnable);

    foreach ($runnable as $linter) {
      foreach ($linter->getLintMessages() as $message) {
        $this->validateLintMessage($linter, $message);

        if (!$this->isSeverityEnabled($message->getSeverity())) {
          continue;
        }
        if (!$this->isRelevantMessage($message)) {
          continue;
        }
        $message->setGranularity($linter->getCacheGranularity());
        $result = $this->getResultForPath($message->getPath());
        $result->addMessage($message);
      }
    }

    if ($this->cachedResults) {
      foreach ($this->cachedResults as $path => $messages) {
        $messages = idx($messages, $this->cacheVersion, array());
        $repository_version = idx($messages, 'repository_version');
        unset($messages['stopped']);
        unset($messages['repository_version']);
        foreach ($messages as $message) {
          $use_cache = $this->shouldUseCache(
            idx($message, 'granularity'),
            $repository_version);
          if ($use_cache) {
            $this->getResultForPath($path)->addMessage(
              ArcanistLintMessage::newFromDictionary($message));
          }
        }
      }
    }

    foreach ($this->results as $path => $result) {
      $disk_path = $this->getFilePathOnDisk($path);
      $result->setFilePathOnDisk($disk_path);
      if (isset($this->fileData[$path])) {
        $result->setData($this->fileData[$path]);
      } else if ($disk_path && Filesystem::pathExists($disk_path)) {
        // TODO: this may cause us to, e.g., load a large binary when we only
        // raised an error about its filename. We could refine this by looking
        // through the lint messages and doing this load only if any of them
        // have original/replacement text or something like that.
        try {
          $this->fileData[$path] = Filesystem::readFile($disk_path);
          $result->setData($this->fileData[$path]);
        } catch (FilesystemException $ex) {
          // Ignore this, it's noncritical that we access this data and it
          // might be unreadable or a directory or whatever else for plenty
          // of legitimate reasons.
        }
      }
    }

    if ($exceptions) {
      throw new PhutilAggregateException(
        pht('Some linters failed:'),
        $exceptions);
    }

    return $this->results;
  }

  final public function isSeverityEnabled($severity) {
    $minimum = $this->minimumSeverity;
    return ArcanistLintSeverity::isAtLeastAsSevere($severity, $minimum);
  }

  private function shouldUseCache(
    $cache_granularity,
    $repository_version) {

    switch ($cache_granularity) {
      case ArcanistLinter::GRANULARITY_FILE:
        return true;
      case ArcanistLinter::GRANULARITY_DIRECTORY:
      case ArcanistLinter::GRANULARITY_REPOSITORY:
        return ($this->repositoryVersion == $repository_version);
      default:
        return false;
    }
  }

  /**
   * @param dict<string path, dict<string version, list<dict message>>>
   * @return this
   */
  final public function setCachedResults(array $results) {
    $this->cachedResults = $results;
    return $this;
  }

  final public function getResults() {
    return $this->results;
  }

  final public function getStoppedPaths() {
    return $this->stopped;
  }

  abstract public function buildLinters();

  final public function setRepositoryVersion($version) {
    $this->repositoryVersion = $version;
    return $this;
  }

  private function isRelevantMessage(ArcanistLintMessage $message) {
    // When a user runs "arc lint", we default to raising only warnings on
    // lines they have changed (errors are still raised anywhere in the
    // file). The list of $changed lines may be null, to indicate that the
    // path is a directory or a binary file so we should not exclude
    // warnings.

    if (!$this->changedLines ||
        $message->isError() ||
        $message->shouldBypassChangedLineFiltering()) {
      return true;
    }

    $locations = $message->getOtherLocations();
    $locations[] = $message->toDictionary();

    foreach ($locations as $location) {
      $path = idx($location, 'path', $message->getPath());

      if (!array_key_exists($path, $this->changedLines)) {
        if (phutil_is_windows()) {
          // We try checking the UNIX path form as well, on Windows.  Linters
          // store noramlized paths, which use the Windows-style "\" as a
          // delimiter; as such, they don't match the UNIX-style paths stored
          // in changedLines, which come from the VCS.
          $path = str_replace('\\', '/', $path);
          if (!array_key_exists($path, $this->changedLines)) {
            continue;
          }
        } else {
          continue;
        }
      }

      $changed = $this->getPathChangedLines($path);

      if ($changed === null || !$location['line']) {
        return true;
      }

      $last_line = $location['line'];
      if (isset($location['original'])) {
        $last_line += substr_count($location['original'], "\n");
      }

      for ($l = $location['line']; $l <= $last_line; $l++) {
        if (!empty($changed[$l])) {
          return true;
        }
      }
    }

    return false;
  }

  final protected function getResultForPath($path) {
    if (empty($this->results[$path])) {
      $result = new ArcanistLintResult();
      $result->setPath($path);
      $result->setCacheVersion($this->cacheVersion);
      $this->results[$path] = $result;
    }
    return $this->results[$path];
  }

  final public function getLineAndCharFromOffset($path, $offset) {
    if (!isset($this->charToLine[$path])) {
      $char_to_line = array();
      $line_to_first_char = array();

      $lines = explode("\n", $this->loadData($path));
      $line_number = 0;
      $line_start = 0;
      foreach ($lines as $line) {
        $len = strlen($line) + 1; // Account for "\n".
        $line_to_first_char[] = $line_start;
        $line_start += $len;
        for ($ii = 0; $ii < $len; $ii++) {
          $char_to_line[] = $line_number;
        }
        $line_number++;
      }
      $this->charToLine[$path] = $char_to_line;
      $this->lineToFirstChar[$path] = $line_to_first_char;
    }

    $line = $this->charToLine[$path][$offset];
    $char = $offset - $this->lineToFirstChar[$path][$line];

    return array($line, $char);
  }

  protected function getCacheVersion() {
    return 1;
  }


  /**
   * Get a named linter resource shared by another linter.
   *
   * This mechanism allows linters to share arbitrary resources, like the
   * results of computation. If several linters need to perform the same
   * expensive computation step, they can use a named resource to synchronize
   * construction of the result so it doesn't need to be built multiple
   * times.
   *
   * @param string  Resource identifier.
   * @param wild    Optionally, default value to return if resource does not
   *                exist.
   * @return wild   Resource, or default value if not present.
   */
  public function getLinterResource($key, $default = null) {
    return idx($this->linterResources, $key, $default);
  }


  /**
   * Set a linter resource that other linters can access.
   *
   * See @{method:getLinterResource} for a description of this mechanism.
   *
   * @param string Resource identifier.
   * @param wild   Resource.
   * @return this
   */
  public function setLinterResource($key, $value) {
    $this->linterResources[$key] = $value;
    return $this;
  }


  private function getRunnableLinters(array $linters) {
    assert_instances_of($linters, 'ArcanistLinter');

    // TODO: The canRun() mechanism is only used by one linter, and just
    // silently disables the linter. Almost every other linter handles this
    // by throwing `ArcanistMissingLinterException`. Both mechanisms are not
    // ideal; linters which can not run should emit a message, get marked as
    // "skipped", and allow execution to continue. See T7045.

    $runnable = array();
    foreach ($linters as $key => $linter) {
      if ($linter->canRun()) {
        $runnable[$key] = $linter;
      }
    }

    return $runnable;
  }

  private function executeLinters(array $runnable) {
    assert_instances_of($runnable, 'ArcanistLinter');

    $all_paths = $this->getPaths();
    $path_chunks = array_chunk($all_paths, 32, $preserve_keys = true);

    $exception_lists = array();
    foreach ($path_chunks as $chunk) {
      $exception_lists[] = $this->executeLintersOnChunk($runnable, $chunk);
    }

    return array_mergev($exception_lists);
  }


  private function executeLintersOnChunk(array $runnable, array $path_list) {
    assert_instances_of($runnable, 'ArcanistLinter');

    $path_map = array_fuse($path_list);

    $exceptions = array();
    $did_lint = array();
    foreach ($runnable as $linter) {
      $linter_id = $linter->getLinterID();
      $paths = $linter->getPaths();

      foreach ($paths as $key => $path) {
        // If we aren't running this path in the current chunk of paths,
        // skip it completely.
        if (empty($path_map[$path])) {
          unset($paths[$key]);
          continue;
        }

        // Make sure each path has a result generated, even if it is empty
        // (i.e., the file has no lint messages).
        $result = $this->getResultForPath($path);

        // If a linter has stopped all other linters for this path, don't
        // actually run the linter.
        if (isset($this->stopped[$path])) {
          unset($paths[$key]);
          continue;
        }

        // If we have a cached result for this path, don't actually run the
        // linter.
        if (isset($this->cachedResults[$path][$this->cacheVersion])) {
          $cached_result = $this->cachedResults[$path][$this->cacheVersion];

          $use_cache = $this->shouldUseCache(
            $linter->getCacheGranularity(),
            idx($cached_result, 'repository_version'));

          if ($use_cache) {
            unset($paths[$key]);
            if (idx($cached_result, 'stopped') == $linter_id) {
              $this->stopped[$path] = $linter_id;
            }
          }
        }
      }

      $paths = array_values($paths);

      if (!$paths) {
        continue;
      }

      try {
        $this->executeLinterOnPaths($linter, $paths);
        $did_lint[] = array($linter, $paths);
      } catch (Exception $ex) {
        $exceptions[] = $ex;
      }
    }

    foreach ($did_lint as $info) {
      list($linter, $paths) = $info;
      try {
        $this->executeDidLintOnPaths($linter, $paths);
      } catch (Exception $ex) {
        $exceptions[] = $ex;
      }
    }

    return $exceptions;
  }

  private function beginLintServiceCall(ArcanistLinter $linter, array $paths) {
    $profiler = PhutilServiceProfiler::getInstance();

    return $profiler->beginServiceCall(
      array(
        'type' => 'lint',
        'linter' => $linter->getInfoName(),
        'paths' => $paths,
      ));
  }

  private function endLintServiceCall($call_id) {
    $profiler = PhutilServiceProfiler::getInstance();
    $profiler->endServiceCall($call_id, array());
  }

  private function executeLinterOnPaths(ArcanistLinter $linter, array $paths) {
    $call_id = $this->beginLintServiceCall($linter, $paths);

    try {
      $linter->willLintPaths($paths);
      foreach ($paths as $path) {
        $linter->setActivePath($path);
        $linter->lintPath($path);
        if ($linter->didStopAllLinters()) {
          $this->stopped[$path] = $linter->getLinterID();
        }
      }
    } catch (Exception $ex) {
      $this->endLintServiceCall($call_id);
      throw $ex;
    }

    $this->endLintServiceCall($call_id);
  }

  private function executeDidLintOnPaths(ArcanistLinter $linter, array $paths) {
    $call_id = $this->beginLintServiceCall($linter, $paths);

    try {
      $linter->didLintPaths($paths);
    } catch (Exception $ex) {
      $this->endLintServiceCall($call_id);
      throw $ex;
    }

    $this->endLintServiceCall($call_id);
  }

  private function validateLintMessage(
    ArcanistLinter $linter,
    ArcanistLintMessage $message) {

    $name = $message->getName();
    if (!strlen($name)) {
      throw new Exception(
        pht(
          'Linter "%s" generated a lint message that is invalid because it '.
          'does not have a name. Lint messages must have a name.',
          get_class($linter)));
    }
  }

}
