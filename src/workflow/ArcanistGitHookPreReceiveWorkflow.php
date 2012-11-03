<?php

/**
 * Installable as a git pre-receive hook.
 *
 * @group workflow
 */
final class ArcanistGitHookPreReceiveWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'git-hook-pre-receive';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **git-hook-pre-receive**
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git
          You can install this as a git pre-receive hook.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
    );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function shouldShellComplete() {
    return false;
  }

  public function run() {
    $working_copy = $this->getWorkingCopy();
    if (!$working_copy->getProjectID()) {
      throw new ArcanistUsageException(
        "You have installed a git pre-receive hook in a remote without an ".
        ".arcconfig.");
    }

    // Git repositories have special rules in pre-receive hooks. We need to
    // construct the API against the .git directory instead of the project
    // root or commands don't work properly.
    $repository_api = ArcanistGitAPI::newHookAPI($_SERVER['PWD']);

    $root = $working_copy->getProjectRoot();

    $parser = new ArcanistDiffParser();

    $mark_revisions = array();

    $stdin = file_get_contents('php://stdin');
    $commits = array_filter(explode("\n", $stdin));
    foreach ($commits as $commit) {
      list($old_ref, $new_ref, $refname) = explode(' ', $commit);

      list($log) = execx(
        '(cd %s && git log -n1 %s)',
        $repository_api->getPath(),
        $new_ref);
      $message_log = reset($parser->parseDiff($log));
      $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
        $message_log->getMetadata('message'));

      $revision_id = $message->getRevisionID();
      if ($revision_id) {
        $mark_revisions[] = $revision_id;
      }

      // TODO: Do commit message junk.

      $info = $repository_api->getPreReceiveHookStatus($old_ref, $new_ref);
      $paths = ipull($info, 'mask');
      $frefs = ipull($info, 'ref');
      $data  = array();
      foreach ($paths as $path => $mask) {
        list($stdout) = execx(
          '(cd %s && git cat-file blob %s)',
          $repository_api->getPath(),
          $frefs[$path]);
        $data[$path] = $stdout;
      }

      // TODO: Do commit content junk.

      $commit_name = $new_ref;
      if ($revision_id) {
        $commit_name = 'D'.$revision_id.' ('.$commit_name.')';
      }

      echo "[arc pre-receive] {$commit_name} OK...\n";
    }

    $conduit = $this->getConduit();

    $futures = array();
    foreach ($mark_revisions as $revision_id) {
      $futures[] = $conduit->callMethod(
        'differential.close',
        array(
          'revisionID' => $revision_id,
        ));
    }

    Futures($futures)->resolveAll();

    return 0;
  }
}
