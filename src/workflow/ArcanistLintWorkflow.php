<?php

/**
 * Runs lint rules on changes.
 *
 * @group workflow
 */
final class ArcanistLintWorkflow extends ArcanistBaseWorkflow {

  const RESULT_OKAY       = 0;
  const RESULT_WARNINGS   = 1;
  const RESULT_ERRORS     = 2;
  const RESULT_SKIP       = 3;
  const RESULT_POSTPONED  = 4;

  const DEFAULT_SEVERITY = ArcanistLintSeverity::SEVERITY_ADVICE;

  private $unresolvedMessages;
  private $shouldLintAll;
  private $shouldAmendChanges = false;
  private $shouldAmendWithoutPrompt = false;
  private $shouldAmendAutofixesWithoutPrompt = false;
  private $engine;
  private $postponedLinters;

  public function getWorkflowName() {
    return 'lint';
  }

  public function setShouldAmendChanges($should_amend) {
    $this->shouldAmendChanges = $should_amend;
    return $this;
  }

  public function setShouldAmendWithoutPrompt($should_amend) {
    $this->shouldAmendWithoutPrompt = $should_amend;
    return $this;
  }

  public function setShouldAmendAutofixesWithoutPrompt($should_amend) {
    $this->shouldAmendAutofixesWithoutPrompt = $should_amend;
    return $this;
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **lint** [__options__] [__paths__]
      **lint** [__options__] --rev [__rev__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, svn, hg
          Run static analysis on changes to check for mistakes. If no files
          are specified, lint will be run on all files which have been modified.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'lintall' => array(
        'help' =>
          "Show all lint warnings, not just those on changed lines."
      ),
      'rev' => array(
        'param' => 'revision',
        'help' => "Lint changes since a specific revision.",
        'supports' => array(
          'git',
          'hg',
        ),
        'nosupport' => array(
          'svn' => "Lint does not currently support --rev in SVN.",
        ),
      ),
      'output' => array(
        'param' => 'format',
        'help' =>
          "With 'summary', show lint warnings in a more compact format. ".
          "With 'json', show lint warnings in machine-readable JSON format. ".
          "With 'compiler', show lint warnings in suitable for your editor."
      ),
      'only-new' => array(
        'param' => 'bool',
        'supports' => array('git', 'hg'), // TODO: svn
        'help' => 'Display only messages not present in the original code.',
      ),
      'engine' => array(
        'param' => 'classname',
        'help' =>
          "Override configured lint engine for this project."
      ),
      'apply-patches' => array(
        'help' =>
          'Apply patches suggested by lint to the working copy without '.
          'prompting.',
        'conflicts' => array(
          'never-apply-patches' => true,
        ),
      ),
      'never-apply-patches' => array(
        'help' => 'Never apply patches suggested by lint.',
        'conflicts' => array(
          'apply-patches' => true,
        ),
      ),
      'amend-all' => array(
        'help' =>
        'When linting git repositories, amend HEAD with all patches '.
        'suggested by lint without prompting.',
      ),
      'amend-autofixes' => array(
        'help' =>
          'When linting git repositories, amend HEAD with autofix '.
          'patches suggested by lint without prompting.',
      ),
      'severity' => array(
        'param' => 'string',
        'help' =>
          "Set minimum message severity. One of: '".
          implode(
            "', '",
            array_keys(ArcanistLintSeverity::getLintSeverities())).
          "'. Defaults to '".self::DEFAULT_SEVERITY."'.",
      ),
      'cache' => array(
        'param' => 'bool',
        'help' => "0 to disable cache, 1 to enable (default).",
      ),
      '*' => 'paths',
    );
  }

  public function requiresAuthentication() {
    return (bool)$this->getArgument('only-new');
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  private function getCacheKey() {
    return implode("\n", array(
      get_class($this->engine),
      $this->getArgument('severity', self::DEFAULT_SEVERITY),
      $this->shouldLintAll,
    ));
  }

  public function run() {
    $working_copy = $this->getWorkingCopy();

    $engine = $this->getArgument('engine');
    if (!$engine) {
      $engine = $working_copy->getConfigFromAnySource('lint.engine');
      if (!$engine) {
        throw new ArcanistNoEngineException(
          "No lint engine configured for this project. Edit .arcconfig to ".
          "specify a lint engine.");
      }
    }

    $rev = $this->getArgument('rev');
    $paths = $this->getArgument('paths');
    $use_cache = $this->getArgument('cache', true);

    if ($rev && $paths) {
      throw new ArcanistUsageException("Specify either --rev or paths.");
    }

    $this->shouldLintAll = $this->getArgument('lintall');
    if ($paths) {
      // NOTE: When the user specifies paths, we imply --lintall and show all
      // warnings for the paths in question. This is easier to deal with for
      // us and less confusing for users.
      $this->shouldLintAll = true;
    }

    $paths = $this->selectPathsForWorkflow($paths, $rev);

    if (!class_exists($engine) ||
        !is_subclass_of($engine, 'ArcanistLintEngine')) {
      throw new ArcanistUsageException(
        "Configured lint engine '{$engine}' is not a subclass of ".
        "'ArcanistLintEngine'.");
    }

    $engine = newv($engine, array());
    $this->engine = $engine;
    $engine->setWorkingCopy($working_copy);
    $engine->setMinimumSeverity(
      $this->getArgument('severity', self::DEFAULT_SEVERITY));

    if ($use_cache) {
      $engine->setRepositoryVersion($this->getRepositoryVersion());
      $cache = $this->readScratchJSONFile('lint-cache.json');
      $cache = idx($cache, $this->getCacheKey(), array());
      $cache = array_intersect_key($cache, array_flip($paths));
      $cached = array();
      foreach ($cache as $path => $messages) {
        $abs_path = $engine->getFilePathOnDisk($path);
        if (!Filesystem::pathExists($abs_path)) {
          continue;
        }
        $messages = idx($messages, md5_file($abs_path));
        if ($messages !== null) {
          $cached[$path] = $messages;
        }
      }
      $engine->setCachedResults($cached);
    }

    // Propagate information about which lines changed to the lint engine.
    // This is used so that the lint engine can drop warning messages
    // concerning lines that weren't in the change.
    $engine->setPaths($paths);
    if (!$this->shouldLintAll) {
      foreach ($paths as $path) {
        // Note that getChangedLines() returns null to indicate that a file
        // is binary or a directory (i.e., changed lines are not relevant).
        $engine->setPathChangedLines(
          $path,
          $this->getChangedLines($path, 'new'));
      }
    }

    // Enable possible async linting only for 'arc diff' not 'arc lint'
    if ($this->getParentWorkflow()) {
      $engine->setEnableAsyncLint(true);
    } else {
      $engine->setEnableAsyncLint(false);
    }

    if ($this->getArgument('only-new')) {
      $conduit = $this->getConduit();
      $api = $this->getRepositoryAPI();
      if ($rev) {
        $api->setBaseCommit($rev);
      }
      $svn_root = id(new PhutilURI($api->getSourceControlPath()))->getPath();

      $all_paths = array();
      foreach ($paths as $path) {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        $full_paths = array($path);

        $change = $this->getChange($path);
        $type = $change->getType();
        if (ArcanistDiffChangeType::isOldLocationChangeType($type)) {
          $full_paths = $change->getAwayPaths();
        } else if (ArcanistDiffChangeType::isNewLocationChangeType($type)) {
          continue;
        } else if (ArcanistDiffChangeType::isDeleteChangeType($type)) {
          continue;
        }

        foreach ($full_paths as $full_path) {
          $all_paths[$svn_root.'/'.$full_path] = $path;
        }
      }

      $lint_future = $conduit->callMethod('diffusion.getlintmessages', array(
        'arcanistProject' => $this->getWorkingCopy()->getProjectID(),
        'branch' => '', // TODO: Tracking branch.
        'commit' => $api->getBaseCommit(),
        'files' => array_keys($all_paths),
      ));
    }

    $failed = null;
    try {
      $engine->run();
    } catch (Exception $ex) {
      $failed = $ex;
    }

    $results = $engine->getResults();

    if ($this->getArgument('only-new')) {
      $total = 0;
      foreach ($results as $result) {
        $total += count($result->getMessages());
      }

      // Don't wait for response with default value of --only-new.
      $timeout = null;
      if ($this->getArgument('only-new') === null || !$total) {
        $timeout = 0;
      }

      $raw_messages = $this->resolveCall($lint_future, $timeout);
      if ($raw_messages && $total) {
        $old_messages = array();
        $line_maps = array();
        foreach ($raw_messages as $message) {
          $path = $all_paths[$message['path']];
          $line = $message['line'];
          $code = $message['code'];

          if (!isset($line_maps[$path])) {
            $line_maps[$path] = $this->getChange($path)->buildLineMap();
          }

          $new_lines = idx($line_maps[$path], $line);
          if (!$new_lines) { // Unmodified lines after last hunk.
            $last_old = ($line_maps[$path] ? last_key($line_maps[$path]) : 0);
            $news = array_filter($line_maps[$path]);
            $last_new = ($news ? last(end($news)) : 0);
            $new_lines = array($line + $last_new - $last_old);
          }

          $error = array($code => array(true));
          foreach ($new_lines as $new) {
            if (isset($old_messages[$path][$new])) {
              $old_messages[$path][$new][$code][] = true;
              break;
            }
            $old_messages[$path][$new] = &$error;
          }
          unset($error);
        }

        foreach ($results as $result) {
          foreach ($result->getMessages() as $message) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $message->getPath());
            $line = $message->getLine();
            $code = $message->getCode();
            if (!empty($old_messages[$path][$line][$code])) {
              $message->setObsolete(true);
              array_pop($old_messages[$path][$line][$code]);
            }
          }
          $result->sortAndFilterMessages();
        }
      }
    }

    // It'd be nice to just return a single result from the run method above
    // which contains both the lint messages and the postponed linters.
    // However, to maintain compatibility with existing lint subclasses, use
    // a separate method call to grab the postponed linters.
    $this->postponedLinters = $engine->getPostponedLinters();

    if ($this->getArgument('never-apply-patches')) {
      $apply_patches = false;
    } else {
      $apply_patches = true;
    }

    if ($this->getArgument('apply-patches')) {
      $prompt_patches = false;
    } else {
      $prompt_patches = true;
    }

    if ($this->getArgument('amend-all')) {
      $this->shouldAmendChanges = true;
      $this->shouldAmendWithoutPrompt = true;
    }

    if ($this->getArgument('amend-autofixes')) {
      $prompt_autofix_patches = false;
      $this->shouldAmendChanges = true;
      $this->shouldAmendAutofixesWithoutPrompt = true;
    } else {
      $prompt_autofix_patches = true;
    }

    $wrote_to_disk = false;

    switch ($this->getArgument('output')) {
      case 'json':
        $renderer = new ArcanistLintJSONRenderer();
        $prompt_patches = false;
        $apply_patches = $this->getArgument('apply-patches');
        break;
      case 'summary':
        $renderer = new ArcanistLintSummaryRenderer();
        break;
      case 'compiler':
        $renderer = new ArcanistLintLikeCompilerRenderer();
        $prompt_patches = false;
        $apply_patches = $this->getArgument('apply-patches');
        break;
      default:
        $renderer = new ArcanistLintConsoleRenderer();
        $renderer->setShowAutofixPatches($prompt_autofix_patches);
        break;
    }

    $all_autofix = true;

    $console = PhutilConsole::getConsole();

    foreach ($results as $result) {
      $result_all_autofix = $result->isAllAutofix();

      if (!$result->getMessages() && !$result_all_autofix) {
        continue;
      }

      if (!$result_all_autofix) {
        $all_autofix = false;
      }

      $lint_result = $renderer->renderLintResult($result);
      if ($lint_result) {
        $console->writeOut('%s', $lint_result);
      }

      if ($apply_patches && $result->isPatchable()) {
        $patcher = ArcanistLintPatcher::newFromArcanistLintResult($result);

        if ($prompt_patches &&
            !($result_all_autofix && !$prompt_autofix_patches)) {
          $old_file = $result->getFilePathOnDisk();
          if (!Filesystem::pathExists($old_file)) {
            $old_file = '/dev/null';
          }
          $new_file = new TempFile();
          $new = $patcher->getModifiedFileContent();
          Filesystem::writeFile($new_file, $new);

          // TODO: Improve the behavior here, make it more like
          // difference_render().
          list(, $stdout, $stderr) =
            exec_manual("diff -u %s %s", $old_file, $new_file);
          $console->writeOut('%s', $stdout);
          $console->writeErr('%s', $stderr);

          $prompt = phutil_console_format(
            "Apply this patch to __%s__?",
            $result->getPath());
          if (!$console->confirm($prompt, $default_no = false)) {
            continue;
          }
        }

        $patcher->writePatchToDisk();
        $wrote_to_disk = true;
      }
    }

    $repository_api = $this->getRepositoryAPI();
    if ($wrote_to_disk &&
        ($repository_api instanceof ArcanistGitAPI) &&
        $this->shouldAmendChanges) {

      if ($this->shouldAmendWithoutPrompt ||
          ($this->shouldAmendAutofixesWithoutPrompt && $all_autofix)) {
        $console->writeOut(
          "<bg:yellow>** LINT NOTICE **</bg> Automatically amending HEAD ".
          "with lint patches.\n");
        $amend = true;
      } else {
        $amend = $console->confirm("Amend HEAD with lint patches?");
      }

      if ($amend) {
        execx(
          '(cd %s; git commit -a --amend -C HEAD)',
          $repository_api->getPath());
      } else {
        throw new ArcanistUsageException(
          "Sort out the lint changes that were applied to the working ".
          "copy and relint.");
      }
    }

    if ($this->getArgument('output') == 'json') {
      // NOTE: Required by save_lint.php in Phabricator.
      return 0;
    }

    if ($failed) {
      throw $failed;
    }

    $unresolved = array();
    $has_warnings = false;
    $has_errors = false;

    foreach ($results as $result) {
      foreach ($result->getMessages() as $message) {
        if (!$message->isPatchApplied()) {
          if ($message->isError()) {
            $has_errors = true;
          } else if ($message->isWarning()) {
            $has_warnings = true;
          }
          $unresolved[] = $message;
        }
      }
    }
    $this->unresolvedMessages = $unresolved;

    $cache = $this->readScratchJSONFile('lint-cache.json');
    $cached = idx($cache, $this->getCacheKey(), array());
    if ($cached || $use_cache) {
      $stopped = $engine->getStoppedPaths();
      foreach ($results as $result) {
        $path = $result->getPath();
        if (!$use_cache) {
          unset($cached[$path]);
          continue;
        }
        $abs_path = $engine->getFilePathOnDisk($path);
        if (!Filesystem::pathExists($abs_path)) {
          continue;
        }
        $hash = md5_file($abs_path);
        $version = $result->getCacheVersion();
        $cached_path = array();
        if (isset($stopped[$path])) {
          $cached_path['stopped'] = $stopped[$path];
        }
        $cached_path['repository_version'] = $this->getRepositoryVersion();
        foreach ($result->getMessages() as $message) {
          $granularity = $message->getGranularity();
          if ($granularity == ArcanistLinter::GRANULARITY_GLOBAL) {
            continue;
          }
          if (!$message->isPatchApplied()) {
            $cached_path[] = $message->toDictionary();
          }
        }
        $cached[$path] = array($hash => array($version => $cached_path));
      }
      $cache[$this->getCacheKey()] = $cached;
      // TODO: Garbage collection.
      $this->writeScratchJSONFile('lint-cache.json', $cache);
    }

    // Take the most severe lint message severity and use that
    // as the result code.
    if ($has_errors) {
      $result_code = self::RESULT_ERRORS;
    } else if ($has_warnings) {
      $result_code = self::RESULT_WARNINGS;
    } else if (!empty($this->postponedLinters)) {
      $result_code = self::RESULT_POSTPONED;
    } else {
      $result_code = self::RESULT_OKAY;
    }

    if (!$this->getParentWorkflow()) {
      if ($result_code == self::RESULT_OKAY) {
        $console->writeOut('%s', $renderer->renderOkayResult());
      }
    }

    return $result_code;
  }

  public function getUnresolvedMessages() {
    return $this->unresolvedMessages;
  }

  public function getPostponedLinters() {
    return $this->postponedLinters;
  }

}
