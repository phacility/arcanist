<?php

/**
 * Explicitly closes Differential revisions.
 */
final class ArcanistCloseRevisionWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'close-revision';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **close-revision** [__options__] __revision__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, hg, svn
          Close a revision which has been committed (svn) or pushed (git, hg).
          You should not normally need to do this: arc commit (svn), arc amend
          (git, hg), arc land (git, hg), or repository tracking on the master
          remote repository should do it for you. However, if these mechanisms
          have failed for some reason you can use this command to manually
          change a revision status from "Accepted" to "Closed".
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'finalize' => array(
        'help' => pht(
          "Close only if the repository is untracked and the revision is ".
          "accepted. Continue even if the close can't happen. This is a soft ".
          "version of 'close-revision' used by other workflows."),
      ),
      'quiet' => array(
        'help' => pht('Do not print a success message.'),
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
    // "arc close-revision D123" from a git repository, but D123 is an SVN
    // revision). We could be smarter about this, but it's just display fluff.
    return true;
  }

  public function run() {
    $is_finalize = $this->getArgument('finalize');

    $conduit = $this->getConduit();

    $revision_list = $this->getArgument('revision', array());
    if (!$revision_list) {
      throw new ArcanistUsageException(
        pht(
          '%s requires a revision number.',
          'close-revision'));
    }
    if (count($revision_list) != 1) {
      throw new ArcanistUsageException(
        pht(
          '%s requires exactly one revision.',
          'close-revision'));
    }
    $revision_id = reset($revision_list);
    $revision_id = $this->normalizeRevisionID($revision_id);

    $revisions = $conduit->callMethodSynchronous(
      'differential.query',
      array(
        'ids' => array($revision_id),
      ));
    $revision = head($revisions);

    $object_name = "D{$revision_id}";

    if (!$revision && !$is_finalize) {
      throw new ArcanistUsageException(
        pht(
          'Revision %s does not exist.',
          $object_name));
    }

    $status_accepted = ArcanistDifferentialRevisionStatus::ACCEPTED;
    $status_closed   = ArcanistDifferentialRevisionStatus::CLOSED;

    if (!$is_finalize && $revision['status'] != $status_accepted) {
      throw new ArcanistUsageException(
        pht(
          "Revision %s can not be closed. You can only close ".
          "revisions which have been 'accepted'.",
          $object_name));
    }

    if ($revision) {
      $revision_display = sprintf(
        '%s %s',
        $object_name,
        $revision['title']);

      if (!$is_finalize && $revision['authorPHID'] != $this->getUserPHID()) {
        $prompt = pht(
          'You are not the author of revision "%s", '.
          'are you sure you want to close it?',
          $object_name);
        if (!phutil_console_confirm($prompt)) {
          throw new ArcanistUserAbortException();
        }
      }

      $actually_close = true;
      if ($is_finalize) {
        if ($this->getRepositoryPHID()) {
          $actually_close = false;
        } else if ($revision['status'] != $status_accepted) {
          // See T13458. The server doesn't permit a transition to "Closed"
          // over the API if the revision is not "Accepted". If we won't be
          // able to close the revision, skip the attempt and print a
          // message.

          $this->writeWarn(
            pht('OPEN REVISION'),
            pht(
              'Revision "%s" is not in state "Accepted", so it will '.
              'be left open.',
              $object_name));

          $actually_close = false;
        }
      }

      if ($actually_close) {
        $this->writeInfo(
          pht('CLOSE'),
          pht(
            'Closing revision "%s"...',
            $revision_display));

        $conduit->callMethodSynchronous(
          'differential.close',
          array(
            'revisionID' => $revision_id,
          ));

        $this->writeOkay(
          pht('CLOSE'),
          pht(
            'Done, closed revision.'));
      }
    }

    $status = $revision['status'];
    if ($status == $status_accepted || $status == $status_closed) {
      // If this has already been attached to commits, don't show the
      // "you can push this commit" message since we know it's been pushed
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
        echo pht('Done.')."\n";
      }
    }

    return 0;
  }

}
