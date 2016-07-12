<?php

/**
 * Synchronizes commit messages from Differential.
 */
final class ArcanistAmendWorkflow extends ArcanistWorkflow {

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
          pht(
            "You may only run '%s' in a git or hg ".
            "(version 2.2 or newer) working copy.",
            'arc amend'));
      }

      if ($this->isHistoryImmutable()) {
        throw new ArcanistUsageException(
          pht(
            'This project is marked as adhering to a conservative history '.
            'mutability doctrine (having an immutable local history), which '.
            'precludes amending commit messages.'));
      }

      if ($repository_api->getUncommittedChanges()) {
        throw new ArcanistUsageException(
          pht(
            'You have uncommitted changes in this branch. Stage and commit '.
            '(or revert) them before proceeding.'));
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
        'status'    => 'status-any',
      ));
    $in_working_copy = ipull($in_working_copy, null, 'id');

    if (!$revision_id) {
      if (count($in_working_copy) == 0) {
        throw new ArcanistUsageException(
          pht(
            "No revision specified with '%s', and no revisions found ".
            "in the working copy. Use '%s' to specify which revision ".
            "you want to amend.",
            '--revision',
            '--revision <id>'));
      } else if (count($in_working_copy) > 1) {
        $message = pht(
          "More than one revision was found in the working copy:\n%s\n".
          "Use '%s' to specify which revision you want to amend.",
          $this->renderRevisionList($in_working_copy),
          '--revision <id>');
        throw new ArcanistUsageException($message);
      } else {
        $revision_id = key($in_working_copy);
        $revision = $in_working_copy[$revision_id];
        if ($revision['authorPHID'] != $this->getUserPHID()) {
          $other_author = $this->getConduit()->callMethodSynchronous(
            'user.query',
            array(
              'phids' => array($revision['authorPHID']),
            ));
          $other_author = ipull($other_author, 'userName', 'phid');
          $other_author = $other_author[$revision['authorPHID']];
          $rev_title = $revision['title'];
          $ok = phutil_console_confirm(
            pht(
              "You are amending the revision '%s' but you are not ".
              "the author. Amend this revision by %s?",
              "D{$revision_id}: {$rev_title}",
              $other_author));
          if (!$ok) {
            throw new ArcanistUserAbortException();
          }
        }
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
          pht("Revision '%s' does not exist.", "D{$revision_id}")
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
        pht("Failed to lookup information for '%s'!", "D{$revision_id}"));
    }
    $revision = head($revision);
    $revision_title = $revision['title'];

    if (!$is_show) {
      if ($revision_id && empty($in_working_copy[$revision_id])) {
        $ok = phutil_console_confirm(
          pht(
            "The revision '%s' does not appear to be in the working copy. Are ".
            "you sure you want to amend HEAD with the commit message for '%s'?",
            "D{$revision_id}",
            "D{$revision_id}: {$revision_title}"));
        if (!$ok) {
          throw new ArcanistUserAbortException();
        }
      }
    }

    if ($is_show) {
      echo $message."\n";
    } else {
      echo pht(
        "Amending commit message to reflect revision %s.\n",
        phutil_console_format(
          '**D%d: %s**',
          $revision_id,
          $revision_title));

      $repository_api->amendCommit($message);
    }

    return 0;
  }

  public function getSupportedRevisionControlSystems() {
    return array('git', 'hg');
  }

}
