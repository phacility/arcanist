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
        'help' => pht('Select an output format.'),
      ),
      'outfile' => array(
        'param' => 'path',
        'help' => pht(
          'Output the linter results to a file. Defaults to stdout.'),
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
        'help' => pht(
          'Lint all tracked files in the working copy. Ignored files and '.
          'untracked files will not be linted.'),
        'conflicts' => array(
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
      '*' => 'paths',
    );
  }

  public function requiresAuthentication() {
    return false;
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function run() {
    $console = PhutilConsole::getConsole();
    $working_copy = $this->getWorkingCopyIdentity();
    $configuration_manager = $this->getConfigurationManager();

    $engine = $this->newLintEngine($this->getArgument('engine'));

    $rev = $this->getArgument('rev');
    $paths = $this->getArgument('paths');
    $everything = $this->getArgument('everything');
    if ($everything && $paths) {
      throw new ArcanistUsageException(
        pht(
          'You can not specify paths with %s. The %s flag lints every '.
          'tracked file in the working copy.',
          '--everything',
          '--everything'));
    }

    if ($rev !== null) {
      $this->parseBaseCommitArgument(array($rev));
    }

    // Sometimes, we hide low-severity messages which occur on lines which
    // were not changed. This is the default behavior when you run "arc lint"
    // with no arguments: if you touched a file, but there was already some
    // minor warning about whitespace or spelling elsewhere in the file, you
    // don't need to correct it.

    // In other modes, notably "arc lint <file>", this is not the defualt
    // behavior. If you ask us to lint a specific file, we show you all the
    // lint messages in the file.

    // You can change this behavior with various flags, including "--lintall",
    // "--rev", and "--everything".
    if ($this->getArgument('lintall')) {
      $lint_all = true;
    } else if ($rev !== null) {
      $lint_all = false;
    } else if ($paths || $everything) {
      $lint_all = true;
    } else {
      $lint_all = false;
    }

    if ($everything) {
      $paths = iterator_to_array($this->getRepositoryAPI()->getAllFiles());
    } else {
      $paths = $this->selectPathsForWorkflow($paths, $rev);
    }

    $this->engine = $engine;

    $engine->setMinimumSeverity(
      $this->getArgument('severity', self::DEFAULT_SEVERITY));

    // Propagate information about which lines changed to the lint engine.
    // This is used so that the lint engine can drop warning messages
    // concerning lines that weren't in the change.
    $engine->setPaths($paths);
    if (!$lint_all) {
      foreach ($paths as $path) {
        // Note that getChangedLines() returns null to indicate that a file
        // is binary or a directory (i.e., changed lines are not relevant).
        $engine->setPathChangedLines(
          $path,
          $this->getChangedLines($path, 'new'));
      }
    }

    $failed = null;
    try {
      $engine->run();
    } catch (Exception $ex) {
      $failed = $ex;
    }

    $results = $engine->getResults();

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
      $this->shouldAmendChanges = true;
      $this->shouldAmendAutofixesWithoutPrompt = true;
    }

    $repository_api = $this->getRepositoryAPI();
    if ($this->shouldAmendChanges) {
      $this->shouldAmendChanges = $repository_api->supportsAmend() &&
        !$this->isHistoryImmutable();
    }

    $wrote_to_disk = false;

    $default_renderer = ArcanistConsoleLintRenderer::RENDERERKEY;
    $renderer_key = $this->getArgument('output', $default_renderer);

    $renderers = ArcanistLintRenderer::getAllRenderers();
    if (!isset($renderers[$renderer_key])) {
      throw new Exception(
        pht(
          'Lint renderer "%s" is unknown. Supported renderers are: %s.',
          $renderer_key,
          implode(', ', array_keys($renderers))));
    }
    $renderer = $renderers[$renderer_key];

    $all_autofix = true;

    $out_path = $this->getArgument('outfile');
    if ($out_path !== null) {
      $tmp = new TempFile();
      $renderer->setOutputPath((string)$tmp);
    } else {
      $tmp = null;
    }

    if ($failed) {
      $renderer->handleException($failed);
    }

    $renderer->willRenderResults();

    $should_patch = ($apply_patches && $renderer->supportsPatching());
    foreach ($results as $result) {
      if (!$result->getMessages()) {
        continue;
      }

      $result_all_autofix = $result->isAllAutofix();
      if (!$result_all_autofix) {
        $all_autofix = false;
      }

      $renderer->renderLintResult($result);

      if ($should_patch && $result->isPatchable()) {
        $patcher = ArcanistLintPatcher::newFromArcanistLintResult($result);

        $apply = true;
        if ($prompt_patches && !$result_all_autofix) {
          $old_file = $result->getFilePathOnDisk();
          if (!Filesystem::pathExists($old_file)) {
            $old_file = null;
          }

          $new_file = new TempFile();
          $new = $patcher->getModifiedFileContent();
          Filesystem::writeFile($new_file, $new);

          $apply = $renderer->promptForPatch($result, $old_file, $new_file);
        }

        if ($apply) {
          $patcher->writePatchToDisk();
          $wrote_to_disk = true;
        }
      }
    }

    $renderer->didRenderResults();

    if ($tmp) {
      Filesystem::rename($tmp, $out_path);
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
        $amend = phutil_console_confirm(pht('Amend HEAD with lint patches?'),
                                        false);
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

    // Take the most severe lint message severity and use that
    // as the result code.
    if ($has_errors) {
      $result_code = self::RESULT_ERRORS;
    } else if ($has_warnings) {
      $result_code = self::RESULT_WARNINGS;
    } else {
      $result_code = self::RESULT_OKAY;
    }

    $renderer->renderResultCode($result_code);

    return $result_code;
  }

  public function getUnresolvedMessages() {
    return $this->unresolvedMessages;
  }

}
