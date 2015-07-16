<?php

/**
 * Executes "svn commit" once a revision has been "Accepted".
 */
final class ArcanistCommitWorkflow extends ArcanistWorkflow {

  private $revisionID;

  public function getWorkflowName() {
    return 'commit';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **commit** [--revision __revision_id__] [--show]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
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
        'help' => pht(
          'Show the command which would be issued, but do not actually '.
          'commit anything.'),
      ),
      'revision' => array(
        'param' => 'revision_id',
        'help' => pht(
          'Commit a specific revision. If you do not specify a revision, '.
          'arc will look for committable revisions.'),
      ),
    );
  }

  public function run() {
    $repository_api = $this->getRepositoryAPI();

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
          pht(
            "Unable to identify the revision in the working copy. Use ".
            "'%s' to select a revision.",
            '--revision <revision_id>'));
      } else if (count($revisions) > 1) {
        throw new ArcanistUsageException(
          pht(
            "More than one revision exists in the working copy:\n\n%s\n".
            "Use '%s' to select a revision.",
            $this->renderRevisionList($revisions),
            '--revision <revision_id>'));
      }

    } else {
      $revisions = $this->getConduit()->callMethodSynchronous(
        'differential.query',
        array(
          'ids' => array($revision_id),
        ));

      if (count($revisions) == 0) {
        throw new ArcanistUsageException(
          pht(
            "Revision '%s' does not exist.",
            "D{$revision_id}"));
      }
    }

    $revision = head($revisions);
    $this->revisionID = $revision['id'];
    $revision_id = $revision['id'];

    $is_show = $this->getArgument('show');

    if (!$is_show) {
      $this->runSanityChecks($revision);
    }

    $message = $this->getConduit()->callMethodSynchronous(
      'differential.getcommitmessage',
      array(
        'revision_id' => $revision_id,
        'edit'        => false,
      ));

    $event = $this->dispatchEvent(
      ArcanistEventType::TYPE_COMMIT_WILLCOMMITSVN,
      array(
        'message'   => $message,
      ));

    $message = $event->getValue('message');

    if ($is_show) {
      echo $message."\n";
      return 0;
    }

    $revision_title = $revision['title'];
    echo pht(
      "Committing '%s: %s'...\n",
      "D{$revision_id}",
      $revision_title);

    $files = $this->getCommitFileList($revision);

    $tmp_file = new TempFile();
    Filesystem::writeFile($tmp_file, $message);

    $command = csprintf(
      'svn commit %Ls --encoding utf-8 -F %s',
      $files,
      $tmp_file);

    // make sure to specify LANG on non-windows systems to suppress any fancy
    // warnings; see @{method:getSVNLangEnvVar}.
    if (!phutil_is_windows()) {
      $command = csprintf('LANG=%C %C', $this->getSVNLangEnvVar(), $command);
    }

    chdir($repository_api->getPath());

    $err = phutil_passthru('%C', $command);
    if ($err) {
      throw new Exception(pht("Executing '%s' failed!", 'svn commit'));
    }

    $this->askForRepositoryUpdate();

    $mark_workflow = $this->buildChildWorkflow(
      'close-revision',
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
            pht(
              "This commit includes the directory '%s', but it contains a ".
              "modified path ('%s') which is NOT included in the commit. ".
              "Subversion can not handle this operation and will commit the ".
              "path anyway. You need to sort out the working copy changes to ".
              "'%s' before you may proceed with the commit.",
              $will_commit,
              $path,
              $path));
        }
      }
      $modified_but_not_included[] = $path;
    }

    if ($modified_but_not_included) {
      $prefix = pht(
        '%s locally modified path(s) are not included in this revision:',
        new PhutilNumber(count($modified_but_not_included)));
      $prompt = pht(
        'These %s path(s) will NOT be committed. Commit this revision anyway?',
        new PhutilNumber(count($modified_but_not_included)));
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
      $prefix = pht(
        'Revision includes changes to %s path(s) that do not exist:',
        new PhutilNumber(count($do_not_exist)));
      $prompt = pht('Commit this revision anyway?');
      $this->promptFileWarning($prefix, $prompt, $do_not_exist);
    }

    $files = array_keys($commit_paths);
    $files = ArcanistSubversionAPI::escapeFileNamesForSVN($files);

    if (empty($files)) {
      throw new ArcanistUsageException(
        pht(
          'There is nothing left to commit. '.
          'None of the modified paths exist.'));
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

  public function getSupportedRevisionControlSystems() {
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
   * For example, it happens on epriestley's Mac (10.6.7) with
   * Subversion 1.6.15.
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
      $confirm[] = pht(
        "Revision '%s: %s' has not been accepted. Commit this revision anyway?",
        "D{$revision_id}",
        $revision_title);
    }

    if ($revision['authorPHID'] != $this->getUserPHID()) {
      $confirm[] = pht(
        "You are not the author of '%s: %s'. Commit this revision anyway?",
        "D{$revision_id}",
        $revision_title);
    }

    $revision_source = idx($revision, 'branch');
    $current_source = $repository_api->getBranchName();
    if ($revision_source != $current_source) {
      $confirm[] = pht(
        "Revision '%s: %s' was generated from '%s', but current working ".
        "copy root is '%s'. Commit this revision anyway?",
        "D{$revision_id}",
        $revision_title,
        $revision_source,
        $current_source);
    }

    foreach ($confirm as $thing) {
      if (!phutil_console_confirm($thing)) {
        throw new ArcanistUserAbortException();
      }
    }
  }

}
