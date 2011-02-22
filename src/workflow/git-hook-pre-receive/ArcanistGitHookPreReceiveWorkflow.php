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
 * Installable as a git pre-receive hook.
 *
 * @group workflow
 */
class ArcanistGitHookPreReceiveWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **git-hook-pre-receive**
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

    if (!$working_copy->getConfig('remote_hooks_installed')) {
      echo phutil_console_wrap(
        "\n".
        "NOTE: Arcanist is installed as a git pre-receive hook in the git ".
        "remote you are pushing to, but the project's '.arcconfig' does not ".
        "have the 'remote_hooks_installed' flag set. Until you set the flag, ".
        "some code will run needlessly in both the local and remote, and ".
        "revisions will be marked 'committed' in Differential when they are ".
        "amended rather than when they are actually pushed to the remote ".
        "origin.".
        "\n\n");
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
        'differential.markcommitted',
        array(
          'revision_id' => $revision_id,
        ));
    }

    Futures($futures)->resolveAll();

    return 0;
  }
}
