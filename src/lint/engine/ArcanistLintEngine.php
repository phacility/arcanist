<?php

/**
 * Manages lint execution. When you run 'arc lint' or 'arc diff', Arcanist
 * checks your .arcconfig to see if you have specified a lint engine in the
 * key "lint.engine". The engine must extend this class. For example:
 *
 *  lang=js
 *  {
 *    // ...
 *    "lint.engine" : "ExampleLintEngine",
 *    // ...
 *  }
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
 * Because lint engines are pretty custom to the rules of a project, you will
 * generally need to build your own. Fortunately, it's pretty easy (and you
 * can use the prebuilt //linters//, you just need to write a little glue code
 * to tell Arcanist which linters to run). For a simple example of how to build
 * a lint engine, see @{class:ExampleLintEngine}.
 *
 * You can test an engine like this:
 *
 *   arc lint --engine ExampleLintEngine --lintall some_file.py
 *
 * ...which will show you all the lint issues raised in the file.
 *
 * See @{article@phabricator:Arcanist User Guide: Customizing Lint, Unit Tests
 * and Workflows} for more information about configuring lint engines.
 *
 * @group lint
 * @stable
 */
abstract class ArcanistLintEngine {

  protected $workingCopy;
  protected $paths = array();
  protected $fileData = array();

  protected $charToLine = array();
  protected $lineToFirstChar = array();
  private $results = array();
  private $minimumSeverity = ArcanistLintSeverity::SEVERITY_DISABLED;

  private $changedLines = array();
  private $commitHookMode = false;
  private $hookAPI;

  private $enableAsyncLint = false;
  private $postponedLinters = array();

  public function __construct() {

  }

  public function setWorkingCopy(ArcanistWorkingCopyIdentity $working_copy) {
    $this->workingCopy = $working_copy;
    return $this;
  }

  public function getWorkingCopy() {
    return $this->workingCopy;
  }

  public function setPaths($paths) {
    $this->paths = $paths;
    return $this;
  }

  public function getPaths() {
    return $this->paths;
  }

  public function setPathChangedLines($path, $changed) {
    if ($changed === null) {
      $this->changedLines[$path] = null;
    } else {
      $this->changedLines[$path] = array_fill_keys($changed, true);
    }
    return $this;
  }

  public function getPathChangedLines($path) {
    return idx($this->changedLines, $path);
  }

  public function setFileData($data) {
    $this->fileData = $data + $this->fileData;
    return $this;
  }

  public function setCommitHookMode($mode) {
    $this->commitHookMode = $mode;
    return $this;
  }

  public function setHookAPI(ArcanistHookAPI $hook_api) {
    $this->hookAPI  = $hook_api;
    return $this;
  }

  public function getHookAPI() {
    return $this->hookAPI;
  }

  public function setEnableAsyncLint($enable_async_lint) {
    $this->enableAsyncLint = $enable_async_lint;
    return $this;
  }

  public function getEnableAsyncLint() {
    return $this->enableAsyncLint;
  }

  public function loadData($path) {
    if (!isset($this->fileData[$path])) {
      if ($this->getCommitHookMode()) {
        $this->fileData[$path] = $this->getHookAPI()
          ->getCurrentFileData($path);
      } else {
        $disk_path = $this->getFilePathOnDisk($path);
        $this->fileData[$path] = Filesystem::readFile($disk_path);
      }
    }
    return $this->fileData[$path];
  }

  public function pathExists($path) {
    if ($this->getCommitHookMode()) {
      $file_data = $this->loadData($path);
      return ($file_data !== null);
    } else {
      $disk_path = $this->getFilePathOnDisk($path);
      return Filesystem::pathExists($disk_path);
    }
  }

  public function getFilePathOnDisk($path) {
    return Filesystem::resolvePath(
      $path,
      $this->getWorkingCopy()->getProjectRoot());
  }

  public function setMinimumSeverity($severity) {
    $this->minimumSeverity = $severity;
    return $this;
  }

  public function getCommitHookMode() {
    return $this->commitHookMode;
  }

  public function run() {
    $stopped = array();
    $linters = $this->buildLinters();

    if (!$linters) {
      throw new ArcanistNoEffectException("No linters to run.");
    }

    $have_paths = false;
    foreach ($linters as $linter) {
      if ($linter->getPaths()) {
        $have_paths = true;
        break;
      }
    }

    if (!$have_paths) {
      throw new ArcanistNoEffectException("No paths are lintable.");
    }

    $exceptions = array();
    foreach ($linters as $linter_name => $linter) {
      try {
        $linter->setEngine($this);
        if (!$linter->canRun()) {
          continue;
        }
        $paths = $linter->getPaths();

        foreach ($paths as $key => $path) {
          // Make sure each path has a result generated, even if it is empty
          // (i.e., the file has no lint messages).
          $result = $this->getResultForPath($path);
          if (isset($stopped[$path])) {
            unset($paths[$key]);
          }
        }
        $paths = array_values($paths);

        if ($paths) {
          $linter->willLintPaths($paths);
          foreach ($paths as $path) {
            $linter->willLintPath($path);
            $linter->lintPath($path);
            if ($linter->didStopAllLinters()) {
              $stopped[$path] = true;
            }
          }
        }

        $minimum = $this->minimumSeverity;
        foreach ($linter->getLintMessages() as $message) {
          if (!ArcanistLintSeverity::isAtLeastAsSevere($message, $minimum)) {
            continue;
          }
          if (!$this->isRelevantMessage($message)) {
            continue;
          }
          $result = $this->getResultForPath($message->getPath());
          $result->addMessage($message);
        }
      } catch (Exception $ex) {
        if (!is_string($linter_name)) {
          $linter_name = get_class($linter);
        }
        $exceptions[$linter_name] = $ex;
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
      throw new PhutilAggregateException('Some linters failed:', $exceptions);
    }

    return $this->results;
  }

  public function getResults() {
    return $this->results;
  }

  abstract protected function buildLinters();

  private function isRelevantMessage($message) {
    // When a user runs "arc lint", we default to raising only warnings on
    // lines they have changed (errors are still raised anywhere in the
    // file). The list of $changed lines may be null, to indicate that the
    // path is a directory or a binary file so we should not exclude
    // warnings.

    $changed = $this->getPathChangedLines($message->getPath());

    if ($changed === null || $message->isError() || !$message->getLine()) {
      return true;
    }

    $last_line = $message->getLine();
    if ($message->getOriginalText()) {
      $last_line += substr_count($message->getOriginalText(), "\n");
    }

    for ($l = $message->getLine(); $l <= $last_line; $l++) {
      if (!empty($changed[$l])) {
        return true;
      }
    }

    return false;
  }

  private function getResultForPath($path) {
    if (empty($this->results[$path])) {
      $result = new ArcanistLintResult();
      $result->setPath($path);
      $this->results[$path] = $result;
    }
    return $this->results[$path];
  }

  public function getLineAndCharFromOffset($path, $offset) {
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

  public function getPostponedLinters() {
    return $this->postponedLinters;
  }

  public function setPostponedLinters(array $linters) {
    $this->postponedLinters = $linters;
    return $this;
  }

  protected function getPEP8WithTextOptions() {
    // E101 is subset of TXT2 (Tab Literal).
    // E501 is same as TXT3 (Line Too Long).
    // W291 is same as TXT6 (Trailing Whitespace).
    // W292 is same as TXT4 (File Does Not End in Newline).
    // W293 is same as TXT6 (Trailing Whitespace).
    return '--ignore=E101,E501,W291,W292,W293';
  }

}
