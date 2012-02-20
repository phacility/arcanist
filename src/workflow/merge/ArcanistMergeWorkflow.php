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
 * Merges a branch using "git merge" or "hg merge", using a template commit
 * message from Differential.
 *
 * @group workflow
 */
final class ArcanistMergeWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **merge** [__branch__] [--revision __revision_id__] [--show]
          Supports: hg
          Execute a "hg merge --rev <branch>" of a reviewed branch, but give the
          merge commit a useful commit message with information from
          Differential.

          Tthis operates like "hg merge" (default) or "hg merge --rev <branch>"
          and should be executed from the branch you want to merge __from__,
          just like "hg merge". It will also effect an "hg commit" with a rich
          commit message if possible.

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
      'show' => array(
        'help' =>
          "Don't merge, just show the commit message."
      ),
      'revision' => array(
        'param' => 'revision',
        'help' =>
          "Use the message for a specific revision. If 'arc' can't figure ".
          "out which revision you want, you can tell it explicitly.",
      ),
      '*' => 'branch',
    );
  }

  public function run() {
    $repository_api = $this->getRepositoryAPI();

    if ($repository_api instanceof ArcanistGitAPI) {
      throw new ArcanistUsageException(
        "'arc merge' no longer supports git. Use ".
        "'arc land --keep-branch --hold --merge <feature_branch>' instead.");
    }

    $this->writeStatusMessage(
      phutil_console_format(
        "**WARNING:** 'arc merge' is new and experimental.\n"));


    if (!$repository_api->supportsLocalBranchMerge()) {
      $name = $repository_api->getSourceControlSystemName();
      throw new ArcanistUsageException(
        "This source control system ('{$name}') does not support 'arc merge'.");
    }

    if ($repository_api->getUncommittedChanges()) {
      throw new ArcanistUsageException(
        "You have uncommitted changes in this working copy. Commit ".
        "(or revert) them before proceeding.");
    }

    $branch = $this->getArgument('branch');
    if (count($branch) > 1) {
      throw new ArcanistUsageException("Specify only one branch to merge.");
    } else {
      $branch = head($branch);
    }

    $conduit = $this->getConduit();

    $revisions = $conduit->callMethodSynchronous(
      'differential.find',
      array(
        'guids' => array($this->getUserPHID()),
        'query' => 'committable',
      ));

    // TODO: Make an effort to guess which revision the user means here. Branch
    // name is a very strong heuristic but Conduit doesn't make it easy to get
    // right now. We now also have "commits:local" after D857. Between these
    // we should be able to get this right automatically in essentially every
    // reasonable case.

    try {
      $revision = $this->chooseRevision(
        $revisions,
        $this->getArgument('revision'),
        'Which revision do you want to merge?');
      $revision_id = $revision->getID();
    } catch (ArcanistChooseInvalidRevisionException $ex) {
      throw new ArcanistUsageException(
        "You can only merge Differential revisions which have been accepted.");
    } catch (ArcanistChooseNoRevisionsException $ex) {
      throw new ArcanistUsageException(
        "You have no accepted Differential revisions.");
    }

    $message = $conduit->callMethodSynchronous(
      'differential.getcommitmessage',
      array(
        'revision_id' => $revision_id,
        'edit'        => false,
      ));

    if ($this->getArgument('show')) {
      echo $message."\n";
    } else {
      $repository_api->performLocalBranchMerge($branch, $message);
      echo "Merged '{$branch}'.\n";

      $mark_workflow = $this->buildChildWorkflow(
        'mark-committed',
        array(
          '--finalize',
          $revision_id,
        ));
      $mark_workflow->run();
    }

    return 0;
  }

  protected function getSupportedRevisionControlSystems() {
    return array('hg');
  }

}
