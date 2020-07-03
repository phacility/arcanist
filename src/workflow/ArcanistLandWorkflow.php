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
Supports: git, git/p4, git/svn, hg

Publish accepted revisions after review. This command is the last step in the
standard Differential code review workflow.

To publish changes in local branch or bookmark "feature1", you will usually
run this command:

  **$ arc land feature1**

This workflow merges and pushes changes associated with revisions that are
ancestors of __ref__. Without __ref__, the current working copy state will be
used. You can specify multiple __ref__ arguments to publish multiple changes at
once.

A __ref__ can be any symbol which identifies a commit: a branch name, a tag
name, a bookmark name, a topic name, a raw commit hash, a symbolic reference,
etc.

When you provide a __ref__, all unpublished changes which are present in
ancestors of that __ref__ will be selected for publishing. (With the
**--pick** flag, only the unpublished changes you directly reference will be
selected.)

For example, if you provide local branch "feature3" as a __ref__ argument, that
may also select the changes in "feature1" and "feature2" (if they are ancestors
of "feature3"). If you stack changes in a single local branch, all commits in
the stack may be selected.

The workflow merges unpublished changes reachable from __ref__ "into" some
intermediate branch, then pushes the combined state "onto" some destination
branch (or list of branches).

(In Mercurial, the "into" and "onto" branches may be bookmarks instead.)

In the most common case, there is only one "onto" branch (often "master" or
"default" or some similar branch) and the "into" branch is the same branch. For
example, it is common to merge local feature branch "feature1" into
"origin/master", then push it onto "origin/master".

The list of "onto" branches is selected by examining these sources in order:

  - the **--onto** flags;
  - the __arc.land.onto__ configuration setting;
  - (in Git) the upstream of the branch targeted by the land operation,
    recursively;
  - or by falling back to a standard default:
    - (in Git) "master";
    - (in Mercurial) "default".

The remote to push "onto" is selected by examining these sources in order:

  - the **--onto-remote** flag;
  - the __arc.land.onto-remote__ configuration setting;
  - (in Git) the upstream of the current branch, recursively;
  - (in Git) the special "p4" remote which indicates a repository has
    been synchronized with Perforce;
  - or by falling back to a standard default:
    - (in Git) "origin";
    - (in Mercurial) "default".

The branch to merge "into" is selected by examining these sources in order:

  - the **--into** flag;
  - the **--into-empty** flag;
  - or by falling back to the first "onto" branch.

The remote to merge "into" is selected by examining these sources in order:

  - the **--into-remote** flag;
  - the **--into-local** flag (which disables fetching before merging);
  - or by falling back to the "onto" remote.

After selecting remotes and branches, the commits which will land are printed.

With **--preview**, execution stops here, before the change is merged.

The "into" branch is fetched from the "into" remote (unless **--into-local** or
**--into-empty** are specified) and the changes are merged into the state in
the "into" branch according to the selected merge strategy.

The default merge strategy is "squash", which produces a single commit from
all local commits for each change. A different strategy can be selected with
the **--strategy** flag.

The resulting merged change will be given an up-to-date commit message
describing the final state of the revision in Differential.

With **--hold**, execution stops here, before the change is pushed.

The change is pushed onto all of the "onto" branches in the "onto" remote.

If you are landing multiple changes, they are normally all merged locally and
then all pushed in a single operation. Instead, you can merge and push them one
at a time with **--incremental**.

Under merge strategies which mutate history (including the default "squash"
strategy), local refs which descend from commits that were published are
now updated. For example, if you land "feature4", local branches "feature5" and
"feature6" may now be rebased on the published version of the change.

Once everything has been pushed, cleanup occurs. Consulting mystical sources of
power, the workflow makes a guess about what state you wanted to end up in
after the process finishes. The working copy is put into that state.

Any obsolete refs that point at commits which were published are deleted,
unless the **--keep-branches** flag is passed.
EOTEXT
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Publish reviewed changes.'))
      ->addExample(pht('**land** [__options__] -- [__ref__ ...]'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('hold')
        ->setHelp(
          pht(
            'Prepare the changes to be pushed, but do not actually push '.
            'them.')),
      $this->newWorkflowArgument('keep-branches')
        ->setHelp(
          pht(
            'Keep local branches around after changes are pushed. By '.
            'default, local branches are deleted after the changes they '.
            'contain are published.')),
      $this->newWorkflowArgument('onto-remote')
        ->setParameter('remote-name')
        ->setHelp(pht('Push to a remote other than the default.'))
        ->addRelatedConfig('arc.land.onto-remote'),
      $this->newWorkflowArgument('onto')
        ->setParameter('branch-name')
        ->setRepeatable(true)
        ->addRelatedConfig('arc.land.onto')
        ->setHelp(
          array(
            pht(
              'After merging, push changes onto a specified branch.'),
            pht(
              'Specifying this flag multiple times will push to multiple '.
              'branches.'),
          )),
      $this->newWorkflowArgument('strategy')
        ->setParameter('strategy-name')
        ->addRelatedConfig('arc.land.strategy')
        ->setHelp(
          array(
            pht(
              'Merge using a particular strategy. Supported strategies are '.
              '"squash" and "merge".'),
            pht(
              'The "squash" strategy collapses multiple local commits into '.
              'a single commit when publishing. It produces a linear '.
              'published history (but discards local checkpoint commits). '.
              'This is the default strategy.'),
            pht(
              'The "merge" strategy generates a merge commit when publishing '.
              'that retains local checkpoint commits (but produces a '.
              'nonlinear published history). Select this strategy if you do '.
              'not want "arc land" to discard checkpoint commits.'),
          )),
      $this->newWorkflowArgument('revision')
        ->setParameter('revision-identifier')
        ->setHelp(
          pht(
            'Land a specific revision, rather than determining revisions '.
            'automatically from the commits that are landing.')),
      $this->newWorkflowArgument('preview')
        ->setHelp(
          pht(
            'Show the changes that will land. Does not modify the working '.
            'copy or the remote.')),
      $this->newWorkflowArgument('into')
        ->setParameter('commit-ref')
        ->setHelp(
          pht(
            'Specify the state to merge into. By default, this is the same '.
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
          array(
            pht(
              'When landing multiple revisions at once, push and rebase '.
              'after each merge completes instead of waiting until all '.
              'merges are completed to push.'),
            pht(
              'This is slower than the default behavior and not atomic, '.
              'but may make it easier to resolve conflicts and land '.
              'complicated changes by allowing you to make progress one '.
              'step at a time.'),
          )),
      $this->newWorkflowArgument('pick')
        ->setHelp(
          pht(
            'Land only the changes directly named by arguments, instead '.
            'of all reachable ancestors.')),
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
            'Confirms that the correct changes have been selected to '.
            'land.')),
      $this->newPrompt('arc.land.implicit')
        ->setDescription(
          pht(
            'Confirms that local commits which are not associated with '.
            'a revision have been associated correctly and should land.')),
      $this->newPrompt('arc.land.unauthored')
        ->setDescription(
          pht(
            'Confirms that revisions you did not author should land.')),
      $this->newPrompt('arc.land.changes-planned')
        ->setDescription(
          pht(
            'Confirms that revisions with changes planned should land.')),
      $this->newPrompt('arc.land.published')
        ->setDescription(
          pht(
            'Confirms that revisions that are already published should land.')),
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
            'Confirms that revisions with failed builds should land.')),
      $this->newPrompt('arc.land.ongoing-builds')
        ->setDescription(
          pht(
            'Confirms that revisions with ongoing builds should land.')),
      $this->newPrompt('arc.land.create')
        ->setDescription(
          pht(
            'Confirms that new branches or bookmarks should be created '.
            'in the remote.')),
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
    $pick = $this->getArgument('pick');

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
      ->setPickArgument($pick)
      ->setIsIncremental($is_incremental)
      ->setRevisionSymbol($revision);

    $land_engine->execute();
  }

}
