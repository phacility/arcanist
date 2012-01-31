<?php

/*
 * Copyright 2012 Facebook, Inc.
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
 * Executes "svn commit" once a revision has been "Accepted".
 *
 * @group workflow
 */
final class ArcanistCommitWorkflow extends ArcanistBaseWorkflow {

  private $revisionID;

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **commit** [--revision __revision_id__] [--show]
          Supports: svn
          Commit a revision which has been accepted by a reviewer.
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

  public function getRevisionID() {
    return $this->revisionID;
  }

  public function getArguments() {
    return array(
      'show' => array(
        'help' =>
          "Show the command which would be issued, but do not actually ".
          "commit anything."
      ),
      'revision' => array(
        'param' => 'revision_id',
        'help' =>
          "Commit a specific revision. If you do not specify a revision, ".
          "arc will look for committable revisions.",
      )
    );
  }

  public function run() {
    $repository_api = $this->getRepositoryAPI();

    if (!($repository_api instanceof ArcanistSubversionAPI)) {
      throw new ArcanistUsageException(
        "'arc commit' is only supported under svn.");
    }


    $revision_id = $this->normalizeRevisionID($this->getArgument('revision'));
    if (!$revision_id) {
      $revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
        $this->getConduit(),
        array(
          'authors' => array($this->getUserPHID()),
          'status'  => 'status-accepted',
        ));

      if (count($revisions) == 0) {
        throw new ArcanistUsageException(
          "Unable to identify the revision in the working copy. Use ".
          "'--revision <revision_id>' to select a revision.");
      } else if (count($revisions) > 1) {
        throw new ArcanistUsageException(
          "More than one revision exists in the working copy:\n\n".
          $this->renderRevisionList($revisions)."\n".
          "Use '--revision <revision_id>' to select a revision.");
      }

    } else {
      $revisions = $this->getConduit()->callMethodSynchronous(
        'differential.query',
        array(
          'ids' => array($revision_id),
        ));

      if (count($revisions) == 0) {
        throw new ArcanistUsageException(
          "Revision 'D{$revision_id}' does not exist.");
      }
    }

    $revision = head($revisions);
    $this->revisionID = $revision['id'];
    $revision_id = $revision['id'];

    $this->runSanityChecks($revision);

    $message = $this->getConduit()->callMethodSynchronous(
      'differential.getcommitmessage',
      array(
        'revision_id' => $revision_id,
        'edit'        => false,
      ));

    $event = new PhutilEvent(
      ArcanistEventType::TYPE_COMMIT_WILLCOMMITSVN,
      array(
        'message'   => $message,
        'workflow'  => $this,
      )
    );
    PhutilEventEngine::dispatchEvent($event);

    $message = $event->getValue('message');

    if ($this->getArgument('show')) {
      echo $message;
      return 0;
    }

    $revision_title = $revision['title'];
    echo "Committing 'D{$revision_id}: {$revision_title}'...\n";

    $files = $this->getCommitFileList($revision);

    $files = implode(' ', array_map('escapeshellarg', $files));
    $message = escapeshellarg($message);
    $root = escapeshellarg($repository_api->getPath());

    $lang = $this->getSVNLangEnvVar();

    // Specify LANG explicitly so that UTF-8 commit messages don't break
    // subversion.
    $command = "(cd {$root} && LANG={$lang} svn commit {$files} -m {$message})";

    $err = phutil_passthru('%C', $command);

    if ($err) {
      throw new Exception("Executing 'svn commit' failed!");
    }

    $mark_workflow = $this->buildChildWorkflow(
      'mark-committed',
      array(
        '--finalize',
        $revision_id,
      ));
    $mark_workflow->run();

    return $err;
  }

  protected function getCommitFileList(array $revision) {
    $repository_api = $this->getRepositoryAPI();
    $revision_id = $revision['id'];

    $commit_paths = $this->getConduit()->callMethodSynchronous(
      'differential.getcommitpaths',
      array(
        'revision_id' => $revision_id,
      ));
    $dir_paths = array();
    foreach ($commit_paths as $path) {
      $path = dirname($path);
      while ($path != '.') {
        $dir_paths[$path] = true;
        $path = dirname($path);
      }
    }
    $commit_paths = array_fill_keys($commit_paths, true);

    $status = $repository_api->getSVNStatus();

    $modified_but_not_included = array();
    foreach ($status as $path => $mask) {
      if (!empty($dir_paths[$path])) {
        $commit_paths[$path] = true;
      }
      if (!empty($commit_paths[$path])) {
        continue;
      }
      foreach ($commit_paths as $will_commit => $ignored) {
        if (Filesystem::isDescendant($path, $will_commit)) {
          throw new ArcanistUsageException(
            "This commit includes the directory '{$will_commit}', but ".
            "it contains a modified path ('{$path}') which is NOT included ".
            "in the commit. Subversion can not handle this operation and ".
            "will commit the path anyway. You need to sort out the working ".
            "copy changes to '{$path}' before you may proceed with the ".
            "commit.");
        }
      }
      $modified_but_not_included[] = $path;
    }

    if ($modified_but_not_included) {
      if (count($modified_but_not_included) == 1) {
        $prefix = "A locally modified path is not included in this revision:";
        $prompt = "It will NOT be committed. Commit this revision anyway?";
      } else {
        $prefix = "Locally modified paths are not included in this revision:";
        $prompt = "They will NOT be committed. Commit this revision anyway?";
      }
      $this->promptFileWarning($prefix, $prompt, $modified_but_not_included);
    }

    $do_not_exist = array();
    foreach ($commit_paths as $path => $ignored) {
      $disk_path = $repository_api->getPath($path);
      if (file_exists($disk_path)) {
        continue;
      }
      if (is_link($disk_path)) {
        continue;
      }
      if (idx($status, $path) & ArcanistRepositoryAPI::FLAG_DELETED) {
        continue;
      }
      $do_not_exist[] = $path;
      unset($commit_paths[$path]);
    }

    if ($do_not_exist) {
      if (count($do_not_exist) == 1) {
        $prefix = "Revision includes changes to a path that does not exist:";
        $prompt = "Commit this revision anyway?";
      } else {
        $prefix = "Revision includes changes to paths that do not exist:";
        $prompt = "Commit this revision anyway?";
      }
      $this->promptFileWarning($prefix, $prompt, $do_not_exist);
    }

    $files = array_keys($commit_paths);

    if (empty($files)) {
      throw new ArcanistUsageException(
        "There is nothing left to commit. None of the modified paths exist.");
    }

    return $files;
  }

  protected function promptFileWarning($prefix, $prompt, array $paths) {
    echo $prefix."\n\n";
    foreach ($paths as $path) {
      echo "    ".$path."\n";
    }
    if (!phutil_console_confirm($prompt)) {
      throw new ArcanistUserAbortException();
    }
  }

  protected function getSupportedRevisionControlSystems() {
    return array('svn');
  }

  /**
   * On some systems, we need to specify "en_US.UTF-8" instead of "en_US.utf8",
   * and SVN spews some bewildering warnings if we don't:
   *
   *   svn: warning: cannot set LC_CTYPE locale
   *   svn: warning: environment variable LANG is en_US.utf8
   *   svn: warning: please check that your locale name is correct
   *
   * For example, is happens on my 10.6.7 machine with Subversion 1.6.15.
   */
  private function getSVNLangEnvVar() {
    $locale = 'en_US.utf8';
    try {
      list($locales) = execx('locale -a');
      $locales = explode("\n", trim($locales));
      $locales = array_fill_keys($locales, true);
      if (isset($locales['en_US.UTF-8'])) {
        $locale = 'en_US.UTF-8';
      }
    } catch (Exception $ex) {
      // Ignore.
    }
    return $locale;
  }

  private function runSanityChecks(array $revision) {
    $repository_api = $this->getRepositoryAPI();
    $revision_id = $revision['id'];
    $revision_title = $revision['title'];

    $confirm = array();

    if ($revision['status'] != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      $confirm[] =
        "Revision 'D{$revision_id}: {$revision_title}' has not been accepted. ".
        "Commit this revision anyway?";
    }

    if ($revision['authorPHID'] != $this->getUserPHID()) {
      $confirm[] =
        "You are not the author of 'D{$revision_id}: {$revision_title}'. ".
        "Commit this revision anyway?";
    }

    $revision_source = $revision['sourcePath'];
    $current_source = $repository_api->getPath();
    if ($revision_source != $current_source) {
      $confirm[] =
        "Revision 'D{$revision_id}: {$revision_title}' was generated from ".
        "'{$revision_source}', but current working copy root is ".
        "'{$current_source}'. Commit this revision anyway?";
    }

    foreach ($confirm as $thing) {
      if (!phutil_console_confirm($thing)) {
        throw new ArcanistUserAbortException();
      }
    }
  }


}
