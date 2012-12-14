<?php

/**
 * Sends changes from your working copy to Differential for code review.
 *
 * @task lintunit   Lint and Unit Tests
 * @task message    Commit and Update Messages
 * @task diffspec   Diff Specification
 * @task diffprop   Diff Properties
 *
 * @group workflow
 */
final class ArcanistDiffWorkflow extends ArcanistBaseWorkflow {

  private $console;
  private $hasWarnedExternals = false;
  private $unresolvedLint;
  private $excuses = array('lint' => null, 'unit' => null);
  private $testResults;
  private $diffID;
  private $revisionID;
  private $postponedLinters;
  private $haveUncommittedChanges = false;
  private $diffPropertyFutures = array();
  private $commitMessageFromRevision;

  public function getWorkflowName() {
    return 'diff';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **diff** [__paths__] (svn)
      **diff** [__commit__] (git, hg)
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, svn, hg
          Generate a Differential diff or revision from local changes.

          Under git, you can specify a commit (like __HEAD^^^__ or __master__)
          and Differential will generate a diff against the merge base of that
          commit and HEAD.

          Under svn, you can choose to include only some of the modified files
          in the working copy in the diff by specifying their paths. If you
          omit paths, all changes are included in the diff.
EOTEXT
      );
  }

  public function requiresWorkingCopy() {
    return !$this->isRawDiffSource();
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    if (!$this->isRawDiffSource()) {
      return true;
    }

    if ($this->getArgument('use-commit-message')) {
      return true;
    }

    return false;
  }

  public function getDiffID() {
    return $this->diffID;
  }

  public function getArguments() {
    $arguments = array(
      'message' => array(
        'short'       => 'm',
        'param'       => 'message',
        'help' =>
          "When updating a revision, use the specified message instead of ".
          "prompting.",
      ),
      'message-file' => array(
        'short' => 'F',
        'param' => 'file',
        'paramtype' => 'file',
        'help' => 'When creating a revision, read revision information '.
                  'from this file.',
      ),
      'use-commit-message' => array(
        'supports' => array(
          'git',
          // TODO: Support mercurial.
        ),
        'short' => 'C',
        'param' => 'commit',
        'help' => 'Read revision information from a specific commit.',
        'conflicts' => array(
          'only'    => null,
          'preview' => null,
          'update'  => null,
        ),
      ),
      'edit' => array(
        'supports'    => array(
          'git',
        ),
        'nosupport'   => array(
          'svn' => 'Edit revisions via the web interface when using SVN.',
        ),
        'help' =>
          "When updating a revision under git, edit revision information ".
          "before updating.",
      ),
      'raw' => array(
        'help' =>
          "Read diff from stdin, not from the working copy. This disables ".
          "many Arcanist/Phabricator features which depend on having access ".
          "to the working copy.",
        'conflicts' => array(
          'less-context'        => null,
          'apply-patches'       => '--raw disables lint.',
          'never-apply-patches' => '--raw disables lint.',
          'advice'              => '--raw disables lint.',
          'lintall'             => '--raw disables lint.',

          'create'              => '--raw and --create both need stdin. '.
                                   'Use --raw-command.',
          'edit'                => '--raw and --edit both need stdin. '.
                                   'Use --raw-command.',
          'raw-command'         => null,
        ),
      ),
      'raw-command' => array(
        'param' => 'command',
        'help' =>
          "Generate diff by executing a specified command, not from the ".
          "working copy. This disables many Arcanist/Phabricator features ".
          "which depend on having access to the working copy.",
        'conflicts' => array(
          'less-context'        => null,
          'apply-patches'       => '--raw-command disables lint.',
          'never-apply-patches' => '--raw-command disables lint.',
          'advice'              => '--raw-command disables lint.',
          'lintall'             => '--raw-command disables lint.',
        ),
      ),
      'create' => array(
        'help' => "Always create a new revision.",
        'conflicts' => array(
          'edit'    => '--create can not be used with --edit.',
          'only'    => '--create can not be used with --only.',
          'preview' => '--create can not be used with --preview.',
          'update'  => '--create can not be used with --update.',
        ),
      ),
      'update' => array(
        'param' => 'revision_id',
        'help'  => "Always update a specific revision.",
      ),
      'nounit' => array(
        'help' =>
          "Do not run unit tests.",
      ),
      'nolint' => array(
        'help' =>
          "Do not run lint.",
        'conflicts' => array(
          'lintall'   => '--nolint suppresses lint.',
          'advice'    => '--nolint suppresses lint.',
          'apply-patches' => '--nolint suppresses lint.',
          'never-apply-patches' => '--nolint suppresses lint.',
        ),
      ),
      'only' => array(
        'help' =>
          "Only generate a diff, without running lint, unit tests, or other ".
          "auxiliary steps. See also --preview.",
        'conflicts' => array(
          'preview'   => null,
          'message'   => '--only does not affect revisions.',
          'edit'      => '--only does not affect revisions.',
          'lintall'   => '--only suppresses lint.',
          'advice'    => '--only suppresses lint.',
          'apply-patches' => '--only suppresses lint.',
          'never-apply-patches' => '--only suppresses lint.',
        ),
      ),
      'preview' => array(
        'help' =>
          "Instead of creating or updating a revision, only create a diff, ".
          "which you may later attach to a revision. This still runs lint ".
          "unit tests. See also --only.",
        'conflicts' => array(
          'only'      => null,
          'edit'      => '--preview does affect revisions.',
          'message'   => '--preview does not update any revision.',
        ),
      ),
      'plan-changes' => array(
        'help' =>
          "Create or update a revision without requesting a code review.",
        'conflicts' => array(
          'only'     => '--only does not affect revisions.',
          'preview'  => '--preview does not affect revisions.',
        ),
      ),
      'encoding' => array(
        'param' => 'encoding',
        'help' =>
          "Attempt to convert non UTF-8 hunks into specified encoding.",
      ),
      'allow-untracked' => array(
        'help' =>
          "Skip checks for untracked files in the working copy.",
      ),
      'excuse' => array(
        'param' => 'excuse',
        'help' => 'Provide a prepared in advance excuse for any lints/tests'.
          ' shall they fail.',
      ),
      'less-context' => array(
        'help' =>
          "Normally, files are diffed with full context: the entire file is ".
          "sent to Differential so reviewers can 'show more' and see it. If ".
          "you are making changes to very large files with tens of thousands ".
          "of lines, this may not work well. With this flag, a diff will ".
          "be created that has only a few lines of context.",
      ),
      'lintall' => array(
        'help' =>
          "Raise all lint warnings, not just those on lines you changed.",
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'advice' => array(
        'help' =>
          "Require excuse for lint advice in addition to lint warnings and ".
          "errors.",
      ),
      'only-new' => array(
        'param' => 'bool',
        'help' =>
          'Display only lint messages not present in the original code.',
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'apply-patches' => array(
        'help' =>
          'Apply patches suggested by lint to the working copy without '.
          'prompting.',
        'conflicts' => array(
          'never-apply-patches' => true,
        ),
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'never-apply-patches' => array(
        'help' => 'Never apply patches suggested by lint.',
        'conflicts' => array(
          'apply-patches' => true,
        ),
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'amend-all' => array(
        'help' =>
          'When linting git repositories, amend HEAD with all patches '.
          'suggested by lint without prompting.',
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'amend-autofixes' => array(
        'help' =>
          'When linting git repositories, amend HEAD with autofix '.
          'patches suggested by lint without prompting.',
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'add-all' => array(
        'help' =>
          'Automatically add all untracked, unstaged and uncommitted files to '.
          'the commit.',
      ),
      'json' => array(
        'help' =>
          'Emit machine-readable JSON. EXPERIMENTAL! Probably does not work!',
      ),
      'no-amend' => array(
        'help' => 'Never amend commits in the working copy with lint patches.',
      ),
      'uncommitted' => array(
        'help' => 'Suppress warning about uncommitted changes.',
        'supports' => array(
          'hg',
        ),
      ),
      'verbatim' => array(
        'help' => 'When creating a revision, try to use the working copy '.
                  'commit message verbatim, without prompting to edit it. '.
                  'When updating a revision, update some fields from the '.
                  'local commit message.',
        'supports' => array(
          'hg',
          'git',
        ),
        'conflicts' => array(
          'use-commit-message'  => true,
          'update'              => true,
          'only'                => true,
          'preview'             => true,
          'raw'                 => true,
          'raw-command'         => true,
          'message-file'        => true,
        ),
      ),
      'reviewers' => array(
        'param' => 'usernames',
        'help' => 'When creating a revision, add reviewers.',
        'conflicts' => array(
          'only'    => true,
          'preview' => true,
          'update'  => true,
        ),
      ),
      'cc' => array(
        'param' => 'usernames',
        'help' => 'When creating a revision, add CCs.',
        'conflicts' => array(
          'only'    => true,
          'preview' => true,
          'update'  => true,
        ),
      ),
      'skip-binaries' => array(
        'help'  => 'Do not upload binaries (like images).',
      ),
      'ignore-unsound-tests' => array(
        'help'  => 'Ignore unsound test failures without prompting.',
      ),
      'base' => array(
        'param' => 'rules',
        'help'  => 'Additional rules for determining base revision.',
        'nosupport' => array(
          'svn' => 'Subversion does not use base commits.',
        ),
        'supports' => array('git', 'hg'),
      ),
      'no-diff' => array(
        'help' => 'Only run lint and unit tests. Intended for internal use.',
      ),
      'background' => array(
        'param' => 'bool',
        'help' =>
          'Run lint and unit tests on background. '.
          '"0" to disable, "1" to enable (default).',
      ),
      'cache' => array(
        'param' => 'bool',
        'help' => "0 to disable lint cache, 1 to enable (default).",
        'passthru' => array(
          'lint' => true,
        ),
      ),
      '*' => 'paths',
    );

    if (phutil_is_windows()) {
      unset($arguments['background']);
    }

    return $arguments;
  }

  public function isRawDiffSource() {
    return $this->getArgument('raw') || $this->getArgument('raw-command');
  }

  public function run() {
    $this->console = PhutilConsole::getConsole();

    $this->runRepositoryAPISetup();

    if ($this->getArgument('no-diff')) {
      $this->removeScratchFile('diff-result.json');
      $data = $this->runLintUnit();
      $this->writeScratchJSONFile('diff-result.json', $data);
      return 0;
    }

    $this->runDiffSetupBasics();

    $background = $this->getArgument('background', true);
    if ($this->isRawDiffSource() || phutil_is_windows()) {
      $background = false;
    }

    if ($background) {
      $argv = $this->getPassedArguments();
      if (!PhutilConsoleFormatter::getDisableANSI()) {
        array_unshift($argv, '--ansi');
      }

      $lint_unit = new ExecFuture(
        'php %s --recon diff --no-diff %Ls',
        phutil_get_library_root('arcanist').'/../scripts/arcanist.php',
        $argv);
      $lint_unit->write('', true);
      $lint_unit->start();
    }

    $commit_message = $this->buildCommitMessage();

    $this->dispatchEvent(
      ArcanistEventType::TYPE_DIFF_DIDBUILDMESSAGE,
      array());

    if (!$this->shouldOnlyCreateDiff()) {
      $revision = $this->buildRevisionFromCommitMessage($commit_message);
    }

    if ($background) {
      $server = new PhutilConsoleServer();
      $server->addExecFutureClient($lint_unit);
      $server->setHandler(array($this, 'handleServerMessage'));
      $server->run();

      list($err) = $lint_unit->resolve();
      $data = $this->readScratchJSONFile('diff-result.json');
      if ($err || !$data) {
        return 1;
      }
    } else {
      $server = $this->console->getServer();
      $server->setHandler(array($this, 'handleServerMessage'));
      $data = $this->runLintUnit();
    }
    $lint_result = $data['lintResult'];
    $this->unresolvedLint = $data['unresolvedLint'];
    $this->postponedLinters = $data['postponedLinters'];
    $unit_result = $data['unitResult'];
    $this->testResults = $data['testResults'];

    if ($this->getArgument('nolint')) {
      $this->excuses['lint'] = $this->getSkipExcuse(
        'Provide explanation for skipping lint or press Enter to abort:',
        'lint-excuses');
    }

    if ($this->getArgument('nounit')) {
      $this->excuses['unit'] = $this->getSkipExcuse(
        'Provide explanation for skipping unit tests or press Enter to abort:',
        'unit-excuses');
    }

    $changes = $this->generateChanges();
    if (!$changes) {
      throw new ArcanistUsageException(
        "There are no changes to generate a diff from!");
    }

    $diff_spec = array(
      'changes'                   => mpull($changes, 'toDictionary'),
      'lintStatus'                => $this->getLintStatus($lint_result),
      'unitStatus'                => $this->getUnitStatus($unit_result),
    ) + $this->buildDiffSpecification();

    $conduit = $this->getConduit();
    $diff_info = $conduit->callMethodSynchronous(
      'differential.creatediff',
      $diff_spec);

    $this->diffID = $diff_info['diffid'];

    $event = $this->dispatchEvent(
      ArcanistEventType::TYPE_DIFF_WASCREATED,
      array(
        'diffID' => $diff_info['diffid'],
        'lintResult' => $lint_result,
        'unitResult' => $unit_result,
      ));

    $this->updateLintDiffProperty();
    $this->updateUnitDiffProperty();
    $this->updateLocalDiffProperty();
    $this->resolveDiffPropertyUpdates();

    $output_json = $this->getArgument('json');

    if ($this->shouldOnlyCreateDiff()) {
      if (!$output_json) {
        echo phutil_console_format(
          "Created a new Differential diff:\n".
          "        **Diff URI:** __%s__\n\n",
          $diff_info['uri']);
      } else {
        $human = ob_get_clean();
        echo json_encode(array(
          'diffURI' => $diff_info['uri'],
          'diffID'  => $this->getDiffID(),
          'human'   => $human,
        ))."\n";
        ob_start();
      }
    } else {
      $revision['diffid'] = $this->getDiffID();

      if ($commit_message->getRevisionID()) {
        $result = $conduit->callMethodSynchronous(
          'differential.updaterevision',
          $revision);

        foreach (array('edit-messages.json', 'update-messages.json') as $file) {
          $messages = $this->readScratchJSONFile($file);
          unset($messages[$revision['id']]);
          $this->writeScratchJSONFile($file, $messages);
        }

        echo "Updated an existing Differential revision:\n";
      } else {
        $revision = $this->dispatchWillCreateRevisionEvent($revision);

        $result = $conduit->callMethodSynchronous(
          'differential.createrevision',
          $revision);

        $revised_message = $conduit->callMethodSynchronous(
          'differential.getcommitmessage',
          array(
            'revision_id' => $result['revisionid'],
          ));

        if ($this->shouldAmend()) {
          $repository_api = $this->getRepositoryAPI();
          if ($repository_api->supportsAmend()) {
            echo "Updating commit message...\n";
            $repository_api->amendCommit($revised_message);
          } else {
            echo "Commit message was not amended. Amending commit message is ".
                 "only supported in git and hg (version 2.2 or newer)";
          }
        }

        echo "Created a new Differential revision:\n";
      }

      $uri = $result['uri'];
      echo phutil_console_format(
        "        **Revision URI:** __%s__\n\n",
        $uri);

      if ($this->getArgument('plan-changes')) {
        $conduit->callMethodSynchronous(
          'differential.createcomment',
          array(
            'revision_id' => $result['revisionid'],
            'action' => 'rethink',
          ));
        echo "Planned changes to the revision.\n";
      }
    }

    echo "Included changes:\n";
    foreach ($changes as $change) {
      echo '  '.$change->renderTextSummary()."\n";
    }

    if ($output_json) {
      ob_get_clean();
    }

    $this->removeScratchFile('create-message');

    return 0;
  }

  private function runRepositoryAPISetup() {
    if (!$this->requiresRepositoryAPI()) {
      return;
    }

    $repository_api = $this->getRepositoryAPI();
    if ($this->getArgument('less-context')) {
      $repository_api->setDiffLinesOfContext(3);
    }

    $repository_api->setBaseCommitArgumentRules(
      $this->getArgument('base', ''));

    if ($repository_api->supportsCommitRanges()) {
      $this->parseBaseCommitArgument($this->getArgument('paths'));
    }
  }

  private function runDiffSetupBasics() {
    $output_json = $this->getArgument('json');
    if ($output_json) {
      // TODO: We should move this to a higher-level and put an indirection
      // layer between echoing stuff and stdout.
      ob_start();
    }

    if ($this->requiresWorkingCopy()) {
      $repository_api = $this->getRepositoryAPI();
      try {
        if ($this->getArgument('add-all')) {
          $this->setCommitMode(self::COMMIT_ENABLE);
        } else if ($this->getArgument('uncommitted')) {
          $this->setCommitMode(self::COMMIT_DISABLE);
        } else {
          $this->setCommitMode(self::COMMIT_ALLOW);
        }
        if ($repository_api instanceof ArcanistSubversionAPI) {
          $repository_api->limitStatusToPaths($this->getArgument('paths'));
        }
        $this->requireCleanWorkingCopy();
      } catch (ArcanistUncommittedChangesException $ex) {
        if ($repository_api instanceof ArcanistMercurialAPI) {

          // Some Mercurial users prefer to use it like SVN, where they don't
          // commit changes before sending them for review. This would be a
          // pretty bad workflow in Git, but Mercurial users are significantly
          // more expert at change management.

          $use_dirty_changes = false;
          if ($this->getArgument('uncommitted')) {
            // OK.
          } else {
            $ok = phutil_console_confirm(
              "You have uncommitted changes in your working copy. You can ".
              "include them in the diff, or abort and deal with them. (Use ".
              "'--uncommitted' to include them and skip this prompt.) ".
              "Do you want to include uncommitted changes in the diff?");
            if (!$ok) {
              throw $ex;
            }
          }

          $repository_api->setIncludeDirectoryStateInDiffs(true);
          $this->haveUncommittedChanges = true;
        } else {
          throw $ex;
        }
      }
    }
  }

  private function buildRevisionFromCommitMessage(
    ArcanistDifferentialCommitMessage $message) {

    $conduit = $this->getConduit();

    $revision_id = $message->getRevisionID();
    $revision = array(
      'fields' => $message->getFields(),
    );

    if ($revision_id) {

      // With '--verbatim', pass the (possibly modified) local fields. This
      // allows the user to edit some fields (like "title" and "summary")
      // locally without '--edit' and have changes automatically synchronized.
      // Without '--verbatim', we do not update the revision to reflect local
      // commit message changes.
      if ($this->getArgument('verbatim')) {
        $use_fields = $message->getFields();
      } else {
        $use_fields = array();
      }

      $should_edit = $this->getArgument('edit');
      $edit_messages = $this->readScratchJSONFile('edit-messages.json');
      $remote_corpus = idx($edit_messages, $revision_id);

      if (!$should_edit || !$remote_corpus || $use_fields) {
        if ($this->commitMessageFromRevision) {
          $remote_corpus = $this->commitMessageFromRevision;
        } else {
          $remote_corpus = $conduit->callMethodSynchronous(
            'differential.getcommitmessage',
            array(
              'revision_id' => $revision_id,
              'edit'        => 'edit',
              'fields'      => $use_fields,
            ));
        }
      }

      if ($should_edit) {
        $edited = $this->newInteractiveEditor($remote_corpus)
          ->setName('differential-edit-revision-info')
          ->editInteractively();
        if ($edited != $remote_corpus) {
          $remote_corpus = $edited;
          $edit_messages[$revision_id] = $remote_corpus;
          $this->writeScratchJSONFile('edit-messages.json', $edit_messages);
        }
      }

      if ($this->commitMessageFromRevision == $remote_corpus) {
        $new_message = $message;
      } else {
        $new_message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
          $remote_corpus);
        $new_message->pullDataFromConduit($conduit);
      }

      $revision['fields'] = $new_message->getFields();

      $revision['id'] = $revision_id;
      $this->revisionID = $revision_id;

      $revision['message'] = $this->getArgument('message');
      if (!strlen($revision['message'])) {
        $update_messages = $this->readScratchJSONFile('update-messages.json');

        $update_messages[$revision_id] = $this->getUpdateMessage(
          $revision['fields'],
          idx($update_messages, $revision_id));

        $revision['message'] = ArcanistCommentRemover::removeComments(
          $update_messages[$revision_id]);
        if (!strlen(trim($revision['message']))) {
          throw new ArcanistUserAbortException();
        }

        $this->writeScratchJSONFile('update-messages.json', $update_messages);
      }
    }

    return $revision;
  }

  protected function shouldOnlyCreateDiff() {

    if ($this->getArgument('create')) {
      return false;
    }

    if ($this->getArgument('update')) {
      return false;
    }

    if ($this->getArgument('use-commit-message')) {
      return false;
    }

    if ($this->isRawDiffSource()) {
      return true;
    }

    return $this->getArgument('preview') ||
           $this->getArgument('only');
  }

  private function generateAffectedPaths() {
    if ($this->isRawDiffSource()) {
      return array();
    }

    $repository_api = $this->getRepositoryAPI();
    if ($repository_api instanceof ArcanistSubversionAPI) {
      $file_list = new FileList($this->getArgument('paths', array()));
      $paths = $repository_api->getSVNStatus($externals = true);
      foreach ($paths as $path => $mask) {
        if (!$file_list->contains($repository_api->getPath($path), true)) {
          unset($paths[$path]);
        }
      }

      $warn_externals = array();
      foreach ($paths as $path => $mask) {
        $any_mod = ($mask & ArcanistRepositoryAPI::FLAG_ADDED) ||
                   ($mask & ArcanistRepositoryAPI::FLAG_MODIFIED) ||
                   ($mask & ArcanistRepositoryAPI::FLAG_DELETED);
        if ($mask & ArcanistRepositoryAPI::FLAG_EXTERNALS) {
          unset($paths[$path]);
          if ($any_mod) {
            $warn_externals[] = $path;
          }
        }
      }

      if ($warn_externals && !$this->hasWarnedExternals) {
        echo phutil_console_format(
          "The working copy includes changes to 'svn:externals' paths. These ".
          "changes will not be included in the diff because SVN can not ".
          "commit 'svn:externals' changes alongside normal changes.".
          "\n\n".
          "Modified 'svn:externals' files:".
          "\n\n".
          phutil_console_wrap(implode("\n", $warn_externals), 8));
        $prompt = "Generate a diff (with just local changes) anyway?";
        if (!phutil_console_confirm($prompt)) {
          throw new ArcanistUserAbortException();
        } else {
          $this->hasWarnedExternals = true;
        }
      }

    } else {
      $paths = $repository_api->getWorkingCopyStatus();
    }

    foreach ($paths as $path => $mask) {
      if ($mask & ArcanistRepositoryAPI::FLAG_UNTRACKED) {
        unset($paths[$path]);
      }
    }

    return $paths;
  }


  protected function generateChanges() {
    $parser = $this->newDiffParser();

    $is_raw = $this->isRawDiffSource();
    if ($is_raw) {

      if ($this->getArgument('raw')) {
        fwrite(STDERR, "Reading diff from stdin...\n");
        $raw_diff = file_get_contents('php://stdin');
      } else if ($this->getArgument('raw-command')) {
        list($raw_diff) = execx($this->getArgument('raw-command'));
      } else {
        throw new Exception("Unknown raw diff source.");
      }

      $changes = $parser->parseDiff($raw_diff);
      foreach ($changes as $key => $change) {
        // Remove "message" changes, e.g. from "git show".
        if ($change->getType() == ArcanistDiffChangeType::TYPE_MESSAGE) {
          unset($changes[$key]);
        }
      }
      return $changes;
    }

    $repository_api = $this->getRepositoryAPI();

    if ($repository_api instanceof ArcanistSubversionAPI) {
      $paths = $this->generateAffectedPaths();
      $this->primeSubversionWorkingCopyData($paths);

      // Check to make sure the user is diffing from a consistent base revision.
      // This is mostly just an abuse sanity check because it's silly to do this
      // and makes the code more difficult to effectively review, but it also
      // affects patches and makes them nonportable.
      $bases = $repository_api->getSVNBaseRevisions();

      // Remove all files with baserev "0"; these files are new.
      foreach ($bases as $path => $baserev) {
        if ($bases[$path] <= 0) {
          unset($bases[$path]);
        }
      }

      if ($bases) {
        $rev = reset($bases);

        $revlist = array();
        foreach ($bases as $path => $baserev) {
          $revlist[] = "    Revision {$baserev}, {$path}";
        }
        $revlist = implode("\n", $revlist);

        foreach ($bases as $path => $baserev) {
          if ($baserev !== $rev) {
            throw new ArcanistUsageException(
              "Base revisions of changed paths are mismatched. Update all ".
              "paths to the same base revision before creating a diff: ".
              "\n\n".
              $revlist);
          }
        }

        // If you have a change which affects several files, all of which are
        // at a consistent base revision, treat that revision as the effective
        // base revision. The use case here is that you made a change to some
        // file, which updates it to HEAD, but want to be able to change it
        // again without updating the entire working copy. This is a little
        // sketchy but it arises in Facebook Ops workflows with config files and
        // doesn't have any real material tradeoffs (e.g., these patches are
        // perfectly applyable).
        $repository_api->overrideSVNBaseRevisionNumber($rev);
      }

      $changes = $parser->parseSubversionDiff(
        $repository_api,
        $paths);
    } else if ($repository_api instanceof ArcanistGitAPI) {
      $diff = $repository_api->getFullGitDiff();
      if (!strlen($diff)) {
        throw new ArcanistUsageException(
          "No changes found. (Did you specify the wrong commit range?)");
      }
      $changes = $parser->parseDiff($diff);
    } else if ($repository_api instanceof ArcanistMercurialAPI) {
      $diff = $repository_api->getFullMercurialDiff();
      if (!strlen($diff)) {
        throw new ArcanistUsageException(
          "No changes found. (Did you specify the wrong commit range?)");
      }
      $changes = $parser->parseDiff($diff);
    } else {
      throw new Exception("Repository API is not supported.");
    }

    if (count($changes) > 250) {
      $count = number_format(count($changes));
      $link =
        "http://www.phabricator.com/docs/phabricator/article/".
        "Differential_User_Guide_Large_Changes.html";
      $message =
        "This diff has a very large number of changes ({$count}). ".
        "Differential works best for changes which will receive detailed ".
        "human review, and not as well for large automated changes or ".
        "bulk checkins. See {$link} for information about reviewing big ".
        "checkins. Continue anyway?";
      if (!phutil_console_confirm($message)) {
        throw new ArcanistUsageException(
          "Aborted generation of gigantic diff.");
      }
    }

    $limit = 1024 * 1024 * 4;
    foreach ($changes as $change) {
      $size = 0;
      foreach ($change->getHunks() as $hunk) {
        $size += strlen($hunk->getCorpus());
      }
      if ($size > $limit) {
        $file_name = $change->getCurrentPath();
        $change_size = number_format($size);
        $byte_warning =
          "Diff for '{$file_name}' with context is {$change_size} bytes in ".
          "length. Generally, source changes should not be this large.";
        if (!$this->getArgument('less-context')) {
          $byte_warning .=
            " If this file is a huge text file, try using the ".
            "'--less-context' flag.";
        }
        if ($repository_api instanceof ArcanistSubversionAPI) {
          throw new ArcanistUsageException(
            "{$byte_warning} If the file is not a text file, mark it as ".
            "binary with:".
            "\n\n".
            "  $ svn propset svn:mime-type application/octet-stream <filename>".
            "\n");
        } else {
          $confirm =
            "{$byte_warning} If the file is not a text file, you can ".
            "mark it 'binary'. Mark this file as 'binary' and continue?";
          if (phutil_console_confirm($confirm)) {
            $change->convertToBinaryChange();
          } else {
            throw new ArcanistUsageException(
              "Aborted generation of gigantic diff.");
          }
        }
      }
    }

    $try_encoding = nonempty($this->getArgument('encoding'), null);

    $utf8_problems = array();
    foreach ($changes as $change) {
      foreach ($change->getHunks() as $hunk) {
        $corpus = $hunk->getCorpus();
        if (!phutil_is_utf8($corpus)) {

          // If this corpus is heuristically binary, don't try to convert it.
          // mb_check_encoding() and mb_convert_encoding() are both very very
          // liberal about what they're willing to process.
          $is_binary = ArcanistDiffUtils::isHeuristicBinaryFile($corpus);
          if (!$is_binary) {

            if (!$try_encoding) {
              try {
                $try_encoding = $this->getRepositoryEncoding();
              } catch (ConduitClientException $e) {
                if ($e->getErrorCode() == 'ERR-BAD-ARCANIST-PROJECT') {
                  echo phutil_console_wrap(
                    "Lookup of encoding in arcanist project failed\n".
                    $e->getMessage());
                } else {
                  throw $e;
                }
              }
            }

            if ($try_encoding) {
              $corpus = phutil_utf8_convert($corpus, 'UTF-8', $try_encoding);
              $name = $change->getCurrentPath();
              if (phutil_is_utf8($corpus)) {
                $this->writeStatusMessage(
                  "Converted a '{$name}' hunk from '{$try_encoding}' ".
                  "to UTF-8.\n");
                $hunk->setCorpus($corpus);
                continue;
              }
            }
          }
          $utf8_problems[] = $change;
          break;
        }
      }
    }

    // If there are non-binary files which aren't valid UTF-8, warn the user
    // and treat them as binary changes. See D327 for discussion of why Arcanist
    // has this behavior.
    if ($utf8_problems) {
      $utf8_warning =
        pht(
          "This diff includes file(s) which are not valid UTF-8 (they contain ".
            "invalid byte sequences). You can either stop this workflow and ".
            "fix these files, or continue. If you continue, these files will ".
            "be marked as binary.",
          count($utf8_problems))."\n\n".
        "You can learn more about how Phabricator handles character encodings ".
        "(and how to configure encoding settings and detect and correct ".
        "encoding problems) by reading 'User Guide: UTF-8 and Character ".
        "Encoding' in the Phabricator documentation.\n\n";
        "    ".pht('AFFECTED FILE(S)', count($utf8_problems))."\n";
      $confirm = pht(
        'Do you want to mark these files as binary and continue?',
        count($utf8_problems));

      echo phutil_console_format("**Invalid Content Encoding (Non-UTF8)**\n");
      echo phutil_console_wrap($utf8_warning);

      $file_list = mpull($utf8_problems, 'getCurrentPath');
      $file_list = '    '.implode("\n    ", $file_list);
      echo $file_list;

      if (!phutil_console_confirm($confirm, $default_no = false)) {
        throw new ArcanistUsageException("Aborted workflow to fix UTF-8.");
      } else {
        foreach ($utf8_problems as $change) {
          $change->convertToBinaryChange();
        }
      }
    }

    foreach ($changes as $change) {
      if ($change->getFileType() != ArcanistDiffChangeType::FILE_BINARY) {
        continue;
      }

      $path = $change->getCurrentPath();

      $name = basename($path);

      $old_file = $change->getOriginalFileData();
      $old_dict = $this->uploadFile($old_file, $name, 'old binary');
      if ($old_dict['guid']) {
        $change->setMetadata('old:binary-phid', $old_dict['guid']);
      }
      $change->setMetadata('old:file:size',      $old_dict['size']);
      $change->setMetadata('old:file:mime-type', $old_dict['mime']);

      $new_file = $change->getCurrentFileData();
      $new_dict = $this->uploadFile($new_file, $name, 'new binary');
      if ($new_dict['guid']) {
        $change->setMetadata('new:binary-phid', $new_dict['guid']);
      }
      $change->setMetadata('new:file:size',      $new_dict['size']);
      $change->setMetadata('new:file:mime-type', $new_dict['mime']);

      $mime_type = coalesce($new_dict['mime'], $old_dict['mime']);
      if (preg_match('@^image/@', $mime_type)) {
        $change->setFileType(ArcanistDiffChangeType::FILE_IMAGE);
      }
    }

    return $changes;
  }

  private function uploadFile($data, $name, $desc) {
    $result = array(
      'guid' => null,
      'mime' => null,
      'size' => null
    );

    if ($this->getArgument('skip-binaries')) {
      return $result;
    }

    $result['size'] = $size = strlen($data);
    if (!$size) {
      return $result;
    }

    $tmp = new TempFile();
    Filesystem::writeFile($tmp, $data);
    $mime_type = Filesystem::getMimeType($tmp);
    $result['mime'] = $mime_type;

    echo "Uploading {$desc} '{$name}' ({$mime_type}, {$size} bytes)...\n";

    try {
      $guid = $this->getConduit()->callMethodSynchronous(
        'file.upload',
        array(
          'data_base64' => base64_encode($data),
          'name'        => $name,
      ));

      $result['guid'] = $guid;
    } catch (Exception $e) {
      echo "Failed to upload {$desc} '{$name}'.\n";

      if (!phutil_console_confirm('Continue?', $default_no = false)) {
        throw new ArcanistUsageException(
          'Aborted due to file upload failure. You can use --skip-binaries '.
          'to skip binary uploads.');
      }
    }
    return $result;
  }

  private function getGitParentLogInfo() {
    $info = array(
      'parent'        => null,
      'base_revision' => null,
      'base_path'     => null,
      'uuid'          => null,
    );

    $conduit = $this->getConduit();
    $repository_api = $this->getRepositoryAPI();

    $parser = $this->newDiffParser();
    $history_messages = $repository_api->getGitHistoryLog();
    if (!$history_messages) {
      // This can occur on the initial commit.
      return $info;
    }
    $history_messages = $parser->parseDiff($history_messages);

    foreach ($history_messages as $key => $change) {
      try {
        $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
          $change->getMetadata('message'));
        if ($message->getRevisionID() && $info['parent'] === null) {
          $info['parent'] = $message->getRevisionID();
        }
        if ($message->getGitSVNBaseRevision() &&
            $info['base_revision'] === null) {
          $info['base_revision'] = $message->getGitSVNBaseRevision();
          $info['base_path']     = $message->getGitSVNBasePath();
        }
        if ($message->getGitSVNUUID()) {
          $info['uuid'] = $message->getGitSVNUUID();
        }
        if ($info['parent'] && $info['base_revision']) {
          break;
        }
      } catch (ArcanistDifferentialCommitMessageParserException $ex) {
        // Ignore.
      } catch (ArcanistUsageException $ex) {
        // Ignore an invalid Differential Revision field in the parent commit
      }
    }

    return $info;
  }

  protected function primeSubversionWorkingCopyData($paths) {
    $repository_api = $this->getRepositoryAPI();

    $futures = array();
    $targets = array();
    foreach ($paths as $path => $mask) {
      $futures[] = $repository_api->buildDiffFuture($path);
      $targets[] = array('command' => 'diff', 'path' => $path);
      $futures[] = $repository_api->buildInfoFuture($path);
      $targets[] = array('command' => 'info', 'path' => $path);
    }

    foreach (Futures($futures)->limit(8) as $key => $future) {
      $target = $targets[$key];
      if ($target['command'] == 'diff') {
        $repository_api->primeSVNDiffResult(
          $target['path'],
          $future->resolve());
      } else {
        $repository_api->primeSVNInfoResult(
          $target['path'],
          $future->resolve());
      }
    }
  }

  private function shouldAmend() {
    if ($this->haveUncommittedChanges) {
      return false;
    }

    if ($this->isHistoryImmutable()) {
      return false;
    }

    if ($this->getArgument('no-amend')) {
      return false;
    }

    if ($this->isRawDiffSource()) {
      return false;
    }

    return true;
  }


/* -(  Lint and Unit Tests  )------------------------------------------------ */


  /**
   * @task lintunit
   */
  private function runLintUnit() {
    $lint_result = $this->runLint();
    $unit_result = $this->runUnit();
    return array(
      'lintResult' => $lint_result,
      'unresolvedLint' => $this->unresolvedLint,
      'postponedLinters' => $this->postponedLinters,
      'unitResult' => $unit_result,
      'testResults' => $this->testResults,
    );
  }


  /**
   * @task lintunit
   */
  private function runLint() {
    if ($this->getArgument('nolint') ||
        $this->getArgument('only') ||
        $this->isRawDiffSource()) {
      return ArcanistLintWorkflow::RESULT_SKIP;
    }

    $repository_api = $this->getRepositoryAPI();

    $this->console->writeOut("Linting...\n");
    try {
      $argv = $this->getPassthruArgumentsAsArgv('lint');
      if ($repository_api->supportsCommitRanges()) {
        $argv[] = '--rev';
        $argv[] = $repository_api->getBaseCommit();
      }

      $lint_workflow = $this->buildChildWorkflow('lint', $argv);

      if ($this->shouldAmend()) {
        // TODO: We should offer to create a checkpoint commit.
        $lint_workflow->setShouldAmendChanges(true);
      }

      $lint_result = $lint_workflow->run();

      switch ($lint_result) {
        case ArcanistLintWorkflow::RESULT_OKAY:
          if ($this->getArgument('advice') &&
              $lint_workflow->getUnresolvedMessages()) {
            $this->getErrorExcuse(
              'lint',
              "Lint issued unresolved advice.",
              'lint-excuses');
          } else {
            $this->console->writeOut(
              "<bg:green>** LINT OKAY **</bg> No lint problems.\n");
          }
          break;
        case ArcanistLintWorkflow::RESULT_WARNINGS:
          $this->getErrorExcuse(
            'lint',
            "Lint issued unresolved warnings.",
            'lint-excuses');
          break;
        case ArcanistLintWorkflow::RESULT_ERRORS:
          $this->console->writeOut(
            "<bg:red>** LINT ERRORS **</bg> Lint raised errors!\n");
          $this->getErrorExcuse(
            'lint',
            "Lint issued unresolved errors!",
            'lint-excuses');
          break;
        case ArcanistLintWorkflow::RESULT_POSTPONED:
          $this->console->writeOut(
            "<bg:yellow>** LINT POSTPONED **</bg> ".
            "Lint results are postponed.\n");
          break;
      }

      $this->unresolvedLint = array();
      foreach ($lint_workflow->getUnresolvedMessages() as $message) {
        $this->unresolvedLint[] = $message->toDictionary();
      }

      $this->postponedLinters = $lint_workflow->getPostponedLinters();

      return $lint_result;
    } catch (ArcanistNoEngineException $ex) {
      $this->console->writeOut("No lint engine configured for this project.\n");
    } catch (ArcanistNoEffectException $ex) {
      $this->console->writeOut("No paths to lint.\n");
    }

    return null;
  }


  /**
   * @task lintunit
   */
  private function runUnit() {
    if ($this->getArgument('nounit') ||
        $this->getArgument('only') ||
        $this->isRawDiffSource()) {
      return ArcanistUnitWorkflow::RESULT_SKIP;
    }

    $repository_api = $this->getRepositoryAPI();

    $this->console->writeOut("Running unit tests...\n");
    try {
      $argv = $this->getPassthruArgumentsAsArgv('unit');
      if ($repository_api->supportsCommitRanges()) {
        $argv[] = '--rev';
        $argv[] = $repository_api->getBaseCommit();
      }
      $unit_workflow = $this->buildChildWorkflow('unit', $argv);
      $unit_result = $unit_workflow->run();

      switch ($unit_result) {
        case ArcanistUnitWorkflow::RESULT_OKAY:
          $this->console->writeOut(
            "<bg:green>** UNIT OKAY **</bg> No unit test failures.\n");
          break;
        case ArcanistUnitWorkflow::RESULT_UNSOUND:
          if ($this->getArgument('ignore-unsound-tests')) {
            echo phutil_console_format(
              "<bg:yellow>** UNIT UNSOUND **</bg> Unit testing raised errors, ".
              "but all failing tests are unsound.\n");
          } else {
            $continue = $this->console->confirm(
              "Unit test results included failures, but all failing tests ".
              "are known to be unsound. Ignore unsound test failures?");
            if (!$continue) {
              throw new ArcanistUserAbortException();
            }
          }
          break;
        case ArcanistUnitWorkflow::RESULT_FAIL:
          $this->console->writeOut(
            "<bg:red>** UNIT ERRORS **</bg> Unit testing raised errors!\n");
          $this->getErrorExcuse(
            'unit',
            "Unit test results include failures!",
            'unit-excuses');
          break;
      }

      $this->testResults = array();
      foreach ($unit_workflow->getTestResults() as $test) {
        $this->testResults[] = array(
          'name'      => $test->getName(),
          'link'      => $test->getLink(),
          'result'    => $test->getResult(),
          'userdata'  => $test->getUserData(),
          'coverage'  => $test->getCoverage(),
          'extra'     => $test->getExtraData(),
        );
      }

      return $unit_result;
    } catch (ArcanistNoEngineException $ex) {
      $this->console->writeOut(
        "No unit test engine is configured for this project.\n");
    } catch (ArcanistNoEffectException $ex) {
      $this->console->writeOut("No tests to run.\n");
    }

    return null;
  }

  public function getTestResults() {
    return $this->testResults;
  }

  private function getSkipExcuse($prompt, $history) {
    $excuse = $this->getArgument('excuse');

    if ($excuse === null) {
      $history = $this->getRepositoryAPI()->getScratchFilePath($history);
      $excuse = phutil_console_prompt($prompt, $history);
      if ($excuse == '') {
        throw new ArcanistUserAbortException();
      }
    }

    return $excuse;
  }

  private function getErrorExcuse($type, $prompt, $history) {
    if ($this->getArgument('excuse')) {
      $this->console->sendMessage(array(
        'type'    => $type,
        'confirm'  => $prompt." Ignore them?",
      ));
      return;
    }

    $history = $this->getRepositoryAPI()->getScratchFilePath($history);

    $prompt .= " Provide explanation to continue or press Enter to abort.";
    $this->console->writeOut("\n\n%s", phutil_console_wrap($prompt));
    $this->console->sendMessage(array(
      'type'    => $type,
      'prompt'  => "Explanation:",
      'history' => $history,
    ));
  }

  public function handleServerMessage(PhutilConsoleMessage $message) {
    $data = $message->getData();
    $response = '';
    if (isset($data['prompt'])) {
      $response = phutil_console_prompt($data['prompt'], idx($data, 'history'));
    } else if (phutil_console_confirm($data['confirm'])) {
      $response = $this->getArgument('excuse');
    }
    if ($response == '') {
      throw new ArcanistUserAbortException();
    }
    $this->excuses[$data['type']] = $response;
    return null;
  }


/* -(  Commit and Update Messages  )----------------------------------------- */


  /**
   * @task message
   */
  private function buildCommitMessage() {
    if ($this->getArgument('preview') || $this->getArgument('only')) {
      return null;
    }

    $is_create = $this->getArgument('create');
    $is_update = $this->getArgument('update');
    $is_raw = $this->isRawDiffSource();
    $is_message = $this->getArgument('use-commit-message');
    $is_verbatim = $this->getArgument('verbatim');

    if ($is_message) {
      return $this->getCommitMessageFromCommit($is_message);
    }

    if ($is_verbatim) {
      return $this->getCommitMessageFromUser();
    }


    if (!$is_raw && !$is_create && !$is_update) {
      $repository_api = $this->getRepositoryAPI();
      $revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
        $this->getConduit(),
        array(
          'authors' => array($this->getUserPHID()),
          'status'  => 'status-open',
        ));
      if (!$revisions) {
        $is_create = true;
      } else if (count($revisions) == 1) {
        $revision = head($revisions);
        $is_update = $revision['id'];
      } else {
        throw new ArcanistUsageException(
          "There are several revisions which match the working copy:\n\n".
          $this->renderRevisionList($revisions)."\n".
          "Use '--update' to choose one, or '--create' to create a new ".
          "revision.");
      }
    }

    $message = null;
    if ($is_create) {
      $message_file = $this->getArgument('message-file');
      if ($message_file) {
        return $this->getCommitMessageFromFile($message_file);
      } else {
        return $this->getCommitMessageFromUser();
      }
    } else if ($is_update) {
      $revision_id = $this->normalizeRevisionID($is_update);
      if (!is_numeric($revision_id)) {
        throw new ArcanistUsageException(
          'Parameter to --update must be a Differential Revision number');
      }
      return $this->getCommitMessageFromRevision($revision_id);
    } else {
      // This is --raw without enough info to create a revision, so force just
      // a diff.
      return null;
    }
  }


  /**
   * @task message
   */
  private function getCommitMessageFromCommit($commit) {
    $text = $this->getRepositoryAPI()->getCommitMessage($commit);
    $message = ArcanistDifferentialCommitMessage::newFromRawCorpus($text);
    $message->pullDataFromConduit($this->getConduit());
    $this->validateCommitMessage($message);
    return $message;
  }


  /**
   * @task message
   */
  private function getCommitMessageFromUser() {
    $conduit = $this->getConduit();

    $template = null;

    if (!$this->getArgument('verbatim')) {
      $saved = $this->readScratchFile('create-message');
      if ($saved) {
        $where = $this->getReadableScratchFilePath('create-message');

        $preview = explode("\n", $saved);
        $preview = array_shift($preview);
        $preview = trim($preview);
        $preview = phutil_utf8_shorten($preview, 64);

        if ($preview) {
          $preview = "Message begins:\n\n       {$preview}\n\n";
        } else {
          $preview = null;
        }

        echo
          "You have a saved revision message in '{$where}'.\n".
          "{$preview}".
          "You can use this message, or discard it.";

        $use = phutil_console_confirm(
          "Do you want to use this message?",
          $default_no = false);
        if ($use) {
          $template = $saved;
        } else {
          $this->removeScratchFile('create-message');
        }
      }
    }

    $template_is_default = false;
    $notes = array();
    $included = array();

    list($fields, $notes, $included_commits) = $this->getDefaultCreateFields();
    if ($template) {
      $fields = array();
      $notes = array();
    } else {
      if (!$fields) {
        $template_is_default = true;
      }

      if ($notes) {
        $commit = head($this->getRepositoryAPI()->getLocalCommitInformation());
        $template = $commit['message'];
      } else {
        $template = $conduit->callMethodSynchronous(
          'differential.getcommitmessage',
          array(
            'revision_id' => null,
            'edit'        => 'create',
            'fields'      => $fields,
          ));
      }
    }
    $old_message = $template;

    $included = array();
    if ($included_commits) {
      foreach ($included_commits as $commit) {
        $included[] = '        '.$commit;
      }
      $in_branch = '';
      if (!$this->isRawDiffSource()) {
        $in_branch = ' in branch '.$this->getRepositoryAPI()->getBranchName();
      }
      $included = array_merge(
        array(
          "",
          "Included commits{$in_branch}:",
          "",
        ),
        $included);
    }

    $issues = array_merge(
      array(
        'NEW DIFFERENTIAL REVISION',
        'Describe the changes in this new revision.',
      ),
      $included,
      array(
        '',
        'arc could not identify any existing revision in your working copy.',
        'If you intended to update an existing revision, use:',
        '',
        '  $ arc diff --update <revision>',
      ));
    if ($notes) {
      $issues = array_merge($issues, array(''), $notes);
    }

    $done = false;
    $first = true;
    while (!$done) {
      $template = rtrim($template, "\r\n")."\n\n";
      foreach ($issues as $issue) {
        $template .= '# '.$issue."\n";
      }
      $template .= "\n";

      if ($first && $this->getArgument('verbatim') && !$template_is_default) {
        $new_template = $template;
      } else {
        $new_template = $this->newInteractiveEditor($template)
          ->setName('new-commit')
          ->editInteractively();
      }
      $first = false;

      if ($template_is_default && ($new_template == $template)) {
        throw new ArcanistUsageException("Template not edited.");
      }

      $template = ArcanistCommentRemover::removeComments($new_template);

      $repository_api = $this->getRepositoryAPI();
      // special check for whether to amend here. optimizes a common git
      // workflow. we can't do this for mercurial because the mq extension
      // is popular and incompatible with hg commit --amend ; see T2011.
      $should_amend = (count($included_commits) == 1 &&
                       $repository_api instanceof ArcanistGitAPI &&
                       $this->shouldAmend());
      if ($should_amend) {
        $wrote = (rtrim($old_message) != rtrim($template));
        if ($wrote) {
          $repository_api->amendCommit($template);
          $where = 'commit message';
        }
      } else {
        $wrote = $this->writeScratchFile('create-message', $template);
        $where = "'".$this->getReadableScratchFilePath('create-message')."'";
      }

      try {
        $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
          $template);
        $message->pullDataFromConduit($conduit);
        $this->validateCommitMessage($message);
        $done = true;
      } catch (ArcanistDifferentialCommitMessageParserException $ex) {
        echo "Commit message has errors:\n\n";
        $issues = array('Resolve these errors:');
        foreach ($ex->getParserErrors() as $error) {
          echo phutil_console_wrap("- ".$error."\n", 6);
          $issues[] = '  - '.$error;
        }
        echo "\n";
        echo "You must resolve these errors to continue.";
        $again = phutil_console_confirm(
          "Do you want to edit the message?",
          $default_no = false);
        if ($again) {
          // Keep going.
        } else {
          $saved = null;
          if ($wrote) {
            $saved = "A copy was saved to {$where}.";
          }
          throw new ArcanistUsageException(
            "Message has unresolved errrors. {$saved}");
        }
      } catch (Exception $ex) {
        if ($wrote) {
          echo phutil_console_wrap("(Message saved to {$where}.)\n");
        }
        throw $ex;
      }
    }

    return $message;
  }


  /**
   * @task message
   */
  private function getCommitMessageFromFile($file) {
    $conduit = $this->getConduit();

    $data = Filesystem::readFile($file);
    $message = ArcanistDifferentialCommitMessage::newFromRawCorpus($data);
    $message->pullDataFromConduit($conduit);

    $this->validateCommitMessage($message);

    return $message;
  }


  /**
   * @task message
   */
  private function getCommitMessageFromRevision($revision_id) {
    $id = $revision_id;

    $revision = $this->getConduit()->callMethodSynchronous(
      'differential.query',
      array(
        'ids' => array($id),
      ));
    $revision = head($revision);

    if (!$revision) {
      throw new ArcanistUsageException(
        "Revision '{$revision_id}' does not exist!");
    }

    $this->checkRevisionOwnership($revision);

    $message = $this->getConduit()->callMethodSynchronous(
      'differential.getcommitmessage',
      array(
        'revision_id' => $id,
        'edit'        => false,
      ));
    $this->commitMessageFromRevision = $message;

    $obj = ArcanistDifferentialCommitMessage::newFromRawCorpus($message);
    $obj->pullDataFromConduit($this->getConduit());

    return $obj;
  }


  /**
   * @task message
   */
  private function validateCommitMessage(
    ArcanistDifferentialCommitMessage $message) {
    $futures = array();

    $revision_id = $message->getRevisionID();
    if ($revision_id) {
      $futures['revision'] = $this->getConduit()->callMethod(
        'differential.query',
        array(
          'ids' => array($revision_id),
        ));
    }

    $reviewers = $message->getFieldValue('reviewerPHIDs');
    if (!$reviewers) {
      $confirm = "You have not specified any reviewers. Continue anyway?";
      if (!phutil_console_confirm($confirm)) {
        throw new ArcanistUsageException('Specify reviewers and retry.');
      }
    } else {
      $futures['reviewers'] = $this->getConduit()->callMethod(
        'user.query',
        array(
          'phids' => $reviewers,
        ));
    }

    foreach (Futures($futures) as $key => $future) {
      $result = $future->resolve();
      switch ($key) {
        case 'revision':
          if (empty($result)) {
            throw new ArcanistUsageException(
              "There is no revision D{$revision_id}.");
          }
          $this->checkRevisionOwnership(head($result));
          break;
        case 'reviewers':
          $untils = array();
          foreach ($result as $user) {
            if (idx($user, 'currentStatus') == 'away') {
              $untils[] = $user['currentStatusUntil'];
            }
          }
          if (count($untils) == count($reviewers)) {
            $until = date('l, M j Y', min($untils));
            $confirm = "All reviewers are away until {$until}. ".
                       "Continue anyway?";
            if (!phutil_console_confirm($confirm)) {
              throw new ArcanistUsageException(
                'Specify available reviewers and retry.');
            }
          }
          break;
      }
    }

  }


  /**
   * @task message
   */
  private function getUpdateMessage(array $fields, $template = '') {
    if ($this->getArgument('raw')) {
      throw new ArcanistUsageException(
        "When using '--raw' to update a revision, specify an update message ".
        "with '--message'. (Normally, we'd launch an editor to ask you for a ".
        "message, but can not do that because stdin is the diff source.)");
    }

    // When updating a revision using git without specifying '--message', try
    // to prefill with the message in HEAD if it isn't a template message. The
    // idea is that if you do:
    //
    //  $ git commit -a -m 'fix some junk'
    //  $ arc diff
    //
    // ...you shouldn't have to retype the update message. Similar things apply
    // to Mercurial.

    if ($template == '') {
      $comments = $this->getDefaultUpdateMessage();

      $template =
        rtrim($comments).
        "\n\n".
        "# Updating D{$fields['revisionID']}: {$fields['title']}\n".
        "#\n".
        "# Enter a brief description of the changes included in this update.\n".
        "# The first line is used as subject, next lines as comment.\n".
        "#\n".
        "# If you intended to create a new revision, use:\n".
        "#  $ arc diff --create\n".
        "\n";
    }

    $comments = $this->newInteractiveEditor($template)
      ->setName('differential-update-comments')
      ->editInteractively();

    return $comments;
  }

  private function getDefaultCreateFields() {
    $result = array(array(), array(), array());

    if ($this->isRawDiffSource()) {
      return $result;
    }

    $repository_api = $this->getRepositoryAPI();
    $local = $repository_api->getLocalCommitInformation();
    if ($local) {
      $result = $this->parseCommitMessagesIntoFields($local);
    }

    $result[0] = $this->dispatchWillBuildEvent($result[0]);

    return $result;
  }

  /**
   * Convert a list of commits from `getLocalCommitInformation()` into
   * a format usable by arc to create a new diff. Specifically, we emit:
   *
   *   - A dictionary of commit message fields.
   *   - A list of errors encountered while parsing the messages.
   *   - A human-readable list of the commits themselves.
   *
   * For example, if the user runs "arc diff HEAD^^^" and selects a diff range
   * which includes several diffs, we attempt to merge them somewhat
   * intelligently into a single message, because we can only send one
   * "Summary:", "Reviewers:", etc., field to Differential. We also return
   * errors (e.g., if the user typed a reviewer name incorrectly) and a
   * summary of the commits themselves.
   *
   * @param dict  Local commit information.
   * @return list Complex output, see summary.
   * @task message
   */
  private function parseCommitMessagesIntoFields(array $local) {
    $conduit = $this->getConduit();
    $local = ipull($local, null, 'commit');

    // If the user provided "--reviewers" or "--ccs", add a faux message to
    // the list with the implied fields.

    $faux_message = array();
    if ($this->getArgument('reviewers')) {
      $faux_message[] = 'Reviewers: '.$this->getArgument('reviewers');
    }
    if ($this->getArgument('cc')) {
      $faux_message[] = 'CC: '.$this->getArgument('cc');
    }

    if ($faux_message) {
      $faux_message = implode("\n\n", $faux_message);
      $local = array(
        '(Flags)     ' => array(
          'message' => $faux_message,
          'summary' => 'Command-Line Flags',
        ),
      ) + $local;
    }

    // Build a human-readable list of the commits, so we can show the user which
    // commits are included in the diff.
    $included = array();
    foreach ($local as $hash => $info) {
      $included[] = substr($hash, 0, 12).' '.$info['summary'];
    }

    // Parse all of the messages into fields.
    $messages = array();
    foreach ($local as $hash => $info) {
      $text = $info['message'];
      if (trim($text) == self::AUTO_COMMIT_TITLE) {
        continue;
      }
      $obj = ArcanistDifferentialCommitMessage::newFromRawCorpus($text);
      $messages[$hash] = $obj;
    }

    $notes = array();
    $fields = array();
    foreach ($messages as $hash => $message) {
      try {
        $message->pullDataFromConduit($conduit, $partial = true);
        $fields[$hash] = $message->getFields();
      } catch (ArcanistDifferentialCommitMessageParserException $ex) {
        if ($this->getArgument('verbatim')) {
          // In verbatim mode, just bail when we hit an error. The user can
          // rerun without --verbatim if they want to fix it manually. Most
          // users will probably `git commit --amend` instead.
          throw $ex;
        }
        $fields[$hash] = $message->getFields();

        $frev = substr($hash, 0, 12);
        $notes[] = "NOTE: commit {$frev} could not be completely parsed:";
        foreach ($ex->getParserErrors() as $error) {
          $notes[] = "  - {$error}";
        }
      }
    }

    // Merge commit message fields. We do this somewhat-intelligently so that
    // multiple "Reviewers" or "CC" fields will merge into the concatenation
    // of all values.

    // We have special parsing rules for 'title' because we can't merge
    // multiple titles, and one-line commit messages like "fix stuff" will
    // parse as titles. Instead, pick the first title we encounter. When we
    // encounter subsequent titles, treat them as part of the summary. Then
    // we merge all the summaries together below.

    $result = array();

    // Process fields in oldest-first order, so earlier commits get to set the
    // title of record and reviewers/ccs are listed in chronological order.
    $fields = array_reverse($fields);

    foreach ($fields as $hash => $dict) {
      $title = idx($dict, 'title');
      if (!strlen($title)) {
        continue;
      }

      if (!isset($result['title'])) {
        // We don't have a title yet, so use this one.
        $result['title'] = $title;
      } else {
        // We already have a title, so merge this new title into the summary.
        $summary = idx($dict, 'summary');
        if ($summary) {
          $summary = $title."\n\n".$summary;
        } else {
          $summary = $title;
        }
        $fields[$hash]['summary'] = $summary;
      }
    }

    // Now, merge all the other fields in a general sort of way.

    foreach ($fields as $hash => $dict) {
      foreach ($dict as $key => $value) {
        if ($key == 'title') {
          // This has been handled above, and either assigned directly or
          // merged into the summary.
          continue;
        }

        if (is_array($value)) {
          // For array values, merge the arrays, appending the new values.
          // Examples are "Reviewers" and "Cc", where this produces a list of
          // all users specified as reviewers.
          $cur = idx($result, $key, array());
          $new = array_merge($cur, $value);
          $result[$key] = $new;
          continue;
        } else {
          if (!strlen(trim($value))) {
            // Ignore empty fields.
            continue;
          }

          // For string values, append the new field to the old field with
          // a blank line separating them. Examples are "Test Plan" and
          // "Summary".
          $cur = idx($result, $key, '');
          if (strlen($cur)) {
            $new = $cur."\n\n".$value;
          } else {
            $new = $value;
          }
          $result[$key] = $new;
        }
      }
    }

    return array($result, $notes, $included);
  }

  private function getDefaultUpdateMessage() {
    if ($this->isRawDiffSource()) {
      return null;
    }

    $repository_api = $this->getRepositoryAPI();
    if ($repository_api instanceof ArcanistGitAPI) {
      return $this->getGitUpdateMessage();
    }

    if ($repository_api instanceof ArcanistMercurialAPI) {
      return $this->getMercurialUpdateMessage();
    }

    return null;
  }

  /**
   * Retrieve the git messages between HEAD and the last update.
   *
   * @task message
   */
  private function getGitUpdateMessage() {
    $repository_api = $this->getRepositoryAPI();

    $parser = $this->newDiffParser();
    $commit_messages = $repository_api->getGitCommitLog();
    $commit_messages = $parser->parseDiff($commit_messages);

    if (count($commit_messages) == 1) {
      // If there's only one message, assume this is an amend-based workflow and
      // that using it to prefill doesn't make sense.
      return null;
    }

    // We have more than one message, so figure out which ones are new. We
    // do this by pulling the current diff and comparing commit hashes in the
    // working copy with attached commit hashes. It's not super important that
    // we always get this 100% right, we're just trying to do something
    // reasonable.

    $local = $this->loadActiveLocalCommitInfo();
    $hashes = ipull($local, null, 'commit');

    $usable = array();
    foreach ($commit_messages as $message) {
      $text = $message->getMetadata('message');

      $parsed = ArcanistDifferentialCommitMessage::newFromRawCorpus($text);
      if ($parsed->getRevisionID()) {
        // If this is an amended commit message with a revision ID, it's
        // certainly not new. Stop marking commits as usable and break out.
        break;
      }

      if (isset($hashes[$message->getCommitHash()])) {
        // If this commit is currently part of the diff, stop using commit
        // messages, since anything older than this isn't new.
        break;
      }

      // Otherwise, this looks new, so it's a usable commit message.
      $usable[] = $text;
    }

    if (!$usable) {
      // No new commit messages, so we don't have anywhere to start from.
      return null;
    }

    return $this->formatUsableLogs($usable);
  }

  /**
   * Retrieve the hg messages between tip and the last update.
   *
   * @task message
   */
  private function getMercurialUpdateMessage() {
    $repository_api = $this->getRepositoryAPI();

    $messages = $repository_api->getCommitMessageLog();

    $local = $this->loadActiveLocalCommitInfo();
    $hashes = ipull($local, null, 'commit');

    $usable = array();
    foreach ($messages as $rev => $message) {
      if (isset($hashes[$rev])) {
        // If this commit is currently part of the active diff on the revision,
        // stop using commit messages, since anything older than this isn't new.
        break;
      }

      // Otherwise, this looks new, so it's a usable commit message.
      $usable[] = $message;
    }

    if (!$usable) {
      // No new commit messages, so we don't have anywhere to start from.
      return null;
    }

    return $this->formatUsableLogs($usable);
  }


  /**
   * Format log messages to prefill a diff update.
   *
   * @task message
   */
  private function formatUsableLogs(array $usable) {
    // Flip messages so they'll read chronologically (oldest-first) in the
    // template, e.g.:
    //
    //   - Added foobar.
    //   - Fixed foobar bug.
    //   - Documented foobar.

    $usable = array_reverse($usable);
    $default = array();
    foreach ($usable as $message) {
      // Pick the first line out of each message.
      $text = trim($message);
      if ($text == self::AUTO_COMMIT_TITLE) {
        continue;
      }
      $text = head(explode("\n", $text));
      $default[] = '  - '.$text."\n";
    }

    return implode('', $default);
  }

  private function loadActiveLocalCommitInfo() {
    $current_diff = $this->getConduit()->callMethodSynchronous(
      'differential.getdiff',
      array(
        'revision_id' => $this->revisionID,
      ));

    $properties = idx($current_diff, 'properties', array());
    return idx($properties, 'local:commits', array());
  }


/* -(  Diff Specification  )------------------------------------------------- */


  /**
   * @task diffspec
   */
  private function getLintStatus($lint_result) {
    $map = array(
      ArcanistLintWorkflow::RESULT_OKAY       => 'okay',
      ArcanistLintWorkflow::RESULT_ERRORS     => 'fail',
      ArcanistLintWorkflow::RESULT_WARNINGS   => 'warn',
      ArcanistLintWorkflow::RESULT_SKIP       => 'skip',
      ArcanistLintWorkflow::RESULT_POSTPONED  => 'postponed',
    );
    return idx($map, $lint_result, 'none');
  }


  /**
   * @task diffspec
   */
  private function getUnitStatus($unit_result) {
    $map = array(
      ArcanistUnitWorkflow::RESULT_OKAY       => 'okay',
      ArcanistUnitWorkflow::RESULT_FAIL       => 'fail',
      ArcanistUnitWorkflow::RESULT_UNSOUND    => 'warn',
      ArcanistUnitWorkflow::RESULT_SKIP       => 'skip',
      ArcanistUnitWorkflow::RESULT_POSTPONED  => 'postponed',
    );
    return idx($map, $unit_result, 'none');
  }


  /**
   * @task diffspec
   */
  private function buildDiffSpecification() {

    $base_revision  = null;
    $base_path      = null;
    $vcs            = null;
    $repo_uuid      = null;
    $parent         = null;
    $source_path    = null;
    $branch         = null;
    $bookmark       = null;

    if (!$this->isRawDiffSource()) {
      $repository_api = $this->getRepositoryAPI();

      $base_revision  = $repository_api->getSourceControlBaseRevision();
      $base_path      = $repository_api->getSourceControlPath();
      $vcs            = $repository_api->getSourceControlSystemName();
      $source_path    = $repository_api->getPath();
      $branch         = $repository_api->getBranchName();

      if ($repository_api instanceof ArcanistGitAPI) {
        $info = $this->getGitParentLogInfo();
        if ($info['parent']) {
          $parent = $info['parent'];
        }
        if ($info['base_revision']) {
          $base_revision = $info['base_revision'];
        }
        if ($info['base_path']) {
          $base_path = $info['base_path'];
        }
        if ($info['uuid']) {
          $repo_uuid = $info['uuid'];
        }
      } else if ($repository_api instanceof ArcanistSubversionAPI) {
        $repo_uuid = $repository_api->getRepositorySVNUUID();
      } else if ($repository_api instanceof ArcanistMercurialAPI) {

        $bookmark = $repository_api->getActiveBookmark();
        $svn_info = $repository_api->getSubversionInfo();
        $repo_uuid = idx($svn_info, 'uuid');
        $base_path = idx($svn_info, 'base_path', $base_path);
        $base_revision = idx($svn_info, 'base_revision', $base_revision);

        // TODO: provide parent info

      } else {
        throw new Exception("Unsupported repository API!");
      }
    }

    $project_id = null;
    if ($this->requiresWorkingCopy()) {
      $project_id = $this->getWorkingCopy()->getProjectID();
    }

    return array(
      'sourceMachine'             => php_uname('n'),
      'sourcePath'                => $source_path,
      'branch'                    => $branch,
      'bookmark'                  => $bookmark,
      'sourceControlSystem'       => $vcs,
      'sourceControlPath'         => $base_path,
      'sourceControlBaseRevision' => $base_revision,
      'parentRevisionID'          => $parent,
      'repositoryUUID'            => $repo_uuid,
      'creationMethod'            => 'arc',
      'arcanistProject'           => $project_id,
      'authorPHID'                => $this->getUserPHID(),
    );
  }


/* -(  Diff Properties  )---------------------------------------------------- */


  /**
   * Update lint information for the diff.
   *
   * @return void
   *
   * @task diffprop
   */
  private function updateLintDiffProperty() {
    if (strlen($this->excuses['lint'])) {
      $this->updateDiffProperty('arc:lint-excuse',
        json_encode($this->excuses['lint']));
    }

    if ($this->unresolvedLint) {
      $this->updateDiffProperty('arc:lint', json_encode($this->unresolvedLint));
    }

    $postponed = $this->postponedLinters;
    if ($postponed) {
      $this->updateDiffProperty('arc:lint-postponed', json_encode($postponed));
    }

  }


  /**
   * Update unit test information for the diff.
   *
   * @return void
   *
   * @task diffprop
   */
  private function updateUnitDiffProperty() {
    if (strlen($this->excuses['unit'])) {
      $this->updateDiffProperty('arc:unit-excuse',
        json_encode($this->excuses['unit']));
    }

    if ($this->testResults) {
      $this->updateDiffProperty('arc:unit', json_encode($this->testResults));
    }
  }


  /**
   * Update local commit information for the diff.
   *
   * @task diffprop
   */
  private function updateLocalDiffProperty() {
    if ($this->isRawDiffSource()) {
      return;
    }

    $local_info = $this->getRepositoryAPI()->getLocalCommitInformation();
    if (!$local_info) {
      return;
    }

    $this->updateDiffProperty('local:commits', json_encode($local_info));
  }


  /**
   * Update an arbitrary diff property.
   *
   * @param string Diff property name.
   * @param string Diff property value.
   * @return void
   *
   * @task diffprop
   */
  private function updateDiffProperty($name, $data) {
    $this->diffPropertyFutures[] = $this->getConduit()->callMethod(
      'differential.setdiffproperty',
      array(
        'diff_id' => $this->getDiffID(),
        'name'    => $name,
        'data'    => $data,
      ));
  }

  /**
   * Wait for finishing all diff property updates.
   *
   * @return void
   *
   * @task diffprop
   */
  private function resolveDiffPropertyUpdates() {
    Futures($this->diffPropertyFutures)->resolveAll();
    $this->diffPropertyFutures = array();
  }

  private function dispatchWillCreateRevisionEvent(array $fields) {
    $event = $this->dispatchEvent(
      ArcanistEventType::TYPE_REVISION_WILLCREATEREVISION,
      array(
        'specification' => $fields,
      ));

    return $event->getValue('specification');
  }

  private function dispatchWillBuildEvent(array $fields) {
    $event = $this->dispatchEvent(
      ArcanistEventType::TYPE_DIFF_WILLBUILDMESSAGE,
      array(
        'fields' => $fields,
      ));

    return $event->getValue('fields');
  }

  private function checkRevisionOwnership(array $revision) {
    if ($revision['authorPHID'] == $this->getUserPHID()) {
      return;
    }

    $id = $revision['id'];
    $title = $revision['title'];

    throw new ArcanistUsageException(
      "You don't own revision D{$id} '{$title}'. You can only update ".
      "revisions you own. You can 'Commandeer' this revision from the web ".
      "interface if you want to become the owner.");
  }

}
