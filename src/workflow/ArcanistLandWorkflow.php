<?php

/**
 * Lands a branch by rebasing, merging and amending it.
 */
final class ArcanistLandWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'land';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Supports: git, git/p4, hg

Publish an accepted revision after review. This command is the last
step in the standard Differential code review workflow.

This command merges and pushes changes associated with an accepted
revision that are currently sitting in __ref__, which is usually the
name of a local branch. Without __ref__, the current working copy
state will be used.

Under Git: branches, tags, and arbitrary commits (detached HEADs)
may be landed.

Under Git/Perforce: branches, tags, and arbitrary commits may
be submitted.

Under Mercurial: branches and bookmarks may be landed, but only
onto a target of the same type. See T3855.

The workflow selects a target branch to land onto and a remote where
the change will be pushed to.

A target branch is selected by examining these sources in order:

  - the **--onto** flag;
  - the upstream of the branch targeted by the land operation,
    recursively (Git only);
  - the __arc.land.onto.default__ configuration setting;
  - or by falling back to a standard default:
    - "master" in Git;
    - "default" in Mercurial.

A remote is selected by examining these sources in order:

  - the **--remote** flag;
  - the upstream of the current branch, recursively (Git only);
  - the special "p4" remote which indicates a repository has
    been synchronized with Perforce (Git only);
  - or by falling back to a standard default:
    - "origin" in Git;
    - the default remote in Mercurial.

After selecting a target branch and a remote, the commits which will
be landed are printed.

With **--preview**, execution stops here, before the change is
merged.

The change is merged with the changes in the target branch,
following these rules:

In repositories with mutable history or with **--squash**, this will
perform a squash merge (the entire branch will be represented as one
commit after the merge).

In repositories with immutable history or with **--merge**, this will
perform a strict merge (a merge commit will always be created, and
local commits will be preserved).

The resulting commit will be given an up-to-date commit message
describing the final state of the revision in Differential.

In Git, the merge occurs in a detached HEAD. The local branch
reference (if one exists) is not updated yet.

With **--hold**, execution stops here, before the change is pushed.

The change is pushed into the remote.

Consulting mystical sources of power, the workflow makes a guess
about what state you wanted to end up in after the process finishes
and the working copy is put into that state.

The branch which was landed is deleted, unless the **--keep-branch**
flag was passed or the landing branch is the same as the target
branch.
EOTEXT
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Publish reviewed changes.'))
      ->addExample(pht('**land** [__options__] [__ref__ ...]'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('hold')
        ->setHelp(
          pht(
            'Prepare the change to be pushed, but do not actually push it.')),
      $this->newWorkflowArgument('keep-branches')
        ->setHelp(
          pht(
            'Keep local branches around after changes are pushed. By '.
            'default, local branches are deleted after they land.')),
      $this->newWorkflowArgument('onto-remote')
        ->setParameter('remote-name')
        ->setHelp(pht('Push to a remote other than the default.')),

      // TODO: Formally allow flags to be bound to related configuration
      // for documentation, e.g. "setRelatedConfiguration('arc.land.onto')".

      $this->newWorkflowArgument('onto')
        ->setParameter('branch-name')
        ->setRepeatable(true)
        ->setHelp(
          pht(
            'After merging, push changes onto a specified branch. '.
            'Specifying this flag multiple times will push multiple '.
            'branches.')),
      $this->newWorkflowArgument('strategy')
        ->setParameter('strategy-name')
        ->setHelp(
          pht(
            // TODO: Improve this.
            'Merge using a particular strategy.')),
      $this->newWorkflowArgument('revision')
        ->setParameter('revision-identifier')
        ->setHelp(
          pht(
            'Land a specific revision, rather than determining the revisions '.
            'from the commits that are landing.')),
      $this->newWorkflowArgument('preview')
        ->setHelp(
          pht(
            'Shows the changes that will land. Does not modify the working '.
            'copy or the remote.')),
      $this->newWorkflowArgument('into')
        ->setParameter('commit-ref')
        ->setHelp(
          pht(
            'Specifies the state to merge into. By default, this is the same '.
            'as the "onto" ref.')),
      $this->newWorkflowArgument('into-remote')
        ->setParameter('remote-name')
        ->setHelp(
          pht(
            'Specifies the remote to fetch the "into" ref from. By '.
            'default, this is the same as the "onto" remote.')),
      $this->newWorkflowArgument('into-local')
        ->setHelp(
          pht(
            'Use the local "into" ref state instead of fetching it from '.
            'a remote.')),
      $this->newWorkflowArgument('into-empty')
        ->setHelp(
          pht(
            'Merge into the empty state instead of an existing state. This '.
            'mode is primarily useful when creating a new repository, and '.
            'selected automatically if the "onto" ref does not exist and the '.
            '"into" state is not specified.')),
      $this->newWorkflowArgument('incremental')
        ->setHelp(
          pht(
            'When landing multiple revisions at once, push and rebase '.
            'after each operation instead of waiting until all merges '.
            'are completed. This is slower than the default behavior and '.
            'not atomic, but may make it easier to resolve conflicts and '.
            'land complicated changes by letting you make progress one '.
            'step at a time.')),
      $this->newWorkflowArgument('ref')
        ->setWildcard(true),
    );
  }

  protected function newPrompts() {
    return array(
      $this->newPrompt('arc.land.large-working-set')
        ->setDescription(
          pht(
            'Confirms landing more than %s commit(s) in a single operation.',
            new PhutilNumber($this->getLargeWorkingSetLimit()))),
      $this->newPrompt('arc.land.confirm')
        ->setDescription(
          pht(
            'Confirms that the correct changes have been selected.')),
      $this->newPrompt('arc.land.implicit')
        ->setDescription(
          pht(
            'Confirms that local commits which are not associated with '.
            'a revision should land.')),
      $this->newPrompt('arc.land.unauthored')
        ->setDescription(
          pht(
            'Confirms that revisions you did not author should land.')),
      $this->newPrompt('arc.land.changes-planned')
        ->setDescription(
          pht(
            'Confirms that revisions with changes planned should land.')),
      $this->newPrompt('arc.land.closed')
        ->setDescription(
          pht(
            'Confirms that revisions that are already closed should land.')),
      $this->newPrompt('arc.land.not-accepted')
        ->setDescription(
          pht(
            'Confirms that revisions that are not accepted should land.')),
      $this->newPrompt('arc.land.open-parents')
        ->setDescription(
          pht(
            'Confirms that revisions with open parent revisions should '.
            'land.')),
      $this->newPrompt('arc.land.failed-builds')
        ->setDescription(
          pht(
            'Confirms that revisions with failed builds.')),
      $this->newPrompt('arc.land.ongoing-builds')
        ->setDescription(
          pht(
            'Confirms that revisions with ongoing builds.')),
    );
  }

  public function getLargeWorkingSetLimit() {
    return 50;
  }

  public function runWorkflow() {
    $working_copy = $this->getWorkingCopy();
    $repository_api = $working_copy->getRepositoryAPI();

    $land_engine = $repository_api->getLandEngine();
    if (!$land_engine) {
      throw new PhutilArgumentUsageException(
        pht(
          '"arc land" must be run in a Git or Mercurial working copy.'));
    }

    $is_incremental = $this->getArgument('incremental');
    $source_refs = $this->getArgument('ref');

    $onto_remote_arg = $this->getArgument('onto-remote');
    $onto_args = $this->getArgument('onto');

    $into_remote = $this->getArgument('into-remote');
    $into_empty = $this->getArgument('into-empty');
    $into_local = $this->getArgument('into-local');
    $into = $this->getArgument('into');

    $is_preview = $this->getArgument('preview');
    $should_hold = $this->getArgument('hold');
    $should_keep = $this->getArgument('keep-branches');

    $revision = $this->getArgument('revision');
    $strategy = $this->getArgument('strategy');

    $land_engine
      ->setViewer($this->getViewer())
      ->setWorkflow($this)
      ->setLogEngine($this->getLogEngine())
      ->setSourceRefs($source_refs)
      ->setShouldHold($should_hold)
      ->setShouldKeep($should_keep)
      ->setStrategyArgument($strategy)
      ->setShouldPreview($is_preview)
      ->setOntoRemoteArgument($onto_remote_arg)
      ->setOntoArguments($onto_args)
      ->setIntoRemoteArgument($into_remote)
      ->setIntoEmptyArgument($into_empty)
      ->setIntoLocalArgument($into_local)
      ->setIntoArgument($into)
      ->setIsIncremental($is_incremental)
      ->setRevisionSymbol($revision);

    $land_engine->execute();
  }

}
