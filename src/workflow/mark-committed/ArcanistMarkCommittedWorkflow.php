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
 * Explicitly marks Differential revisions as "Committed".
 *
 * @group workflow
 */
final class ArcanistMarkCommittedWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **mark-committed** __revision__
          Supports: git, svn
          Manually mark a revision as committed. You should not normally need
          to do this; arc commit (svn), arc amend (git), arc merge (git, hg) or
          repository tracking on the master remote repository should do it for
          you. However, if these mechanisms have failed for some reason you can
          use this command to manually change a revision status from "Accepted"
          to "Committed".
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'finalize' => array(
        'help' =>
          "Mark committed only if the repository is untracked and the ".
          "revision is accepted. Continue even if the mark can't happen. This ".
          "is a soft version of 'mark-committed' used by other workflows.",
      ),
      'quiet' => array(
        'help' =>  'Do not print a success message.',
      ),
      '*' => 'revision',
    );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    // NOTE: Technically we only use this to generate the right message at
    // the end, and you can even get the wrong message (e.g., if you run
    // "arc mark-committed D123" from a git repository, but D123 is an SVN
    // revision). We could be smarter about this, but it's just display fluff.
    return true;
  }

  public function run() {
    $is_finalize = $this->getArgument('finalize');

    $conduit = $this->getConduit();

    $revision_list = $this->getArgument('revision', array());
    if (!$revision_list) {
      throw new ArcanistUsageException(
        "mark-committed requires a revision number.");
    }
    if (count($revision_list) != 1) {
      throw new ArcanistUsageException(
        "mark-committed requires exactly one revision.");
    }
    $revision_id = reset($revision_list);
    $revision_id = $this->normalizeRevisionID($revision_id);

    $revision = null;
    try {
      $revision = $conduit->callMethodSynchronous(
        'differential.getrevision',
        array(
          'revision_id' => $revision_id,
        )
      );
    } catch (Exception $ex) {
      if (!$is_finalize) {
        throw new ArcanistUsageException(
          "Revision D{$revision_id} does not exist."
        );
      }
    }

    if (!$is_finalize && $revision['statusName'] != 'Accepted') {
      throw new ArcanistUsageException(
        "Revision D{$revision_id} is not committable. You can only mark ".
        "revisions which have been 'accepted' as committed."
      );
    }

    if ($revision) {
      if (!$is_finalize && $revision['authorPHID'] != $this->getUserPHID()) {
        $prompt = "You are not the author of revision D{$revision_id}, ".
          'are you sure you want to mark it committed?';
        if (!phutil_console_confirm($prompt)) {
          throw new ArcanistUserAbortException();
        }
      }

      $actually_mark = true;
      if ($is_finalize) {
        $project_info = $conduit->callMethodSynchronous(
          'arcanist.projectinfo',
          array(
            'name' => $this->getWorkingCopy()->getProjectID(),
          ));
        if ($project_info['tracked'] || $revision['statusName'] != 'Accepted') {
          $actually_mark = false;
        }
      }
      if ($actually_mark) {
        $revision_name = $revision['title'];

        echo "Marking revision D{$revision_id} '{$revision_name}' ".
             "committed...\n";

        $conduit->callMethodSynchronous(
          'differential.markcommitted',
          array(
            'revision_id' => $revision_id,
          ));
      }
    }

    $status = $revision['statusName'];
    if ($status == 'Accepted' || $status == 'Committed') {
      // If this has already been attached to commits, don't show the
      // "you can push this commit" message since we know it's been committed
      // already.
      $is_finalized = empty($revision['commits']);
    } else {
      $is_finalized = false;
    }

    if (!$this->getArgument('quiet')) {
      if ($is_finalized) {
        $message = $this->getRepositoryAPI()->getFinalizedRevisionMessage();
        echo phutil_console_wrap($message)."\n";
      } else {
        echo "Done.\n";
      }
    }

    return 0;
  }
}
