<?php

/**
 * Synchronizes commit messages from Differential.
 */
final class ArcanistAmendWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'amend';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **amend** [--revision __revision_id__] [--show]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, hg
          Amend the working copy, synchronizing the local commit message from
          Differential.

          Supported in Mercurial 2.2 and newer.
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
        'help' => pht(
          'Show the amended commit message, without modifying the '.
          'working copy.'),
      ),
      'revision' => array(
        'param' => 'revision_id',
        'help' => pht(
          'Use the message from a specific revision. If you do not specify '.
          'a revision, arc will guess which revision is in the working '.
          'copy.'),
      ),
    );
  }

  public function run() {
    $is_show = $this->getArgument('show');

    $repository_api = $this->getRepositoryAPI();
    if (!$is_show) {
      if (!$repository_api->supportsAmend()) {
        throw new ArcanistUsageException(
          "You may only run 'arc amend' in a git or hg (version ".
          "2.2 or newer) working copy.");
      }

      if ($this->isHistoryImmutable()) {
        throw new ArcanistUsageException(
          "This project is marked as adhering to a conservative history ".
          "mutability doctrine (having an immutable local history), which ".
          "precludes amending commit messages.");
      }

      if ($repository_api->getUncommittedChanges()) {
        throw new ArcanistUsageException(
          "You have uncommitted changes in this branch. Stage and commit (or ".
          "revert) them before proceeding.");
      }
    }

    $revision_id = null;
    if ($this->getArgument('revision')) {
      $revision_id = $this->normalizeRevisionID($this->getArgument('revision'));
    }

    $repository_api->setBaseCommitArgumentRules('arc:this');
    $in_working_copy = $repository_api->loadWorkingCopyDifferentialRevisions(
      $this->getConduit(),
      array(
        'authors'   => array($this->getUserPHID()),
        'status'    => 'status-any',
      ));
    $in_working_copy = ipull($in_working_copy, null, 'id');

    if (!$revision_id) {
      if (count($in_working_copy) == 0) {
        throw new ArcanistUsageException(
          "No revision specified with '--revision', and no revisions found ".
          "in the working copy. Use '--revision <id>' to specify which ".
          "revision you want to amend.");
      } else if (count($in_working_copy) > 1) {
        $message = "More than one revision was found in the working copy:\n".
          $this->renderRevisionList($in_working_copy)."\n".
          "Use '--revision <id>' to specify which revision you want to ".
          "amend.";
        throw new ArcanistUsageException($message);
      } else {
        $revision_id = key($in_working_copy);
      }
    }

    $conduit = $this->getConduit();
    try {
      $message = $conduit->callMethodSynchronous(
        'differential.getcommitmessage',
        array(
          'revision_id' => $revision_id,
          'edit'        => false,
        ));
    } catch (ConduitClientException $ex) {
      if (strpos($ex->getMessage(), 'ERR_NOT_FOUND') === false) {
        throw $ex;
      } else {
        throw new ArcanistUsageException(
          "Revision D{$revision_id} does not exist."
        );
      }
    }

    $revision = $conduit->callMethodSynchronous(
      'differential.query',
      array(
        'ids' => array($revision_id),
      ));
    if (empty($revision)) {
      throw new Exception(
        "Failed to lookup information for 'D{$revision_id}'!");
    }
    $revision = head($revision);
    $revision_title = $revision['title'];

    if (!$is_show) {
      if ($revision_id && empty($in_working_copy[$revision_id])) {
        $ok = phutil_console_confirm(
          "The revision 'D{$revision_id}' does not appear to be in the ".
          "working copy. Are you sure you want to amend HEAD with the ".
          "commit message for 'D{$revision_id}: {$revision_title}'?");
        if (!$ok) {
          throw new ArcanistUserAbortException();
        }
      }
    }

    if ($is_show) {
      echo $message."\n";
    } else {
      echo phutil_console_format(
        "Amending commit message to reflect revision **%s**.\n",
        "D{$revision_id}: {$revision_title}");

      $repository_api->amendCommit($message);
    }

    return 0;
  }

  protected function getSupportedRevisionControlSystems() {
    return array('git', 'hg');
  }

}
