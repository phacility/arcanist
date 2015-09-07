<?php

/**
 * Runs lint rules on changes.
 */
final class ArcanistLintWorkflow extends ArcanistWorkflow {

  const RESULT_OKAY       = 0;
  const RESULT_WARNINGS   = 1;
  const RESULT_ERRORS     = 2;
  const RESULT_SKIP       = 3;

  const DEFAULT_SEVERITY = ArcanistLintSeverity::SEVERITY_ADVICE;

  private $unresolvedMessages;
  private $shouldLintAll;
  private $shouldAmendChanges = false;
  private $shouldAmendWithoutPrompt = false;
  private $shouldAmendAutofixesWithoutPrompt = false;
  private $engine;

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
        'help' => pht(
          'Show all lint warnings, not just those on changed lines. When '.
          'paths are specified, this is the default behavior.'),
        'conflicts' => array(
          'only-changed' => true,
        ),
      ),
      'only-changed' => array(
        'help' => pht(
          'Show lint warnings just on changed lines. When no paths are '.
          'specified, this is the default. This differs from only-new '.
          'in cases where line modifications introduce lint on other '.
          'unmodified lines.'),
        'conflicts' => array(
          'lintall' => true,
        ),
      ),
      'rev' => array(
        'param' => 'revision',
        'help' => pht('Lint changes since a specific revision.'),
        'supports' => array(
          'git',
          'hg',
        ),
        'nosupport' => array(
          'svn' => pht('Lint does not currently support %s in SVN.', '--rev'),
        ),
      ),
      'output' => array(
        'param' => 'format',
        'help' => pht(
          "With 'summary', show lint warnings in a more compact format. ".
          "With 'json', show lint warnings in machine-readable JSON format. ".
          "With 'none', show no lint warnings. ".
          "With 'compiler', show lint warnings in suitable for your editor. ".
          "With 'xml', show lint warnings in the Checkstyle XML format."),
      ),
      'outfile' => array(
        'param' => 'path',
        'help' => pht(
          'Output the linter results to a file. Defaults to stdout.'),
      ),
      'only-new' => array(
        'param' => 'bool',
        'supports' => array('git', 'hg'), // TODO: svn
        'help' => pht(
          'Display only messages not present in the original code.'),
      ),
      'engine' => array(
        'param' => 'classname',
        'help' => pht('Override configured lint engine for this project.'),
      ),
      'apply-patches' => array(
        'help' => pht(
          'Apply patches suggested by lint to the working copy without '.
          'prompting.'),
        'conflicts' => array(
          'never-apply-patches' => true,
        ),
      ),
      'never-apply-patches' => array(
        'help' => pht('Never apply patches suggested by lint.'),
        'conflicts' => array(
          'apply-patches' => true,
        ),
      ),
      'amend-all' => array(
        'help' => pht(
          'When linting git repositories, amend HEAD with all patches '.
          'suggested by lint without prompting.'),
      ),
      'amend-autofixes' => array(
        'help' => pht(
          'When linting git repositories, amend HEAD with autofix '.
          'patches suggested by lint without prompting.'),
      ),
      'everything' => array(
        'help' => pht('Lint all files in the project.'),
        'conflicts' => array(
          'cache' => pht('%s lints all files', '--everything'),
          'rev' => pht('%s lints all files', '--everything'),
        ),
      ),
      'severity' => array(
        'param' => 'string',
        'help' => pht(
          "Set minimum message severity. One of: %s. Defaults to '%s'.",
          sprintf(
            "'%s'",
            implode(
              "', '",
              array_keys(ArcanistLintSeverity::getLintSeverities()))),
          self::DEFAULT_SEVERITY),
      ),
      'cache' => array(
        'param' => 'bool',
        'help' => pht(
          "%d to disable cache, %d to enable. The default value is determined ".
          "by '%s' in configuration, which defaults to off. See notes in '%s'.",
          0,
          1,
          'arc.lint.cache',
          'arc.lint.cache'),
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
    $console = PhutilConsole::getConsole();
    $working_copy = $this->getWorkingCopy();
    $configuration_manager = $this->getConfigurationManager();

    $engine = $this->newLintEngine($this->getArgument('engine'));

    $rev = $this->getArgument('rev');
    $paths = $this->getArgument('paths');
    $use_cache = $this->getArgument('cache', null);
    $everything = $this->getArgument('everything');
    if ($everything && $paths) {
      throw new ArcanistUsageException(
        pht(
          'You can not specify paths with %s. The %s flag lints every file.',
          '--everything',
          '--everything'));
    }
    if ($use_cache === null) {
      $use_cache = (bool)$configuration_manager->getConfigFromAnySource(
        'arc.lint.cache',
        false);
    }

    if ($rev && $paths) {
      throw new ArcanistUsageException(
        pht('Specify either %s or paths.', '--rev'));
    }


    // NOTE: When the user specifies paths, we imply --lintall and show all
    // warnings for the paths in question. This is easier to deal with for
    // us and less confusing for users.
    $this->shouldLintAll = $paths ? true : false;
    if ($this->getArgument('lintall')) {
      $this->shouldLintAll = true;
    } else if ($this->getArgument('only-changed')) {
      $this->shouldLintAll = false;
    }

    if ($everything) {
      $paths = iterator_to_array($this->getRepositoryApi()->getAllFiles());
      $this->shouldLintAll = true;
    } else {
      $paths = $this->selectPathsForWorkflow($paths, $rev);
    }

    $this->engine = $engine;

    $engine->setMinimumSeverity(
      $this->getArgument('severity', self::DEFAULT_SEVERITY));

    $file_hashes = array();
    if ($use_cache) {
      $engine->setRepositoryVersion($this->getRepositoryVersion());
      $cache = $this->readScratchJSONFile('lint-cache.json');
      $cache = idx($cache, $this->getCacheKey(), array());
      $cached = array();

      foreach ($paths as $path) {
        $abs_path = $engine->getFilePathOnDisk($path);
        if (!Filesystem::pathExists($abs_path)) {
          continue;
        }
        $file_hashes[$abs_path] = md5_file($abs_path);

        if (!isset($cache[$path])) {
          continue;
        }
        $messages = idx($cache[$path], $file_hashes[$abs_path]);
        if ($messages !== null) {
          $cached[$path] = $messages;
        }
      }

      if ($cached) {
        $console->writeErr(
          "%s\n",
          pht(
            "Using lint cache, use '%s' to disable it.",
            '--cache 0'));
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
        'repositoryPHID' => idx($this->loadProjectRepository(), 'phid'),
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

    $repository_api = $this->getRepositoryAPI();
    if ($this->shouldAmendChanges) {
      $this->shouldAmendChanges = $repository_api->supportsAmend() &&
        !$this->isHistoryImmutable();
    }

    $wrote_to_disk = false;

    switch ($this->getArgument('output')) {
      case 'json':
        $renderer = new ArcanistJSONLintRenderer();
        $prompt_patches = false;
        $apply_patches = $this->getArgument('apply-patches');
        break;
      case 'summary':
        $renderer = new ArcanistSummaryLintRenderer();
        break;
      case 'none':
        $prompt_patches = false;
        $apply_patches = $this->getArgument('apply-patches');
        $renderer = new ArcanistNoneLintRenderer();
        break;
      case 'compiler':
        $renderer = new ArcanistCompilerLintRenderer();
        $prompt_patches = false;
        $apply_patches = $this->getArgument('apply-patches');
        break;
      case 'xml':
        $renderer = new ArcanistCheckstyleXMLLintRenderer();
        $prompt_patches = false;
        $apply_patches = $this->getArgument('apply-patches');
        break;
      default:
        $renderer = new ArcanistConsoleLintRenderer();
        $renderer->setShowAutofixPatches($prompt_autofix_patches);
        break;
    }

    $all_autofix = true;
    $tmp = null;

    if ($this->getArgument('outfile') !== null) {
      $tmp = id(new TempFile())
        ->setPreserveFile(true);
    }

    $preamble = $renderer->renderPreamble();
    if ($tmp) {
      Filesystem::appendFile($tmp, $preamble);
    } else {
      $console->writeOut('%s', $preamble);
    }

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
        if ($tmp) {
          Filesystem::appendFile($tmp, $lint_result);
        } else {
          $console->writeOut('%s', $lint_result);
        }
      }

      if ($apply_patches && $result->isPatchable()) {
        $patcher = ArcanistLintPatcher::newFromArcanistLintResult($result);
        $old_file = $result->getFilePathOnDisk();

        if ($prompt_patches &&
            !($result_all_autofix && !$prompt_autofix_patches)) {
          if (!Filesystem::pathExists($old_file)) {
            $old_file = '/dev/null';
          }
          $new_file = new TempFile();
          $new = $patcher->getModifiedFileContent();
          Filesystem::writeFile($new_file, $new);

          // TODO: Improve the behavior here, make it more like
          // difference_render().
          list(, $stdout, $stderr) =
            exec_manual('diff -u %s %s', $old_file, $new_file);
          $console->writeOut('%s', $stdout);
          $console->writeErr('%s', $stderr);

          $prompt = pht(
            'Apply this patch to %s?',
            phutil_console_format('__%s__', $result->getPath()));
          if (!$console->confirm($prompt, $default = true)) {
            continue;
          }
        }

        $patcher->writePatchToDisk();
        $wrote_to_disk = true;
        $file_hashes[$old_file] = md5_file($old_file);
      }
    }

    $postamble = $renderer->renderPostamble();
    if ($tmp) {
      Filesystem::appendFile($tmp, $postamble);
      Filesystem::rename($tmp, $this->getArgument('outfile'));
    } else {
      $console->writeOut('%s', $postamble);
    }

    if ($wrote_to_disk && $this->shouldAmendChanges) {
      if ($this->shouldAmendWithoutPrompt ||
          ($this->shouldAmendAutofixesWithoutPrompt && $all_autofix)) {
        $console->writeOut(
          "<bg:yellow>** %s **</bg> %s\n",
          pht('LINT NOTICE'),
          pht('Automatically amending HEAD with lint patches.'));
        $amend = true;
      } else {
        $amend = $console->confirm(pht('Amend HEAD with lint patches?'));
      }

      if ($amend) {
        if ($repository_api instanceof ArcanistGitAPI) {
          // Add the changes to the index before amending
          $repository_api->execxLocal('add -u');
        }

        $repository_api->amendCommit();
      } else {
        throw new ArcanistUsageException(
          pht(
            'Sort out the lint changes that were applied to the working '.
            'copy and relint.'));
      }
    }

    if ($this->getArgument('output') == 'json') {
      // NOTE: Required by save_lint.php in Phabricator.
      return 0;
    }

    if ($failed) {
      if ($failed instanceof ArcanistNoEffectException) {
        if ($renderer instanceof ArcanistNoneLintRenderer) {
          return 0;
        }
      }
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
        $hash = idx($file_hashes, $abs_path);
        if (!$hash) {
          $hash = md5_file($abs_path);
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

}
