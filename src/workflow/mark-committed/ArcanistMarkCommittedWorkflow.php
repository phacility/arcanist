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

class ArcanistMarkCommittedWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **mark-committed** __revision__
          Supports: git, svn
          Manually mark a revision as committed. You should not normally need
          to do this; arc commit (svn), arc amend (git) or commit hooks in the
          master remote repository should do it for you. However, if these
          mechanisms have failed for some reason you can use this command to
          manually change a revision status from "accepted" to "committed".
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'revision',
    );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function run() {

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

    $revision_data = $conduit->callMethodSynchronous(
      'differential.find',
      array(
        'query' => 'committable',
        'guids' => array(
          $this->getUserGUID(),
        ),
      ));

    try {
      $revision_id = reset($revision_list);
      $revision_id = $this->normalizeRevisionID($revision_id);
      $revision = $this->chooseRevision(
        $revision_data,
        $revision_id);
    } catch (ArcanistChooseInvalidRevisionException $ex) {
      throw new ArcanistUsageException(
        "Revision D{$revision_id} is not committable. You can only mark ".
        "revisions which have been 'accepted' as committed.");
    }

    $revision_id = $revision->getID();
    $revision_name = $revision->getName();

    echo "Marking revision D{$revision_id} '{$revision_name}' committed...\n";

    $conduit->callMethodSynchronous(
      'differential.markcommitted',
      array(
        'revision_id' => $revision_id,
      ));

    echo "Done.\n";

    return 0;
  }
}
