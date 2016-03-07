<?php

/**
 * Sends changes from your working copy to Differential for code review.
 *
 * @task lintunit   Lint and Unit Tests
 * @task message    Commit and Update Messages
 * @task diffspec   Diff Specification
 * @task diffprop   Diff Properties
 */
final class ArcanistDiffWorkflow extends ArcanistWorkflow {

  private $console;
  private $hasWarnedExternals = false;
  private $unresolvedLint;
  private $excuses = array('lint' => null, 'unit' => null);
  private $testResults;
  private $diffID;
  private $revisionID;
  private $haveUncommittedChanges = false;
  private $diffPropertyFutures = array();
  private $commitMessageFromRevision;
  private $hitAutotargets;

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

          Under git and mercurial, you can specify a commit (like __HEAD^^^__
          or __master__) and Differential will generate a diff against the
          merge base of that commit and your current working directory parent.

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
        'help' => pht(
          'When updating a revision, use the specified message instead of '.
          'prompting.'),
      ),
      'message-file' => array(
        'short' => 'F',
        'param' => 'file',
        'paramtype' => 'file',
        'help' => pht(
          'When creating a revision, read revision information '.
          'from this file.'),
      ),
      'use-commit-message' => array(
        'supports' => array(
          'git',
          // TODO: Support mercurial.
        ),
        'short' => 'C',
        'param' => 'commit',
        'help' => pht('Read revision information from a specific commit.'),
        'conflicts' => array(
          'only'    => null,
          'preview' => null,
          'update'  => null,
        ),
      ),
      'edit' => array(
        'supports'    => array(
          'git',
          'hg',
        ),
        'nosupport'   => array(
          'svn' => pht('Edit revisions via the web interface when using SVN.'),
        ),
        'help' => pht(
          'When updating a revision under git, edit revision information '.
          'before updating.'),
      ),
      'raw' => array(
        'help' => pht(
          'Read diff from stdin, not from the working copy. This disables '.
          'many Arcanist/Phabricator features which depend on having access '.
          'to the working copy.'),
        'conflicts' => array(
          'less-context'        => null,
          'apply-patches'       => pht('%s disables lint.', '--raw'),
          'never-apply-patches' => pht('%s disables lint.', '--raw'),
          'advice'              => pht('%s disables lint.', '--raw'),
          'lintall'             => pht('%s disables lint.', '--raw'),

          'create'              => pht(
            '%s and %s both need stdin. Use %s.',
            '--raw',
            '--create',
            '--raw-command'),
          'edit'                => pht(
            '%s and %s both need stdin. Use %s.',
            '--raw',
            '--edit',
            '--raw-command'),
          'raw-command'         => null,
        ),
      ),
      'raw-command' => array(
        'param' => 'command',
        'help' => pht(
          'Generate diff by executing a specified command, not from the '.
          'working copy. This disables many Arcanist/Phabricator features '.
          'which depend on having access to the working copy.'),
        'conflicts' => array(
          'less-context'        => null,
          'apply-patches'       => pht('%s disables lint.', '--raw-command'),
          'never-apply-patches' => pht('%s disables lint.', '--raw-command'),
          'advice'              => pht('%s disables lint.', '--raw-command'),
          'lintall'             => pht('%s disables lint.', '--raw-command'),
        ),
      ),
      'create' => array(
        'help' => pht('Always create a new revision.'),
        'conflicts' => array(
          'edit'    => pht(
            '%s can not be used with %s.',
            '--create',
            '--edit'),
          'only'    => pht(
            '%s can not be used with %s.',
            '--create',
            '--only'),
          'preview' => pht(
            '%s can not be used with %s.',
            '--create',
            '--preview'),
          'update'  => pht(
            '%s can not be used with %s.',
            '--create',
            '--update'),
        ),
      ),
      'update' => array(
        'param' => 'revision_id',
        'help'  => pht('Always update a specific revision.'),
      ),
      'nounit' => array(
        'help' => pht('Do not run unit tests.'),
      ),
      'nolint' => array(
        'help' => pht('Do not run lint.'),
        'conflicts' => array(
          'lintall'   => pht('%s suppresses lint.', '--nolint'),
          'advice'    => pht('%s suppresses lint.', '--nolint'),
          'apply-patches' => pht('%s suppresses lint.', '--nolint'),
          'never-apply-patches' => pht('%s suppresses lint.', '--nolint'),
        ),
      ),
      'only' => array(
        'help' => pht(
          'Only generate a diff, without running lint, unit tests, or other '.
          'auxiliary steps. See also %s.',
          '--preview'),
        'conflicts' => array(
          'preview'   => null,
          'message'   => pht('%s does not affect revisions.', '--only'),
          'edit'      => pht('%s does not affect revisions.', '--only'),
          'lintall'   => pht('%s suppresses lint.', '--only'),
          'advice'    => pht('%s suppresses lint.', '--only'),
          'apply-patches' => pht('%s suppresses lint.', '--only'),
          'never-apply-patches' => pht('%s suppresses lint.', '--only'),
        ),
      ),
      'preview' => array(
        'help' => pht(
          'Instead of creating or updating a revision, only create a diff, '.
          'which you may later attach to a revision. This still runs lint '.
          'unit tests. See also %s.',
          '--only'),
        'conflicts' => array(
          'only'      => null,
          'edit'      => pht('%s does affect revisions.', '--preview'),
          'message'   => pht('%s does not update any revision.', '--preview'),
        ),
      ),
      'plan-changes' => array(
        'help' => pht(
          'Create or update a revision without requesting a code review.'),
        'conflicts' => array(
          'only'     => pht('%s does not affect revisions.', '--only'),
          'preview'  => pht('%s does not affect revisions.', '--preview'),
        ),
      ),
      'encoding' => array(
        'param' => 'encoding',
        'help' => pht(
          'Attempt to convert non UTF-8 hunks into specified encoding.'),
      ),
      'allow-untracked' => array(
        'help' => pht('Skip checks for untracked files in the working copy.'),
      ),
      'excuse' => array(
        'param' => 'excuse',
        'help' => pht(
          'Provide a prepared in advance excuse for any lints/tests '.
          'shall they fail.'),
      ),
      'less-context' => array(
        'help' => pht(
          "Normally, files are diffed with full context: the entire file is ".
          "sent to Differential so reviewers can 'show more' and see it. If ".
          "you are making changes to very large files with tens of thousands ".
          "of lines, this may not work well. With this flag, a diff will ".
          "be created that has only a few lines of context."),
      ),
      'lintall' => array(
        'help' => pht(
          'Raise all lint warnings, not just those on lines you changed.'),
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'advice' => array(
        'help' => pht(
          'Require excuse for lint advice in addition to lint warnings and '.
          'errors.'),
      ),
      'only-new' => array(
        'param' => 'bool',
        'help' => pht(
          'Display only lint messages not present in the original code.'),
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'apply-patches' => array(
        'help' => pht(
          'Apply patches suggested by lint to the working copy without '.
          'prompting.'),
        'conflicts' => array(
          'never-apply-patches' => true,
        ),
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'never-apply-patches' => array(
        'help' => pht('Never apply patches suggested by lint.'),
        'conflicts' => array(
          'apply-patches' => true,
        ),
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'amend-all' => array(
        'help' => pht(
          'When linting git repositories, amend HEAD with all patches '.
          'suggested by lint without prompting.'),
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'amend-autofixes' => array(
        'help' => pht(
          'When linting git repositories, amend HEAD with autofix '.
          'patches suggested by lint without prompting.'),
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'add-all' => array(
        'short' => 'a',
        'help' => pht(
          'Automatically add all unstaged and uncommitted '.
          'files to the commit.'),
      ),
      'json' => array(
        'help' => pht(
          'Emit machine-readable JSON. EXPERIMENTAL! Probably does not work!'),
      ),
      'no-amend' => array(
        'help' => pht(
          'Never amend commits in the working copy with lint patches.'),
      ),
      'uncommitted' => array(
        'help' => pht('Suppress warning about uncommitted changes.'),
        'supports' => array(
          'hg',
        ),
      ),
      'verbatim' => array(
        'help' => pht(
          'When creating a revision, try to use the working copy commit '.
          'message verbatim, without prompting to edit it. When updating a '.
          'revision, update some fields from the local commit message.'),
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
        'help' => pht('When creating a revision, add reviewers.'),
        'conflicts' => array(
          'only'    => true,
          'preview' => true,
          'update'  => true,
        ),
      ),
      'cc' => array(
        'param' => 'usernames',
        'help' => pht('When creating a revision, add CCs.'),
        'conflicts' => array(
          'only'    => true,
          'preview' => true,
          'update'  => true,
        ),
      ),
      'skip-binaries' => array(
        'help'  => pht('Do not upload binaries (like images).'),
      ),
      'skip-staging' => array(
        'help' => pht('Do not copy changes to the staging area.'),
      ),
      'ignore-unsound-tests' => array(
        'help'  => pht('Ignore unsound test failures without prompting.'),
      ),
      'base' => array(
        'param' => 'rules',
        'help'  => pht('Additional rules for determining base revision.'),
        'nosupport' => array(
          'svn' => pht('Subversion does not use base commits.'),
        ),
        'supports' => array('git', 'hg'),
      ),
      'no-diff' => array(
        'help' => pht(
          'Only run lint and unit tests. Intended for internal use.'),
      ),
      'cache' => array(
        'param' => 'bool',
        'help' => pht(
          '%d to disable lint cache, %d to enable (default).',
          0,
          1),
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'coverage' => array(
        'help' => pht('Always enable coverage information.'),
        'conflicts' => array(
          'no-coverage' => null,
        ),
        'passthru' => array(
          'unit' => true,
        ),
      ),
      'no-coverage' => array(
        'help' => pht('Always disable coverage information.'),
        'passthru' => array(
          'unit' => true,
        ),
      ),
      'browse' => array(
        'help' => pht(
          'After creating a diff or revision, open it in a web browser.'),
      ),
      '*' => 'paths',
      'head' => array(
        'param' => 'commit',
        'help' => pht(
          'Specify the end of the commit range. This disables many '.
          'Arcanist/Phabricator features which depend on having access to '.
          'the working copy.'),
        'supports' => array('git'),
        'nosupport' => array(
          'svn' => pht('Subversion does not support commit ranges.'),
          'hg' => pht('Mercurial does not support %s yet.', '--head'),
        ),
        'conflicts' => array(
          'lintall'   => pht('%s suppresses lint.', '--head'),
          'advice'    => pht('%s suppresses lint.', '--head'),
        ),
      ),
    );

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

    $commit_message = $this->buildCommitMessage();

    $this->dispatchEvent(
      ArcanistEventType::TYPE_DIFF_DIDBUILDMESSAGE,
      array(
        'message' => $commit_message,
      ));

    if (!$this->shouldOnlyCreateDiff()) {
      $revision = $this->buildRevisionFromCommitMessage($commit_message);
    }

    $server = $this->console->getServer();
    $server->setHandler(array($this, 'handleServerMessage'));
    $data = $this->runLintUnit();

    $lint_result = $data['lintResult'];
    $this->unresolvedLint = $data['unresolvedLint'];
    $unit_result = $data['unitResult'];
    $this->testResults = $data['testResults'];

    if ($this->getArgument('nolint')) {
      $this->excuses['lint'] = $this->getSkipExcuse(
        pht('Provide explanation for skipping lint or press Enter to abort:'),
        'lint-excuses');
    }

    if ($this->getArgument('nounit')) {
      $this->excuses['unit'] = $this->getSkipExcuse(
        pht(
          'Provide explanation for skipping unit tests '.
          'or press Enter to abort:'),
        'unit-excuses');
    }

    $changes = $this->generateChanges();
    if (!$changes) {
      throw new ArcanistUsageException(
        pht('There are no changes to generate a diff from!'));
    }

    $diff_spec = array(
      'changes' => mpull($changes, 'toDictionary'),
      'lintStatus' => $this->getLintStatus($lint_result),
      'unitStatus' => $this->getUnitStatus($unit_result),
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

    $this->pushChangesToStagingArea($this->diffID);

    $phid = idx($diff_info, 'phid');
    if ($phid) {
      $this->hitAutotargets = $this->updateAutotargets(
        $phid,
        $unit_result);
    }

    $this->updateLintDiffProperty();
    $this->updateUnitDiffProperty();
    $this->updateLocalDiffProperty();
    $this->updateOntoDiffProperty();
    $this->resolveDiffPropertyUpdates();

    $output_json = $this->getArgument('json');

    if ($this->shouldOnlyCreateDiff()) {
      if (!$output_json) {
        echo phutil_console_format(
          "%s\n        **%s** __%s__\n\n",
          pht('Created a new Differential diff:'),
          pht('Diff URI:'),
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

      if ($this->shouldOpenCreatedObjectsInBrowser()) {
        $this->openURIsInBrowser(array($diff_info['uri']));
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

        echo pht('Updated an existing Differential revision:')."\n";
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
            echo pht('Updating commit message...')."\n";
            $repository_api->amendCommit($revised_message);
          } else {
            echo pht(
              'Commit message was not amended. Amending commit message is '.
              'only supported in git and hg (version 2.2 or newer)');
          }
        }

        echo pht('Created a new Differential revision:')."\n";
      }

      $uri = $result['uri'];
      echo phutil_console_format(
        "        **%s** __%s__\n\n",
        pht('Revision URI:'),
        $uri);

      if ($this->getArgument('plan-changes')) {
        $conduit->callMethodSynchronous(
          'differential.createcomment',
          array(
            'revision_id' => $result['revisionid'],
            'action' => 'rethink',
          ));
        echo pht('Planned changes to the revision.')."\n";
      }

      if ($this->shouldOpenCreatedObjectsInBrowser()) {
        $this->openURIsInBrowser(array($uri));
      }
    }

    echo pht('Included changes:')."\n";
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

    $head_commit = $this->getArgument('head');
    if ($head_commit !== null) {
      $repository_api->setHeadCommit($head_commit);
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
      if (!$this->getArgument('head')) {
        $this->requireCleanWorkingCopy();
      }
    }

    $this->dispatchEvent(
      ArcanistEventType::TYPE_DIFF_DIDCOLLECTCHANGES,
      array());
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
        $remote_corpus = ArcanistCommentRemover::removeComments(
          $remote_corpus);
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
          "%s\n\n%s\n\n",
          pht(
            "The working copy includes changes to '%s' paths. These ".
            "changes will not be included in the diff because SVN can not ".
            "commit 'svn:externals' changes alongside normal changes.",
            'svn:externals'),
          pht(
            "Modified '%s' files:",
            'svn:externals'),
          phutil_console_wrap(implode("\n", $warn_externals), 8));
        $prompt = pht('Generate a diff (with just local changes) anyway?');
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
        fwrite(STDERR, pht('Reading diff from stdin...')."\n");
        $raw_diff = file_get_contents('php://stdin');
      } else if ($this->getArgument('raw-command')) {
        list($raw_diff) = execx('%C', $this->getArgument('raw-command'));
      } else {
        throw new Exception(pht('Unknown raw diff source.'));
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
          $revlist[] = '    '.pht('Revision %s, %s', $baserev, $path);
        }
        $revlist = implode("\n", $revlist);

        foreach ($bases as $path => $baserev) {
          if ($baserev !== $rev) {
            throw new ArcanistUsageException(
              pht(
                "Base revisions of changed paths are mismatched. Update all ".
                "paths to the same base revision before creating a diff: ".
                "\n\n%s",
                $revlist));
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
      $diff = $repository_api->getFullGitDiff(
        $repository_api->getBaseCommit(),
        $repository_api->getHeadCommit());
      if (!strlen($diff)) {
        throw new ArcanistUsageException(
          pht('No changes found. (Did you specify the wrong commit range?)'));
      }
      $changes = $parser->parseDiff($diff);
    } else if ($repository_api instanceof ArcanistMercurialAPI) {
      $diff = $repository_api->getFullMercurialDiff();
      if (!strlen($diff)) {
        throw new ArcanistUsageException(
          pht('No changes found. (Did you specify the wrong commit range?)'));
      }
      $changes = $parser->parseDiff($diff);
    } else {
      throw new Exception(pht('Repository API is not supported.'));
    }

    if (count($changes) > 250) {
      $message = pht(
        'This diff has a very large number of changes (%s). Differential '.
        'works best for changes which will receive detailed human review, '.
        'and not as well for large automated changes or bulk checkins. '.
        'See %s for information about reviewing big checkins. Continue anyway?',
        phutil_count($changes),
        'https://secure.phabricator.com/book/phabricator/article/'.
          'differential_large_changes/');

      if (!phutil_console_confirm($message)) {
        throw new ArcanistUsageException(
          pht('Aborted generation of gigantic diff.'));
      }
    }

    $limit = 1024 * 1024 * 4;
    foreach ($changes as $change) {
      $size = 0;
      foreach ($change->getHunks() as $hunk) {
        $size += strlen($hunk->getCorpus());
      }
      if ($size > $limit) {
        $byte_warning = pht(
          "Diff for '%s' with context is %s bytes in length. ".
          "Generally, source changes should not be this large.",
          $change->getCurrentPath(),
          new PhutilNumber($size));
        if (!$this->getArgument('less-context')) {
          $byte_warning .= ' '.pht(
            "If this file is a huge text file, try using the '%s' flag.",
            '--less-context');
        }
        if ($repository_api instanceof ArcanistSubversionAPI) {
          throw new ArcanistUsageException(
            $byte_warning.' '.
            pht(
              "If the file is not a text file, mark it as binary with:".
              "\n\n  $ %s\n",
              'svn propset svn:mime-type application/octet-stream <filename>'));
        } else {
          $confirm = $byte_warning.' '.pht(
            "If the file is not a text file, you can mark it 'binary'. ".
            "Mark this file as 'binary' and continue?");
          if (phutil_console_confirm($confirm)) {
            $change->convertToBinaryChange($repository_api);
          } else {
            throw new ArcanistUsageException(
              pht('Aborted generation of gigantic diff.'));
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
                    pht('Lookup of encoding in arcanist project failed: %s',
                        $e->getMessage())."\n");
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
                  pht(
                    "Converted a '%s' hunk from '%s' to UTF-8.\n",
                    $name,
                    $try_encoding));
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
        sprintf(
          "%s\n\n%s\n\n    %s\n",
          pht(
            'This diff includes %s file(s) which are not valid UTF-8 (they '.
            'contain invalid byte sequences). You can either stop this '.
            'workflow and fix these files, or continue. If you continue, '.
            'these files will be marked as binary.',
            phutil_count($utf8_problems)),
          pht(
            "You can learn more about how Phabricator handles character ".
            "encodings (and how to configure encoding settings and detect and ".
            "correct encoding problems) by reading 'User Guide: UTF-8 and ".
            "Character Encoding' in the Phabricator documentation."),
          pht(
            '%s AFFECTED FILE(S)',
            phutil_count($utf8_problems)));
      $confirm = pht(
        'Do you want to mark these %s file(s) as binary and continue?',
        phutil_count($utf8_problems));

      echo phutil_console_format(
        "**%s**\n",
        pht('Invalid Content Encoding (Non-UTF8)'));
      echo phutil_console_wrap($utf8_warning);

      $file_list = mpull($utf8_problems, 'getCurrentPath');
      $file_list = '    '.implode("\n    ", $file_list);
      echo $file_list;

      if (!phutil_console_confirm($confirm, $default_no = false)) {
        throw new ArcanistUsageException(pht('Aborted workflow to fix UTF-8.'));
      } else {
        foreach ($utf8_problems as $change) {
          $change->convertToBinaryChange($repository_api);
        }
      }
    }

    $this->uploadFilesForChanges($changes);

    return $changes;
  }

  private function getGitParentLogInfo() {
    $info = array(
      'parent'        => null,
      'base_revision' => null,
      'base_path'     => null,
      'uuid'          => null,
    );

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

    $futures = id(new FutureIterator($futures))
      ->limit(8);
    foreach ($futures as $key => $future) {
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
    if ($this->isRawDiffSource()) {
      return false;
    }

    if ($this->haveUncommittedChanges) {
      return false;
    }

    if ($this->getArgument('no-amend')) {
      return false;
    }

    if ($this->getArgument('head') !== null) {
      return false;
    }

    // Run this last: with --raw or --raw-command, we won't have a repository
    // API.
    if ($this->isHistoryImmutable()) {
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
        $this->isRawDiffSource() ||
        $this->getArgument('head')) {
      return ArcanistLintWorkflow::RESULT_SKIP;
    }

    $repository_api = $this->getRepositoryAPI();

    $this->console->writeOut("%s\n", pht('Linting...'));
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
              pht('Lint issued unresolved advice.'),
              'lint-excuses');
          } else {
            $this->console->writeOut(
              "<bg:green>** %s **</bg> %s\n",
              pht('LINT OKAY'),
              pht('No lint problems.'));
          }
          break;
        case ArcanistLintWorkflow::RESULT_WARNINGS:
          $this->getErrorExcuse(
            'lint',
            pht('Lint issued unresolved warnings.'),
            'lint-excuses');
          break;
        case ArcanistLintWorkflow::RESULT_ERRORS:
          $this->console->writeOut(
            "<bg:red>** %s **</bg> %s\n",
            pht('LINT ERRORS'),
            pht('Lint raised errors!'));
          $this->getErrorExcuse(
            'lint',
            pht('Lint issued unresolved errors!'),
            'lint-excuses');
          break;
      }

      $this->unresolvedLint = array();
      foreach ($lint_workflow->getUnresolvedMessages() as $message) {
        $this->unresolvedLint[] = $message->toDictionary();
      }

      return $lint_result;
    } catch (ArcanistNoEngineException $ex) {
      $this->console->writeOut(
        "%s\n",
        pht('No lint engine configured for this project.'));
    } catch (ArcanistNoEffectException $ex) {
      $this->console->writeOut("%s\n", $ex->getMessage());
    }

    return null;
  }


  /**
   * @task lintunit
   */
  private function runUnit() {
    if ($this->getArgument('nounit') ||
        $this->getArgument('only') ||
        $this->isRawDiffSource() ||
        $this->getArgument('head')) {
      return ArcanistUnitWorkflow::RESULT_SKIP;
    }

    $repository_api = $this->getRepositoryAPI();

    $this->console->writeOut("%s\n", pht('Running unit tests...'));
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
            "<bg:green>** %s **</bg> %s\n",
            pht('UNIT OKAY'),
            pht('No unit test failures.'));
          break;
        case ArcanistUnitWorkflow::RESULT_UNSOUND:
          if ($this->getArgument('ignore-unsound-tests')) {
            echo phutil_console_format(
              "<bg:yellow>** %s **</bg> %s\n",
              pht('UNIT UNSOUND'),
              pht(
                'Unit testing raised errors, but all '.
                'failing tests are unsound.'));
          } else {
            $continue = $this->console->confirm(
              pht(
                'Unit test results included failures, but all failing tests '.
                'are known to be unsound. Ignore unsound test failures?'));
            if (!$continue) {
              throw new ArcanistUserAbortException();
            }
          }
          break;
        case ArcanistUnitWorkflow::RESULT_FAIL:
          $this->console->writeOut(
            "<bg:red>** %s **</bg> %s\n",
            pht('UNIT ERRORS'),
            pht('Unit testing raised errors!'));
          $this->getErrorExcuse(
            'unit',
            pht('Unit test results include failures!'),
            'unit-excuses');
          break;
      }

      $this->testResults = array();
      foreach ($unit_workflow->getTestResults() as $test) {
        $this->testResults[] = $test->toDictionary();
      }

      return $unit_result;
    } catch (ArcanistNoEngineException $ex) {
      $this->console->writeOut(
        "%s\n",
        pht('No unit test engine is configured for this project.'));
    } catch (ArcanistNoEffectException $ex) {
      $this->console->writeOut("%s\n", $ex->getMessage());
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
        'confirm'  => $prompt.' '.pht('Ignore them?'),
      ));
      return;
    }

    $history = $this->getRepositoryAPI()->getScratchFilePath($history);

    $prompt .= ' '.
      pht('Provide explanation to continue or press Enter to abort.');
    $this->console->writeOut("\n\n%s", phutil_console_wrap($prompt));
    $this->console->sendMessage(array(
      'type'    => $type,
      'prompt'  => pht('Explanation:'),
      'history' => $history,
    ));
  }

  public function handleServerMessage(PhutilConsoleMessage $message) {
    $data = $message->getData();

    if ($this->getArgument('excuse')) {
      try {
        phutil_console_require_tty();
      } catch (PhutilConsoleStdinNotInteractiveException $ex) {
        $this->excuses[$data['type']] = $this->getArgument('excuse');
        return null;
      }
    }

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
          pht(
            "There are several revisions which match the working copy:\n\n%s\n".
            "Use '%s' to choose one, or '%s' to create a new revision.",
            $this->renderRevisionList($revisions),
            '--update',
            '--create'));
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
          pht(
            'Parameter to %s must be a Differential Revision number.',
            '--update'));
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
        $preview = id(new PhutilUTF8StringTruncator())
          ->setMaximumGlyphs(64)
          ->truncateString($preview);

        if ($preview) {
          $preview = pht('Message begins:')."\n\n       {$preview}\n\n";
        } else {
          $preview = null;
        }

        echo pht(
          "You have a saved revision message in '%s'.\n%s".
          "You can use this message, or discard it.",
          $where,
          $preview);

        $use = phutil_console_confirm(
          pht('Do you want to use this message?'),
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

      if (!$this->isRawDiffSource()) {
        $message = pht(
          'Included commits in branch %s:',
          $this->getRepositoryAPI()->getBranchName());
      } else {
        $message = pht('Included commits:');
      }
      $included = array_merge(
        array(
          '',
          $message,
          '',
        ),
        $included);
    }

    $issues = array_merge(
      array(
        pht('NEW DIFFERENTIAL REVISION'),
        pht('Describe the changes in this new revision.'),
      ),
      $included,
      array(
        '',
        pht(
          'arc could not identify any existing revision in your working copy.'),
        pht('If you intended to update an existing revision, use:'),
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
        $template .= rtrim('# '.$issue)."\n";
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
        throw new ArcanistUsageException(pht('Template not edited.'));
      }

      $template = ArcanistCommentRemover::removeComments($new_template);

      // With --raw-command, we may not have a repository API.
      if ($this->hasRepositoryAPI()) {
        $repository_api = $this->getRepositoryAPI();
        // special check for whether to amend here. optimizes a common git
        // workflow. we can't do this for mercurial because the mq extension
        // is popular and incompatible with hg commit --amend ; see T2011.
        $should_amend = (count($included_commits) == 1 &&
                         $repository_api instanceof ArcanistGitAPI &&
                         $this->shouldAmend());
      } else {
        $should_amend = false;
      }

      if ($should_amend) {
        $wrote = (rtrim($old_message) != rtrim($template));
        if ($wrote) {
          $repository_api->amendCommit($template);
          $where = pht('commit message');
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
        echo pht('Commit message has errors:')."\n\n";
        $issues = array(pht('Resolve these errors:'));
        foreach ($ex->getParserErrors() as $error) {
          echo phutil_console_wrap("- ".$error."\n", 6);
          $issues[] = '  - '.$error;
        }
        echo "\n";
        echo pht('You must resolve these errors to continue.');
        $again = phutil_console_confirm(
          pht('Do you want to edit the message?'),
          $default_no = false);
        if ($again) {
          // Keep going.
        } else {
          $saved = null;
          if ($wrote) {
            $saved = pht('A copy was saved to %s.', $where);
          }
          throw new ArcanistUsageException(
            pht('Message has unresolved errors.')." {$saved}");
        }
      } catch (Exception $ex) {
        if ($wrote) {
          echo phutil_console_wrap(pht('(Message saved to %s.)', $where)."\n");
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
        pht(
          "Revision '%s' does not exist!",
          $revision_id));
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
      $confirm = pht('You have not specified any reviewers. Continue anyway?');
      if (!phutil_console_confirm($confirm)) {
        throw new ArcanistUsageException(pht('Specify reviewers and retry.'));
      }
    } else {
      $futures['reviewers'] = $this->getConduit()->callMethod(
        'user.query',
        array(
          'phids' => $reviewers,
        ));
    }

    foreach (new FutureIterator($futures) as $key => $future) {
      $result = $future->resolve();
      switch ($key) {
        case 'revision':
          if (empty($result)) {
            throw new ArcanistUsageException(
              pht(
                'There is no revision %s.',
                "D{$revision_id}"));
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
            $confirm = pht(
              'All reviewers are away until %s. Continue anyway?',
              $until);
            if (!phutil_console_confirm($confirm)) {
              throw new ArcanistUsageException(
                pht('Specify available reviewers and retry.'));
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
        pht(
          "When using '%s' to update a revision, specify an update message ".
          "with '%s'. (Normally, we'd launch an editor to ask you for a ".
          "message, but can not do that because stdin is the diff source.)",
          '--raw',
          '--message'));
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

      $template = sprintf(
        "%s\n\n# %s\n#\n# %s\n# %s\n#\n# %s\n#  $ %s\n\n",
        rtrim($comments),
        pht(
          'Updating %s: %s',
          "D{$fields['revisionID']}",
          $fields['title']),
        pht(
          'Enter a brief description of the changes included in this update.'),
        pht('The first line is used as subject, next lines as comment.'),
        pht('If you intended to create a new revision, use:'),
        'arc diff --create');
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
      if ($this->getArgument('create')) {
        unset($result[0]['revisionID']);
      }
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
      $faux_message[] = pht('Reviewers: %s', $this->getArgument('reviewers'));
    }
    if ($this->getArgument('cc')) {
      $faux_message[] = pht('CC: %s', $this->getArgument('cc'));
    }

    if ($faux_message) {
      $faux_message = implode("\n\n", $faux_message);
      $local = array(
        '(Flags)     ' => array(
          'message' => $faux_message,
          'summary' => pht('Command-Line Flags'),
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
        $notes[] = pht(
          'NOTE: commit %s could not be completely parsed:',
          $frev);
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

    if (count($messages) == 1) {
      // If there's only one message, assume this is an amend-based workflow and
      // that using it to prefill doesn't make sense.
      return null;
    }

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
      $text = head(explode("\n", $text));
      $default[] = '  - '.$text."\n";
    }

    return implode('', $default);
  }

  private function loadActiveLocalCommitInfo() {
    $current_diff = $this->getConduit()->callMethodSynchronous(
      'differential.querydiffs',
      array(
        'revisionIDs' => array($this->revisionID),
      ));
    $current_diff = head($current_diff);

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
      $repo_uuid      = $repository_api->getRepositoryUUID();

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
      } else if ($repository_api instanceof ArcanistMercurialAPI) {

        $bookmark = $repository_api->getActiveBookmark();
        $svn_info = $repository_api->getSubversionInfo();
        $repo_uuid = idx($svn_info, 'uuid');
        $base_path = idx($svn_info, 'base_path', $base_path);
        $base_revision = idx($svn_info, 'base_revision', $base_revision);

        // TODO: provide parent info

      }
    }

    $data = array(
      'sourceMachine'             => php_uname('n'),
      'sourcePath'                => $source_path,
      'branch'                    => $branch,
      'bookmark'                  => $bookmark,
      'sourceControlSystem'       => $vcs,
      'sourceControlPath'         => $base_path,
      'sourceControlBaseRevision' => $base_revision,
      'creationMethod'            => 'arc',
    );

    if (!$this->isRawDiffSource()) {
      $repository_phid = $this->getRepositoryPHID();
      if ($repository_phid) {
        $data['repositoryPHID'] = $repository_phid;
      }
    }

    return $data;
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
      $this->updateDiffProperty(
        'arc:lint-excuse',
        json_encode($this->excuses['lint']));
    }

    if (!$this->hitAutotargets) {
      if ($this->unresolvedLint) {
        $this->updateDiffProperty(
          'arc:lint',
          json_encode($this->unresolvedLint));
      }
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

    if (!$this->hitAutotargets) {
      if ($this->testResults) {
        $this->updateDiffProperty('arc:unit', json_encode($this->testResults));
      }
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

  private function updateOntoDiffProperty() {
    $onto = $this->getDiffOntoTargets();

    if (!$onto) {
      return;
    }

    $this->updateDiffProperty('arc:onto', json_encode($onto));
  }

  private function getDiffOntoTargets() {
    if ($this->isRawDiffSource()) {
      return null;
    }

    $api = $this->getRepositoryAPI();

    if (!($api instanceof ArcanistGitAPI)) {
      return null;
    }

    // If we track an upstream branch either directly or indirectly, use that.
    $branch = $api->getBranchName();
    if (strlen($branch)) {
      $upstream_path = $api->getPathToUpstream($branch);
      $remote_branch = $upstream_path->getRemoteBranchName();
      if (strlen($remote_branch)) {
        return array(
          array(
            'type' => 'branch',
            'name' => $remote_branch,
            'kind' => 'upstream',
          ),
        );
      }
    }

    // If "arc.land.onto.default" is configured, use that.
    $config_key = 'arc.land.onto.default';
    $onto = $this->getConfigFromAnySource($config_key);
    if (strlen($onto)) {
      return array(
        array(
          'type' => 'branch',
          'name' => $onto,
          'kind' => 'arc.land.onto.default',
        ),
      );
    }

    return null;
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
    id(new FutureIterator($this->diffPropertyFutures))
      ->resolveAll();
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
      pht(
        "You don't own revision %s '%s'. You can only update revisions ".
        "you own. You can 'Commandeer' this revision from the web ".
        "interface if you want to become the owner.",
        "D{$id}",
        $title));
  }


/* -(  File Uploads  )------------------------------------------------------- */


  private function uploadFilesForChanges(array $changes) {
    assert_instances_of($changes, 'ArcanistDiffChange');

    // Collect all the files we need to upload.

    $need_upload = array();
    foreach ($changes as $key => $change) {
      if ($change->getFileType() != ArcanistDiffChangeType::FILE_BINARY) {
        continue;
      }

      if ($this->getArgument('skip-binaries')) {
        continue;
      }

      $name = basename($change->getCurrentPath());

      $need_upload[] = array(
        'type' => 'old',
        'name' => $name,
        'data' => $change->getOriginalFileData(),
        'change' => $change,
      );

      $need_upload[] = array(
        'type' => 'new',
        'name' => $name,
        'data' => $change->getCurrentFileData(),
        'change' => $change,
      );
    }

    if (!$need_upload) {
      return;
    }

    // Determine mime types and file sizes. Update changes from "binary" to
    // "image" if the file is an image. Set image metadata.

    $type_image = ArcanistDiffChangeType::FILE_IMAGE;
    foreach ($need_upload as $key => $spec) {
      $change = $need_upload[$key]['change'];

      $type = $spec['type'];
      $size = strlen($spec['data']);

      $change->setMetadata("{$type}:file:size", $size);
      if ($spec['data'] === null) {
        // This covers the case where a file was added or removed; we don't
        // need to upload the other half of it (e.g., the old file data for
        // a file which was just added). This is distinct from an empty
        // file, which we do upload.
        unset($need_upload[$key]);
        continue;
      }

      $mime = $this->getFileMimeType($spec['data']);
      if (preg_match('@^image/@', $mime)) {
        $change->setFileType($type_image);
      }

      $change->setMetadata("{$type}:file:mime-type", $mime);
    }

    $uploader = id(new ArcanistFileUploader())
      ->setConduitClient($this->getConduit());

    foreach ($need_upload as $key => $spec) {
      $ref = id(new ArcanistFileDataRef())
        ->setName($spec['name'])
        ->setData($spec['data']);

      $uploader->addFile($ref, $key);
    }

    $files = $uploader->uploadFiles();

    $errors = false;
    foreach ($files as $key => $file) {
      if ($file->getErrors()) {
        unset($files[$key]);
        $errors = true;
        echo pht(
          'Failed to upload binary "%s".',
          $file->getName());
      }
    }

    if ($errors) {
      $prompt = pht('Continue?');
      $ok = phutil_console_confirm($prompt, $default_no = false);
      if (!$ok) {
        throw new ArcanistUsageException(
          pht(
            'Aborted due to file upload failure. You can use %s '.
            'to skip binary uploads.',
            '--skip-binaries'));
      }
    }

    foreach ($files as $key => $file) {
      $spec = $need_upload[$key];
      $phid = $file->getPHID();

      $change = $spec['change'];
      $type = $spec['type'];
      $change->setMetadata("{$type}:binary-phid", $phid);

      echo pht('Uploaded binary data for "%s".', $file->getName())."\n";
    }

    echo pht('Upload complete.')."\n";
  }

  private function getFileMimeType($data) {
    $tmp = new TempFile();
    Filesystem::writeFile($tmp, $data);
    return Filesystem::getMimeType($tmp);
  }

  private function shouldOpenCreatedObjectsInBrowser() {
    return $this->getArgument('browse');
  }

  private function pushChangesToStagingArea($id) {
    if ($this->getArgument('skip-staging')) {
      $this->writeInfo(
        pht('SKIP STAGING'),
        pht('Flag --skip-staging was specified.'));
      return;
    }

    if ($this->isRawDiffSource()) {
      $this->writeInfo(
        pht('SKIP STAGING'),
        pht('Raw changes can not be pushed to a staging area.'));
      return;
    }

    if (!$this->getRepositoryPHID()) {
      $this->writeInfo(
        pht('SKIP STAGING'),
        pht('Unable to determine repository for this change.'));
      return;
    }

    $staging = $this->getRepositoryStagingConfiguration();
    if ($staging === null) {
      $this->writeInfo(
        pht('SKIP STAGING'),
        pht('The server does not support staging areas.'));
      return;
    }

    $supported = idx($staging, 'supported');
    if (!$supported) {
      $this->writeInfo(
        pht('SKIP STAGING'),
        pht('Phabricator does not support staging areas for this repository.'));
      return;
    }

    $staging_uri = idx($staging, 'uri');
    if (!$staging_uri) {
      $this->writeInfo(
        pht('SKIP STAGING'),
        pht('No staging area is configured for this repository.'));
      return;
    }

    $api = $this->getRepositoryAPI();
    if (!($api instanceof ArcanistGitAPI)) {
      $this->writeInfo(
        pht('SKIP STAGING'),
        pht('This client version does not support staging this repository.'));
      return;
    }

    $commit = $api->getHeadCommit();
    $prefix = idx($staging, 'prefix', 'phabricator');

    $base_tag = $prefix.'/base/'.$id;
    $diff_tag = $prefix.'/diff/'.$id;

    $this->writeOkay(
      pht('PUSH STAGING'),
      pht('Pushing changes to staging area...'));

    $push_flags = array();
    if (version_compare($api->getGitVersion(), '1.8.2', '>=')) {
      $push_flags[] = '--no-verify';
    }

    $refs = array();

    // If the base commit is a real commit, we're going to push it. We don't
    // use this, but pushing it to a ref reduces the amount of redundant work
    // that Git does on later pushes by helping it figure out that the remote
    // already has most of the history. See T10509.

    // In the future, we could avoid this push if the staging area is the same
    // as the main repository, or if the staging area is a virtual repository.
    // In these cases, the staging area should automatically have up-to-date
    // refs.
    $base_commit = $api->getSourceControlBaseRevision();
    if ($base_commit !== ArcanistGitAPI::GIT_MAGIC_ROOT_COMMIT) {
      $refs[] = "{$base_commit}:refs/tags/{$base_tag}";
    }

    // We're always going to push the change itself.
    $refs[] = "{$commit}:refs/tags/{$diff_tag}";

    $err = phutil_passthru(
      'git push %Ls -- %s %Ls',
      $push_flags,
      $staging_uri,
      $refs);

    if ($err) {
      $this->writeWarn(
        pht('STAGING FAILED'),
        pht('Unable to push changes to the staging area.'));

      throw new ArcanistUsageException(
        pht(
          'Failed to push changes to staging area. Correct the issue, or '.
          'use --skip-staging to skip this step.'));
    }
  }


  /**
   * Try to upload lint and unit test results into modern Harbormaster build
   * targets.
   *
   * @return bool True if everything was uploaded to build targets.
   */
  private function updateAutotargets($diff_phid, $unit_result) {
    $lint_key = 'arcanist.lint';
    $unit_key = 'arcanist.unit';

    try {
      $result = $this->getConduit()->callMethodSynchronous(
        'harbormaster.queryautotargets',
        array(
          'objectPHID' => $diff_phid,
          'targetKeys' => array(
            $lint_key,
            $unit_key,
          ),
        ));
      $targets = idx($result, 'targetMap', array());
    } catch (Exception $ex) {
      return false;
    }

    $futures = array();

    $lint_target = idx($targets, $lint_key);
    if ($lint_target) {
      $lint = nonempty($this->unresolvedLint, array());
      foreach ($lint as $key => $message) {
        $lint[$key] = $this->getModernLintDictionary($message);
      }

      // Consider this target to have failed if there are any unresolved
      // errors or warnings.
      $type = 'pass';
      foreach ($lint as $message) {
        switch (idx($message, 'severity')) {
          case ArcanistLintSeverity::SEVERITY_WARNING:
          case ArcanistLintSeverity::SEVERITY_ERROR:
            $type = 'fail';
            break;
        }
      }

      $futures[] = $this->getConduit()->callMethod(
        'harbormaster.sendmessage',
        array(
          'buildTargetPHID' => $lint_target,
          'lint' => array_values($lint),
          'type' => $type,
        ));
    }

    $unit_target = idx($targets, $unit_key);
    if ($unit_target) {
      $unit = nonempty($this->testResults, array());
      foreach ($unit as $key => $message) {
        $unit[$key] = $this->getModernUnitDictionary($message);
      }

      $type = ArcanistUnitWorkflow::getHarbormasterTypeFromResult($unit_result);

      $futures[] = $this->getConduit()->callMethod(
        'harbormaster.sendmessage',
        array(
          'buildTargetPHID' => $unit_target,
          'unit' => array_values($unit),
          'type' => $type,
        ));
    }

    try {
      foreach (new FutureIterator($futures) as $future) {
        $future->resolve();
      }
      return true;
    } catch (Exception $ex) {
      // TODO: Eventually, we should expect these to succeed if we get this
      // far, but just log errors for now.
      phlog($ex);
      return false;
    }
  }

}
