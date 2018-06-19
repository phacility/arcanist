<?php

/**
 * Lands a branch by submitting it through SubmitQueue
 */
final class ArcanistSubmitWorkflow extends ArcanistWorkflow {

  private $isGit;
  private $onto;
  private $branch;
  private $branchType;
  private $keepBranch;
  private $remote;
  private $ontoRemoteBranch;
  private $preview;

  private $shouldUseSubmitQueue;
  private $submitQueueUri;
  private $submitQueueClient;

  const REFTYPE_BRANCH = 'branch';
  const REFTYPE_BOOKMARK = 'bookmark';

  public function run() {
    $this->readArguments();
    $revision = $this->getRevision();
    $engine = new UberArcanistSubmitQueueEngine(
      $this->submitQueueClient,
      $this->getConduit());

    $engine
      ->setRevision($revision)
      ->setWorkflow($this)
      ->setRepositoryAPI($this->getRepositoryAPI())
      ->setSourceRef($this->branch)
      ->setTargetRemote($this->remote)
      ->setTargetOnto($this->onto)
      ->setShouldKeep($this->keepBranch)
      ->setSkipUpdateWorkingCopy(false)
      ->setShouldHold(false)
      ->setShouldSquash(false)
      ->setShouldPreview($this->preview)
      ->setSkipUpdateWorkingCopy(true);

    $engine->execute();

    return 0;
  }

  public function getWorkflowName() {
    return 'submit';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **submit** [__options__] [__ref__]
EOTEXT
    );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git

          Publish an accepted revision after review through SubmitQueue.
          This command is the last step in the standard Differential pre-publish
          code review workflow.

          This command requests Submit Queue to push changes associated with an accepted
          revision that are currently sitting in __ref__, which is usually the
          name of a local branch. Without __ref__, the current working copy
          state will be used.

          Under Git: branches, tags, and arbitrary commits (detached HEADs)
          may be landed.

          The workflow selects a target branch to land onto and a remote where
          the change will be pushed to.

          A target branch is selected by examining these sources in order:

            - the **--onto** flag;
            - the upstream of the current branch, recursively (Git only);
            - the __arc.land.onto.default__ configuration setting;
            - or by falling back to a standard default:
              - "master" in Git;

          A remote is selected by examining these sources in order:

            - the **--remote** flag;
            - the upstream of the current branch, recursively (Git only);
            - or by falling back to a standard default:
              - "origin" in Git;

          After selecting a target branch and a remote, the commits which will
          be landed are printed.

          With **--preview**, execution stops here, before the change is
          pushed to Submit Queue.

          The resulting commit will be given an up-to-date commit message
          describing the final state of the revision in Differential.

          The change is pushed into the configured submit queue.

          The branch which was landed is deleted, unless the **--keep-branch**
          flag was passed or the landing branch is the same as the target
          branch.

EOTEXT
    );
  }

  private function readArguments() {
    $repository_api = $this->getRepositoryAPI();
    $this->isGit = $repository_api instanceof ArcanistGitAPI;
    if (!$this->isGit) {
      $message = pht(
        "You are trying to use submit workflow, but the submit workflow only works for git repositories");
      throw new ArcanistUsageException($message);
    }

    $branch = $this->getArgument('branch');
    if (empty($branch)) {
      $branch = $this->getBranchOrBookmark();
      if ($branch) {
        $this->branchType = $this->getBranchType($branch);

        // TODO: This message is misleading when landing a detached head or
        // a tag in Git.

        echo pht("Landing current %s '%s'.", $this->branchType, $branch), "\n";
        $branch = array($branch);
      }
    }

    if (count($branch) !== 1) {
      throw new ArcanistUsageException(
        pht('Specify exactly one branch or bookmark to land changes from.'));
    }
    $this->branch = head($branch);
    $this->keepBranch = $this->getArgument('keep-branch');
    $this->preview = $this->getArgument('preview');

    $onto_default = 'master';
    $onto_default = nonempty(
      $this->getConfigFromAnySource('arc.land.onto.default'),
      $onto_default);
    $this->onto = $this->getArgument('onto', $onto_default);

    $remote_default = 'origin';
    $remote_default = coalesce(
      $this->getUpstreamMatching($this->onto, '/^refs\/remotes\/(.+?)\//'),
      $remote_default);
    $this->remote = $this->getArgument('remote', $remote_default);

    $this->ontoRemoteBranch = $this->onto;
    if ($this->isGit) {
      $this->ontoRemoteBranch = $this->remote.'/'.$this->onto;
    }

    $remote_default = $this->isGit ? 'origin' : '';
    $remote_default = coalesce(
      $this->getUpstreamMatching($this->onto, '/^refs\/remotes\/(.+?)\//'),
      $remote_default);
    $this->remote = $this->getArgument('remote', $remote_default);

    $this->shouldUseSubmitQueue = nonempty(
      $this->getConfigFromAnySource('uber.land.submitqueue.enable'),
      $this->getArgument('use-sq'),
      false
    );

    if (!$this->shouldUseSubmitQueue) {
      $message = pht(
        "You are trying to use submit workflow, but submitqueue is not enabled for your repo");
      throw new ArcanistUsageException($message);
    }

    $this->submitQueueUri = $this->getConfigFromAnySource('uber.land.submitqueue.uri');
    if(empty($this->submitQueueUri)) {
      $message = pht(
        "You are trying to use submitqueue, but the submitqueue URI for your repo is not set");
      throw new ArcanistUsageException($message);
    }
    $this->submitQueueClient =
      new UberSubmitQueueClient(
        $this->submitQueueUri,
        $this->getConduit()->getConduitToken());
  }

  private function getRevision() {
    $repository_api = $this->getRepositoryAPI();

    $this->parseBaseCommitArgument(array($this->ontoRemoteBranch));

    $revision_id = $this->getArgument('revision');
    if ($revision_id) {
      $revision_id = $this->normalizeRevisionID($revision_id);
      $revisions = $this->getConduit()->callMethodSynchronous(
        'differential.query',
        array(
          'ids' => array($revision_id),
        ));
      if (!$revisions) {
        throw new ArcanistUsageException(pht(
          "No such revision '%s'!",
          "D{$revision_id}"));
      }
    } else {
      $revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
        $this->getConduit(),
        array());
    }

    if (!count($revisions)) {
      throw new ArcanistUsageException(pht(
        "arc can not identify which revision exists on %s '%s'. Update the " .
        "revision with recent changes to synchronize the %s name and hashes, " .
        "or use '%s' to amend the commit message at HEAD, or use " .
        "'%s' to select a revision explicitly.",
        $this->branchType,
        $this->branch,
        $this->branchType,
        'arc amend',
        '--revision <id>'));
    } else if (count($revisions) > 1) {
      switch ($this->branchType) {
        case self::REFTYPE_BOOKMARK:
          $message = pht(
            "There are multiple revisions on feature bookmark '%s' which are " .
            "not present on '%s':\n\n" .
            "%s\n" .
            'Separate these revisions onto different bookmarks, or use ' .
            '--revision <id> to use the commit message from <id> ' .
            'and land them all.',
            $this->branch,
            $this->onto,
            $this->renderRevisionList($revisions));
          break;
        case self::REFTYPE_BRANCH:
        default:
          $message = pht(
            "There are multiple revisions on feature branch '%s' which are " .
            "not present on '%s':\n\n" .
            "%s\n" .
            'Separate these revisions onto different branches, or use ' .
            '--revision <id> to use the commit message from <id> ' .
            'and land them all.',
            $this->branch,
            $this->onto,
            $this->renderRevisionList($revisions));
          break;
      }

      throw new ArcanistUsageException($message);
    }

    return head($revisions);
  }

  private function getUpstreamMatching($branch, $pattern) {
    if ($this->isGit) {
      $repository_api = $this->getRepositoryAPI();
      list($err, $fullname) = $repository_api->execManualLocal(
        'rev-parse --symbolic-full-name %s@{upstream}',
        $branch);
      if (!$err) {
        $matches = null;
        if (preg_match($pattern, $fullname, $matches)) {
          return last($matches);
        }
      }
    }
    return null;
  }

  private function getBranchOrBookmark() {
    $repository_api = $this->getRepositoryAPI();
    if ($this->isGit) {
      $branch = $repository_api->getBranchName();

      // If we don't have a branch name, just use whatever's at HEAD.
      if (!strlen($branch)) {
        $branch = $repository_api->getWorkingCopyRevision();
      }
    } else if ($this->isHg) {
      $branch = $repository_api->getActiveBookmark();
      if (!$branch) {
        $branch = $repository_api->getBranchName();
      }
    }

    return $branch;
  }

  private function getBranchType($branch) {
    return 'branch';
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
      'onto' => array(
        'param' => 'master',
        'help' => pht(
          "Land feature branch onto a branch other than the default ".
          "('master' in git, 'default' in hg). You can change the default ".
          "by setting '%s' with `%s` or for the entire project in %s.",
          'arc.land.onto.default',
          'arc set-config',
          '.arcconfig'),
      ),
      'use-sq' => array(
        'help' => pht(
          'force using the submit-queue if the submit-queue is configured '.
          'for this repo.'),
      ),
      'revision' => array(
        'param' => 'id',
        'help' => pht(
          'Use the message from a specific revision, rather than '.
          'inferring the revision based on branch content.'),
      ),
    );
  }
}
