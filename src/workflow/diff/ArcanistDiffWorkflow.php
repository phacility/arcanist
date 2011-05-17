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

/**
 * Sends changes from your working copy to Differential for code review.
 *
 * @group workflow
 */
class ArcanistDiffWorkflow extends ArcanistBaseWorkflow {

  private $hasWarnedExternals = false;
  private $unresolvedLint;
  private $unresolvedTests;
  private $diffID;

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

  public function getDiffID() {
    return $this->diffID;
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
          'apply-patches' => '--nolint suppresses lint.',
          'never-apply-patches' => '--nolint suppresses lint.',
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
          'apply-patches' => '--only suppresses lint.',
          'never-apply-patches' => '--only suppresses lint.',
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
        'passthru' => array(
          'lint' => true,
        ),
      ),
      'advice' => array(
        'help' =>
          "Raise lint advice in addition to lint warnings and errors.",
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

    $paths = $this->generateAffectedPaths();

    // Do this before we start linting or running unit tests so we can detect
    // things like a missing test plan or invalid reviewers immediately.
    if ($this->shouldOnlyCreateDiff()) {
      $commit_message = null;
    } else {
      $commit_message = $this->getGitCommitMessage();
    }

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
    } else if ($lint_result === ArcanistLintWorkflow::RESULT_ERRORS) {
      $lint = 'fail';
    } else if ($lint_result === ArcanistLintWorkflow::RESULT_WARNINGS) {
      $lint = 'warn';
    } else if ($lint_result === ArcanistLintWorkflow::RESULT_SKIP) {
      $lint = 'skip';
    } else {
      $lint = 'none';
    }

    if ($unit_result === ArcanistUnitWorkflow::RESULT_OKAY) {
      $unit = 'okay';
    } else if ($unit_result === ArcanistUnitWorkflow::RESULT_FAIL) {
      $unit = 'fail';
    } else if ($unit_result === ArcanistUnitWorkflow::RESULT_UNSOUND) {
      $unit = 'warn';
    } else if ($unit_result === ArcanistUnitWorkflow::RESULT_SKIP) {
      $unit = 'skip';
    } else {
      $unit = 'none';
    }

    // NOTE: This has to happen after generateChanges(), since it may overwrite
    // the SVN effective base revision.
    $base_revision = $repository_api->getSourceControlBaseRevision();
    $base_path     = $repository_api->getSourceControlPath();
    $repo_uuid     = null;
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
    } else {
      $repo_uuid = $repository_api->getRepositorySVNUUID();
    }

    $working_copy = $this->getWorkingCopy();

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

      'repositoryUUID'            => $repo_uuid,
      'creationMethod'            => 'arc',
      'arcanistProject'           => $working_copy->getProjectID(),
      'authorPHID'                => $this->getUserGUID(),
    );

    $diff_info = $conduit->callMethodSynchronous(
      'differential.creatediff',
      $diff);

    if ($this->unresolvedLint) {
      $data = array();
      foreach ($this->unresolvedLint as $message) {
        $data[] = array(
          'path'        => $message->getPath(),
          'line'        => $message->getLine(),
          'char'        => $message->getChar(),
          'code'        => $message->getCode(),
          'severity'    => $message->getSeverity(),
          'name'        => $message->getName(),
          'description' => $message->getDescription(),
        );
      }
      $conduit->callMethodSynchronous(
        'differential.setdiffproperty',
        array(
          'diff_id' => $diff_info['diffid'],
          'name'    => 'arc:lint',
          'data'    => json_encode($data),
        ));
    }

    if ($this->unresolvedTests) {
      $data = array();
      foreach ($this->unresolvedTests as $test) {
        $data[] = array(
          'name'      => $test->getName(),
          'result'    => $test->getResult(),
          'userdata'  => $test->getUserData(),
        );
      }
      $conduit->callMethodSynchronous(
        'differential.setdiffproperty',
        array(
          'diff_id' => $diff_info['diffid'],
          'name'    => 'arc:unit',
          'data'    => json_encode($data),
        ));
    }

    if ($this->shouldOnlyCreateDiff()) {
      echo phutil_console_format(
        "Created a new Differential diff:\n".
        "        **Diff URI:** __%s__\n\n",
        $diff_info['uri']);
    } else {
      $message = $commit_message;

      $revision = array(
        'diffid' => $diff_info['diffid'],
        'fields' => $message->getFields(),
      );

      if ($message->getRevisionID()) {
        // TODO: This is silly -- we're getting a text corpus from the server
        // and then sending it right back to be parsed. This should be a
        // single call.
        $remote_corpus = $conduit->callMethodSynchronous(
          'differential.getcommitmessage',
          array(
            'revision_id' => $message->getRevisionID(),
            'edit' => true,
            'fields' => array(),
          ));
        $remote_message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
          $remote_corpus);
        $remote_message->pullDataFromConduit($conduit);

        // TODO: We should throw here if you deleted the 'testPlan'.

        $sync = array('title', 'summary', 'testPlan');
        foreach ($sync as $field) {
          $local = $message->getFieldValue($field);
          $remote_message->setFieldValue($field, $local);
        }

        $should_edit = $this->getArgument('edit');

/*

  TODO: This is a complicated mess. We need to move to storing a checksum
  of the non-auto-sync fields as they existed at original diff time and using
  changes from that to detect user edits, not comparison of the client and
  server values since they diverge without user edits (because of Herald
  and explicit server-side user changes).

        if (!$should_edit) {
          $local_sum = $message->getChecksum();
          $remote_sum = $remote_message->getChecksum();
          if ($local_sum != $remote_sum) {
            $prompt =
              "You have made local changes to your commit message. Arcanist ".
              "ignores most local changes. Instead, use the '--edit' flag to ".
              "edit revision information. Edit revision information now?";
            $should_edit = phutil_console_confirm(
              $prompt,
              $default_no = false);
          }
        }
*/

        $revision['fields'] = $remote_message->getFields();

        if ($should_edit) {
          $updated_corpus = $conduit->callMethodSynchronous(
            'differential.getcommitmessage',
            array(
              'revision_id' => $message->getRevisionID(),
              'edit' => true,
              'fields' => $message->getFields(),
            ));
          $new_text = id(new PhutilInteractiveEditor($updated_corpus))
            ->setName('differential-edit-revision-info')
            ->editInteractively();
          $new_message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
            $new_text);
          $new_message->pullDataFromConduit($conduit);
/*
          TODO: restore these checks


          try { // <<< this try goes right after the edit

            if (!$new_message->getTitle()) {
              throw new UsageException(
                "You can not remove the Revision title.");
            }
            if (!$new_message->getTestPlan()) {
              throw new UsageException(
                "You can not remove the 'Test Plan'.");
            }
            if ($new_message->getRevisionID() != $revision->getID()) {
              throw new UsageException(
                "You changed or deleted the Differential revision ID! Why ".
                "would you do that?!");
            }

          } catch (Exception $ex) {
            $ii = 0;
            do {
              $name = $ii
                ? 'differential-message-'.$ii.'.txt'
                : 'differential-message.txt';
              if (!file_exists($name)) {
                break;
              }
              ++$ii;
            } while(true);
            require_module_lazy('resource/filesystem');
            Filesystem::writeFile($name, $new_text);
            echo "Exception! Message was saved to '{$name}'.\n";
            throw $ex;
          }
*/



          $revision['fields'] = $new_message->getFields();
        }

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

    $this->diffID = $diff_info['diffid'];

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

    foreach ($paths as $path => $mask) {
      if ($mask & ArcanistRepositoryAPI::FLAG_UNTRACKED) {
        unset($paths[$path]);
      }
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
        $rev = reset($bases);
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


    // TODO: Ideally, we should do this later, after validating commit message
    // fields (i.e., test plan), in case there are large/slow file upload steps
    // involved.
    foreach ($changes as $change) {
      if ($change->getFileType() != ArcanistDiffChangeType::FILE_BINARY) {
        continue;
      }

      $path = $change->getCurrentPath();
      $old_file = $repository_api->getOriginalFileData($path);
      $new_file = $repository_api->getCurrentFileData($path);

      $old_dict = $this->uploadFile($old_file, basename($path), 'old binary');
      $new_dict = $this->uploadFile($new_file, basename($path), 'new binary');

      if ($old_dict['guid']) {
        $change->setMetadata('old:binary-guid', $old_dict['guid']);
      }
      if ($new_dict['guid']) {
        $change->setMetadata('new:binary-guid', $new_dict['guid']);
      }

      $change->setMetadata('old:file:size',      strlen($old_file));
      $change->setMetadata('new:file:size',      strlen($new_file));
      $change->setMetadata('old:file:mime-type', $old_dict['mime']);
      $change->setMetadata('new:file:mime-type', $new_dict['mime']);

      if (preg_match('@^image/@', $new_dict['mime'])) {
        $change->setFileType(ArcanistDiffChangeType::FILE_IMAGE);
      }
    }


    return $changes;
  }

  private function uploadFile($data, $name, $desc) {
    $result = array(
      'guid' => null,
      'mime' => null,
    );

    if (!strlen($data)) {
      return $result;
    }

    $future = new ExecFuture('file -ib -');
    $future->write($data);
    list($mime_type) = $future->resolvex();

    $mime_type = trim($mime_type);
    if (strpos($mime_type, ',') !== false) {
      // TODO: This is kind of silly, but 'file -ib' goes crazy on executables.
      $mime_type = reset(explode(',', $mime_type));
    }


    $result['mime'] = $mime_type;

    // TODO: Make this configurable.
    $bin_limit = 1024 * 1024; // 1 MB limit
    if (strlen($data) > $bin_limit) {
      return $result;
    }

    $bytes = strlen($data);
    echo "Uploading {$desc} '{$name}' ({$mime_type}, {$bytes} bytes)...\n";

    $guid = $this->getConduit()->callMethodSynchronous(
      'file.upload',
      array(
        'data_base64' => base64_encode($data),
        'name'        => $name,
      ));

    $result['guid'] = $guid;
    return $result;
  }

  /**
   * Retrieve the git message in HEAD if it isn't a primary template message.
   */
  private function getGitUpdateMessage() {
    $repository_api = $this->getRepositoryAPI();

    $parser = new ArcanistDiffParser();
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

    $parser = new ArcanistDiffParser();
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
          "message. Alternatively, use --preview to ignore commit ".
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
          "(not a revision), use --preview to ignore commit messages.");
      } else if (count($problems) == 1) {
        throw new ArcanistUsageException(
          "Commit message is not properly formatted:\n\n".$desc."\n\n".
          "You should use the standard git commit template to provide a ".
          "commit message. If you only want to create a diff (not a ".
          "revision), use --preview to ignore commit messages.");
      }
    }

    if ($blessed) {
      if (!$blessed->getFieldValue('reviewerGUIDs') &&
          !$blessed->getFieldValue('reviewerPHIDs')) {
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
      'uuid'          => null,
    );

    $conduit = $this->getConduit();
    $repository_api = $this->getRepositoryAPI();

    $parser = new ArcanistDiffParser();
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

      $comments = rtrim($comments);
      if (!strlen($comments)) {
        throw new ArcanistUserAbortException();
      }
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
      $argv = $this->getPassthruArgumentsAsArgv('lint');
      if ($repository_api instanceof ArcanistSubversionAPI) {
        $argv = array_merge($argv, array_keys($paths));
      } else {
        $argv[] = $repository_api->getRelativeCommit();
      }
      $lint_workflow = $this->buildChildWorkflow('lint', $argv);

      $lint_workflow->setShouldAmendChanges(true);

      $lint_result = $lint_workflow->run();

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
          $continue = phutil_console_confirm(
            "Lint issued unresolved errors! Ignore lint errors?");
          if (!$continue) {
            throw new ArcanistUserAbortException();
          }
          break;
      }

      $this->unresolvedLint = $lint_workflow->getUnresolvedMessages();

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
      $argv = $this->getPassthruArgumentsAsArgv('unit');
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
          $continue = phutil_console_confirm(
            "Unit test results include failures! Ignore test failures?");
          if (!$continue) {
            throw new ArcanistUserAbortException();
          }
          break;
      }

      $this->unresolvedTests = $unit_workflow->getUnresolvedTests();

      return $unit_result;
    } catch (ArcanistNoEngineException $ex) {
      echo "No unit test engine is configured for this project.\n";
    } catch (ArcanistNoEffectException $ex) {
      echo "No tests to run.\n";
    }

    return null;
  }

}
