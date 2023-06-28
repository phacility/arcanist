<?php

final class ArcanistAmendWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'amend';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Amend the working copy, synchronizing the local commit message from
Differential.

Supported in Mercurial 2.2 and newer.
EOTEXT
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(
        pht('Amend the working copy, synchronizing the local commit message.'))
      ->addExample('**amend** [options] -- ')
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('show')
        ->setHelp(
          pht(
            'Show the amended commit message, without modifying the '.
            'working copy.')),
      $this->newWorkflowArgument('revision')
        ->setParameter('id')
        ->setHelp(
          pht(
            'Use the message from a specific revision. If you do not specify '.
            'a revision, arc will guess which revision is in the working '.
            'copy.')),
    );
  }

  protected function newPrompts() {
    return array(
      $this->newPrompt('arc.amend.unrelated')
        ->setDescription(
          pht(
            'Confirms use of a revision that does not appear to be '.
            'present in the working copy.')),
      $this->newPrompt('arc.amend.author')
        ->setDescription(
          pht(
            'Confirms use of a revision that you are not the author '.
            'of.')),
      $this->newPrompt('arc.amend.immutable')
        ->setDescription(
          pht(
            'Confirms history mutation in a working copy marked as '.
            'immutable.')),
    );
  }

  public function runWorkflow() {
    $symbols = $this->getSymbolEngine();

    $is_show = $this->getArgument('show');

    $repository_api = $this->getRepositoryAPI();
    if (!$is_show) {
      $this->requireAmendSupport($repository_api);
    }

    $revision_symbol = $this->getArgument('revision');

    // We only care about the local working copy state if we need it to
    // figure out which revision we're operating on, or we're planning to
    // mutate it. If the caller is running "arc amend --show --revision X",
    // the local state does not matter.

    $need_state =
      ($revision_symbol === null) ||
      (!$is_show);

    if ($need_state) {
      $state_ref = $repository_api->getCurrentWorkingCopyStateRef();

      $this->loadHardpoints(
        $state_ref,
        ArcanistWorkingCopyStateRef::HARDPOINT_REVISIONREFS);

      $revision_refs = $state_ref->getRevisionRefs();
    }

    if ($revision_symbol === null) {
      $revision_ref = $this->selectRevisionRef($revision_refs);
    } else {
      $revision_ref = $symbols->loadRevisionForSymbol($revision_symbol);
      if (!$revision_ref) {
        throw new PhutilArgumentUsageException(
          pht(
            'Revision "%s" does not exist, or you do not have permission '.
            'to see it.',
            $revision_symbol));
      }
    }

    if (!$is_show) {
      echo tsprintf(
        "%s\n\n%s\n",
        pht('Amending commit message to reflect revision:'),
        $revision_ref->newRefView());

      $this->confirmAmendAuthor($revision_ref);
      $this->confirmAmendNotFound($revision_ref, $state_ref);
    }

    $this->loadHardpoints(
      $revision_ref,
      ArcanistRevisionRef::HARDPOINT_COMMITMESSAGE);

    $message = $revision_ref->getCommitMessage();

    if ($is_show) {
      echo tsprintf(
        "%B\n",
        $message);
    } else {
      $repository_api->amendCommit($message);
    }

    return 0;
  }

  private function requireAmendSupport(ArcanistRepositoryAPI $api) {
    if (!$api->supportsAmend()) {
      if ($api instanceof ArcanistMercurialAPI) {
        throw new PhutilArgumentUsageException(
          pht(
            '"arc amend" is only supported under Mercurial 2.2 or newer. '.
            'Older versions of Mercurial do not support the "--amend" flag '.
            'to "hg commit ...", which this workflow requires.'));
      }

      throw new PhutilArgumentUsageException(
        pht(
          '"arc amend" must be run from inside a working copy of a '.
          'repository using a version control system that supports '.
          'amending commits, like Git or Mercurial.'));
    }

    if ($this->isHistoryImmutable()) {
      echo tsprintf(
        "%!\n\n%W\n",
        pht('IMMUTABLE WORKING COPY'),
        pht(
          'This working copy is configured to have an immutable local '.
          'history, using the "history.immutable" configuration option. '.
          'Amending the working copy will mutate local history.'));

      $prompt = pht('Are you sure you want to mutate history?');

      $this->getPrompt('arc.amend.immutable')
        ->setQuery($prompt)
        ->execute();
    }

    if ($api->getUncommittedChanges()) {
      // TODO: Make this class of error show the uncommitted changes.

      // TODO: This only needs to check for staged-but-uncommitted changes.
      // We can safely amend with untracked and unstaged changes.

      throw new PhutilArgumentUsageException(
        pht(
          'You have uncommitted changes in this working copy. Commit or '.
          'revert them before proceeding.'));
    }
  }

  private function selectRevisionRef(array $revisions) {
    if (!$revisions) {
      throw new PhutilArgumentUsageException(
        pht(
          'No revision specified with "--revision", and no revisions found '.
          'that match the current working copy state. Use "--revision <id>" '.
          'to specify which revision you want to amend.'));
    }

     if (count($revisions) > 1) {
       echo tsprintf(
         "%!\n%W\n\n%B\n",
         pht('MULTIPLE REVISIONS IN WORKING COPY'),
         pht('More than one revision was found in the working copy:'),
         mpull($revisions, 'newRefView'));

      throw new PhutilArgumentUsageException(
        pht(
          'Use "--revision <id>" to specify which revision you want '.
          'to amend.'));
    }

    return head($revisions);
  }

  private function confirmAmendAuthor(ArcanistRevisionRef $revision_ref) {
    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();

    $author_phid = $revision_ref->getAuthorPHID();

    if ($viewer_phid === $author_phid) {
      return;
    }

    $symbols = $this->getSymbolEngine();
    $author_ref = $symbols->loadUserForSymbol($author_phid);
    if (!$author_ref) {
      // If we don't have any luck loading the author, skip this warning.
      return;
    }

    echo tsprintf(
      "%!\n\n%W\n\n%s",
      pht('NOT REVISION AUTHOR'),
      array(
        pht(
          'You are amending the working copy using information from '.
          'a revision you are not the author of.'),
        "\n\n",
        pht(
          'The author of this revision (%s) is:',
          $revision_ref->getMonogram()),
      ),
      $author_ref->newRefView());

    $prompt = pht(
      'Amend working copy using revision owned by %s?',
      $author_ref->getMonogram());

    $this->getPrompt('arc.amend.author')
      ->setQuery($prompt)
      ->execute();
  }

  private function confirmAmendNotFound(
    ArcanistRevisionRef $revision_ref,
    ArcanistWorkingCopyStateRef $state_ref) {

    $local_refs = $state_ref->getRevisionRefs();
    $local_refs = mpull($local_refs, null, 'getPHID');

    $revision_phid = $revision_ref->getPHID();
    $is_local = isset($local_refs[$revision_phid]);

    if ($is_local) {
      return;
    }

    echo tsprintf(
      "%!\n\n%W\n",
      pht('UNRELATED REVISION'),
      pht(
        'You are amending the working copy using information from '.
        'a revision that does not appear to be associated with the '.
        'current state of the working copy.'));

    $prompt = pht(
      'Amend working copy using unrelated revision %s?',
      $revision_ref->getMonogram());

    $this->getPrompt('arc.amend.unrelated')
      ->setQuery($prompt)
      ->execute();
  }

}
