<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class ArcanistDiffWorkflow extends ArcanistBaseWorkflow {

  private $hasWarnedExternals = false;

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **diff** [__paths__] (svn)
      **diff** [__commit__] (git)
          Supports: git, svn
          Generate a Differential diff or revision from local changes.

          Under git, you can specify a commit (like __HEAD^^^__ or __master__)
          and Differential will generate a diff against the merge base of that
          commit and HEAD. If you omit the commit, the default is __HEAD^__.

          Under svn, you can choose to include only some of the modified files
          in the working copy in the diff by specifying their paths. If you
          omit paths, all changes are included in the diff.

EOTEXT
      );
  }
  public function requiresWorkingCopy() {
    return true;
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function getArguments() {
    return array(
      'message' => array(
        'short'       => 'm',
        'supports'    => array(
          'git',
        ),
        'nosupport'   => array(
          'svn' => 'Edit revisions via the web interface when using SVN.',
        ),
        'param'       => 'message',
        'help' =>
          "When updating a revision under git, use the specified message ".
          "instead of prompting.",
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
        ),
      ),
      'only' => array(
        'help' =>
          "Only generate a diff, without running lint, unit tests, or other ".
          "auxiliary steps.",
        'conflicts' => array(
          'preview'   => null,
          'message'   => '--only does not affect revisions.',
          'edit'      => '--only does not affect revisions.',
          'lintall'   => '--only suppresses lint.',
          'advice'    => '--only suppresses lint.',
          'nounit'    => '--only implies --nounit.',
          'nolint'    => '--only implies --nolint.',
        ),
      ),
      'preview' => array(
        'supports'    => array(
          'git',
        ),
        'nosupport'   => array(
          'svn' => 'Revisions are never created directly when using SVN.',
        ),
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
      'allow-untracked' => array(
        'help' =>
          "Skip checks for untracked files in the working copy.",
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
      ),
      'advice' => array(
        'help' =>
          "Raise lint advice in addition to lint warnings and errors.",
      ),
      '*' => 'paths',
    );
  }

  public function run() {
    $repository_api = $this->getRepositoryAPI();

    if ($this->getArgument('less-context')) {
      $repository_api->setDiffLinesOfContext(3);
    }

    $conduit = $this->getConduit();
    $this->requireCleanWorkingCopy();

    $parent = null;

    $base_revision = $repository_api->getSourceControlBaseRevision();
    $base_path     = $repository_api->getSourceControlPath();
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
    }

    $paths = $this->generateAffectedPaths();

    $lint_result = $this->runLint($paths);
    $unit_result = $this->runUnit($paths);

    $changes = $this->generateChanges();
    if (!$changes) {
      throw new ArcanistUsageException(
        "There are no changes to generate a diff from!");
    }

    $change_list = array();
    foreach ($changes as $change) {
      $change_list[] = $change->toDictionary();
    }

    if ($lint_result === ArcanistLintWorkflow::RESULT_OKAY) {
      $lint = 'okay';
    } else if ($lint_result === ArcanistLintWorkflow::RESULT_WARNINGS) {
      $lint = 'fail';
    } else if ($lint_result === ArcanistLintWorkflow::RESULT_SKIP) {
      $lint = 'skip';
    } else {
      $lint = 'none';
    }

    if ($unit_result === ArcanistUnitWorkflow::RESULT_OKAY) {
      $unit = 'okay';
    } else if ($unit_result === ArcanistUnitWorkflow::RESULT_UNSOUND ||
               $unit_result === ArcanistUnitWorkflow::RESULT_FAIL) {
      $unit = 'fail';
    } else if ($unit_result === ArcanistUnitWorkflow::RESULT_SKIP) {
      $unit = 'skip';
    } else {
      $unit = 'none';
    }

    $diff = array(
      'changes'                   => $change_list,
      'sourceMachine'             => php_uname('n'),
      'sourcePath'                => $repository_api->getPath(),
      'branch'                    => $repository_api->getBranchName(),
      'sourceControlSystem'       =>
        $repository_api->getSourceControlSystemName(),
      'sourceControlPath'         => $base_path,
      'sourceControlBaseRevision' => $base_revision,
      'parentRevisionID'          => $parent,
      'lintStatus'                => $lint,
      'unitStatus'                => $unit,
    );

    $diff_info = $conduit->callMethodSynchronous(
      'differential.creatediff',
      $diff);

    if ($this->shouldOnlyCreateDiff()) {
      echo phutil_console_format(
        "Created a new Differential diff:\n".
        "        **Diff URI:** __%s__\n\n",
        $diff_info['uri']);
    } else {
      $message = $this->getGitCommitMessage();

      $revision = array(
        'diffid' => $diff_info['diffid'],
        'fields' => $message->getFields(),
      );

      if ($message->getRevisionID()) {

        $update_message = $this->getUpdateMessage();

        $revision['id'] = $message->getRevisionID();
        $revision['message'] = $update_message;
        $future = $conduit->callMethod(
          'differential.updaterevision',
          $revision);
        $result = $future->resolve();
        echo "Updated an existing Differential revision:\n";
      } else {
        $revision['user'] = $this->getUserGUID();
        $future = $conduit->callMethod(
          'differential.createrevision',
          $revision);
        $result = $future->resolve();
        echo "Updating commit message to include Differential revision ID...\n";
        $repository_api->amendGitHeadCommit(
          $message->getRawCorpus().
          "\n\n".
          "Differential Revision: ".$result['revisionid']."\n");
        echo "Created a new Differential revision:\n";
      }

      $uri = $result['uri'];
      echo phutil_console_format(
        "        **Revision URI:** __%s__\n\n",
        $uri);
    }

    echo "Included changes:\n";
    foreach ($changes as $change) {
      echo '  '.$change->renderTextSummary()."\n";
    }

    return 0;
  }

  protected function shouldOnlyCreateDiff() {
    $repository_api = $this->getRepositoryAPI();
    if ($repository_api instanceof ArcanistSubversionAPI) {
      return true;
    }
    return $this->getArgument('preview') ||
           $this->getArgument('only');
  }

  protected function findRevisionInformation() {
    return array(null, null);
  }

  private function generateAffectedPaths() {
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
          '        '.phutil_console_wrap(implode("\n", $warn_externals), 8));
        $prompt = "Generate a diff (with just local changes) anyway?";
        if (!phutil_console_confirm($prompt)) {
          throw new ArcanistUserAbortException();
        } else {
          $this->hasWarnedExternals = true;
        }
      }

    } else {
      $this->parseGitRelativeCommit(
        $repository_api,
        $this->getArgument('paths', array()));
      $paths = $repository_api->getWorkingCopyStatus();
    }
    return $paths;
  }


  protected function generateChanges() {
    $repository_api = $this->getRepositoryAPI();

    $parser = new ArcanistDiffParser();
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
        if ($bases[$path] == 0) {
          unset($bases[$path]);
        }
      }

      if ($bases) {
        // We have at least one path which isn't new.
        $repository_info = $repository_api->getSVNInfo('/');
        $bases['.'] = $repository_info['Revision'];
        if ($bases['.']) {
          $rev = $bases['.'];
          foreach ($bases as $path => $baserev) {
            if ($baserev !== $rev) {
              $revlist = array();
              foreach ($bases as $path => $baserev) {
                $revlist[] = "    Revision {$baserev}, {$path}";
              }
              $revlist = implode("\n", $revlist);
              throw new ArcanistUsageException(
                "Base revisions of changed paths are mismatched. Update all ".
                "paths to the same base revision before creating a diff: ".
                "\n\n".
                $revlist);
            }
          }
        }
      }

      $changes = $parser->parseSubversionDiff(
        $repository_api,
        $paths);
    } else if ($repository_api instanceof ArcanistGitAPI) {

      $diff = $repository_api->getFullGitDiff();
      if (!strlen($diff)) {
        list($base, $tip) = $repository_api->getCommitRange();
        if ($tip == 'HEAD') {
          if (preg_match('/\^+HEAD/', $base)) {
            $more = 'Did you mean HEAD^ instead of ^HEAD?';
          } else {
            $more = 'Did you specify the wrong relative commit?';
          }
        } else {
          $more = 'Did you specify the wrong commit range?';
        }
        throw new ArcanistUsageException("No changes found. ({$more})");
      }
      $changes = $parser->parseDiff($diff);

    } else {
      throw new Exception("Repository API is not supported.");
    }

    if (count($changes) > 250) {
      $count = number_format(count($changes));
      $message =
        "This diff has a very large number of changes ({$count}). ".
        "Differential works best for changes which will receive detailed ".
        "human review, and not as well for large automated changes or ".
        "bulk checkins. Continue anyway?";
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
          "length. Generally, source changes should not be this large. If ".
          "this file is a huge text file, try using the '--less-context' flag.";
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

    return $changes;
  }

  /**
   * Retrieve the git message in HEAD if it isn't a primary template message.
   */
  private function getGitUpdateMessage() {
    $repository_api = $this->getRepositoryAPI();

    $parser = new ArcanistDiffParser($repository_api);
    $commit_messages = $repository_api->getGitCommitLog();
    $commit_messages = $parser->parseDiff($commit_messages);

    $head = reset($commit_messages);
    $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
      $head->getMetadata('message'));
    if ($message->getRevisionID()) {
      return null;
    }

    return trim($message->getRawCorpus());
  }

  private function getGitCommitMessage() {
    $conduit = $this->getConduit();
    $repository_api = $this->getRepositoryAPI();

    $parser = new ArcanistDiffParser($repository_api);
    $commit_messages = $repository_api->getGitCommitLog();
    $commit_messages = $parser->parseDiff($commit_messages);

    $problems = array();
    $parsed = array();
    foreach ($commit_messages as $key => $change) {
      $problems[$key] = array();

      try {
        $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
          $change->getMetadata('message'));

        $message->pullDataFromConduit($conduit);

        $parsed[$key] = $message;
      } catch (ArcanistDifferentialCommitMessageParserException $ex) {
        $problems[$key][] = $ex;
        continue;
      }

      // TODO: Move this all behind Conduit.
      if (!$message->getRevisionID()) {
        if ($message->getFieldValue('reviewedByGUIDs')) {
          $problems[$key][] = new ArcanistUsageException(
            "When creating or updating a revision, use the 'Reviewers:' ".
            "field to specify reviewers, not 'Reviewed By:'. After the ".
            "revision is accepted, run 'arc amend' to update the commit ".
            "message.");
        }

        if (!$message->getFieldValue('title')) {
          $problems[$key][] = new ArcanistUsageException(
            "Commit message has no title. You must provide a title for this ".
            "revision.");
        }

        if (!$message->getFieldValue('testPlan')) {
          $problems[$key][] = new ArcanistUsageException(
            "Commit message has no 'Test Plan:'. You must provide a test ".
            "plan.");
        }
      }
    }

    $blessed = null;
    $revision_id = -1;
    foreach ($problems as $key => $problem_list) {
      if ($problem_list) {
        continue;
      }
      if ($revision_id === -1) {
        $revision_id = $parsed[$key]->getRevisionID();
        $blessed = $parsed[$key];
      } else {
        throw new ArcanistUsageException(
          "Changes in the specified commit range include more than one ".
          "commit with a valid template commit message. This is ambiguous, ".
          "your commit range should contain only one template commit ".
          "message. Alternatively, use --diff-only to ignore commit ".
          "messages.");
      }
    }

    if ($revision_id === -1) {
      $all_problems = call_user_func_array('array_merge', $problems);
      $desc = implode("\n", mpull($all_problems, 'getMessage'));
      if (count($problems) > 1) {
        throw new ArcanistUsageException(
          "All changes between the specified commits have template parsing ".
          "problems:\n\n".$desc."\n\nIf you only want to create a diff ".
          "(not a revision), use --diff-only to ignore commit messages.");
      } else if (count($problems) == 1) {
        throw new ArcanistUsageException(
          "Commit message is not properly formatted:\n\n".$desc."\n\n".
          "You should use the standard git commit template to provide a ".
          "commit message. If you only want to create a diff (not a ".
          "revision), use --diff-only to ignore commit messages.");
      }
    }

    if ($blessed) {
      if (!$blessed->getFieldValue('reviewerGUIDs')) {
        $message = "You have not specified any reviewers. Continue anyway?";
        if (!phutil_console_confirm($message)) {
          throw new ArcanistUsageException('Specify reviewers and retry.');
        }
      }
    }

    return $blessed;
  }

  private function getGitParentLogInfo() {
    $info = array(
      'parent'        => null,
      'base_revision' => null,
      'base_path'     => null,
    );

    $conduit = $this->getConduit();
    $repository_api = $this->getRepositoryAPI();

    $parser = new ArcanistDiffParser($repository_api);
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
        if ($info['parent'] && $info['base_revision']) {
          break;
        }
      } catch (ArcanistDifferentialCommitMessageParserException $ex) {
        // Ignore.
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

  private function getUpdateMessage() {
    $comments = $this->getArgument('message');
    if (!strlen($comments)) {

      // When updating a revision using git without specifying '--message', try
      // to prefill with the message in HEAD if it isn't a template message. The
      // idea is that if you do:
      //
      //  $ git commit -a -m 'fix some junk'
      //  $ arc diff
      //
      // ...you shouldn't have to retype the update message.
      $repository_api = $this->getRepositoryAPI();
      if ($repository_api instanceof ArcanistGitAPI) {
        $comments = $this->getGitUpdateMessage();
      }

      $template =
        $comments.
        "\n\n".
        "# Enter a brief description of the changes included in this update.".
        "\n";
      $comments = id(new PhutilInteractiveEditor($template))
        ->setName('differential-update-comments')
        ->editInteractively();
      $comments = preg_replace('/^\s*#.*$/m', '', $comments);
    }

    return $comments;
  }

  private function runLint($paths) {
    if ($this->getArgument('nolint') ||
        $this->getArgument('only')) {
      return ArcanistLintWorkflow::RESULT_SKIP;
    }

    $repository_api = $this->getRepositoryAPI();

    echo "Linting...\n";
    try {
      $argv = array();
      if ($this->getArgument('lintall')) {
        $argv[] = '--lintall';
      }
      if ($this->getArgument('advice')) {
        $argv[] = '--advice';
      }
      if ($repository_api instanceof ArcanistSubversionAPI) {
        $argv = array_merge($argv, array_keys($paths));
      } else {
        $argv[] = $repository_api->getRelativeCommit();
      }
      $lint_workflow = $this->buildChildWorkflow('lint', $argv);
      $lint_result   = $lint_workflow->run();

      switch ($lint_result) {
        case ArcanistLintWorkflow::RESULT_OKAY:
          echo phutil_console_format(
            "<bg:green>** LINT OKAY **</bg> No lint problems.\n");
          break;
        case ArcanistLintWorkflow::RESULT_WARNINGS:
          $continue = phutil_console_confirm(
            "Lint issued unresolved warnings. Ignore them?");
          if (!$continue) {
            throw new ArcanistUserAbortException();
          }
          break;
        case ArcanistLintWorkflow::RESULT_ERRORS:
          echo phutil_console_format(
            "<bg:red>** LINT ERRORS **</bg> Lint raised errors!\n");
          throw new ArcanistUsageException(
            "Resolve lint errors or run with --nolint.");
          break;
      }

      return $lint_result;
    } catch (ArcanistNoEngineException $ex) {
      echo "No lint engine configured for this project.\n";
    } catch (ArcanistNoEffectException $ex) {
      echo "No paths to lint.\n";
    }

    return null;
  }

  private function runUnit($paths) {
    if ($this->getArgument('nounit') ||
        $this->getArgument('only')) {
      return ArcanistUnitWorkflow::RESULT_SKIP;
    }

    $repository_api = $this->getRepositoryAPI();

    echo "Running unit tests...\n";
    try {
      $argv = array();
      if ($repository_api instanceof ArcanistSubversionAPI) {
        $argv = array_merge($argv, array_keys($paths));
      }
      $unit_workflow = $this->buildChildWorkflow('unit', $argv);
      $unit_result   = $unit_workflow->run();
      switch ($unit_result) {
        case ArcanistUnitWorkflow::RESULT_OKAY:
          echo phutil_console_format(
            "<bg:green>** UNIT OKAY **</bg> No unit test failures.\n");
          break;
        case ArcanistUnitWorkflow::RESULT_UNSOUND:
          $continue = phutil_console_confirm(
            "Unit test results included failures, but all failing tests ".
            "are known to be unsound. Ignore unsound test failures?");
          if (!$continue) {
            throw new ArcanistUserAbortException();
          }
          break;
        case ArcanistUnitWorkflow::RESULT_FAIL:
          echo phutil_console_format(
            "<bg:red>** UNIT ERRORS **</bg> Unit testing raised errors!\n");
          throw new ArcanistUsageException(
            "Resolve unit test errors or run with --nounit.");
          break;
      }

      return $unit_result;
    } catch (ArcanistNoEngineException $ex) {
      echo "No unit test engine is configured for this project.\n";
    } catch (ArcanistNoEffectException $ex) {
      echo "No tests to run.\n";
    }

    return null;
  }

}
