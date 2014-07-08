<?php

/**
 * Runs git revert and assigns a high priority task to original author.
 */
final class ArcanistBackoutWorkflow extends ArcanistBaseWorkflow {

  private $console;
  private $conduit;
  private $revision;

  public function getWorkflowName() {
    return 'backout';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **backout**
EOTEXT
    );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Reverts/backouts on a previous commit. Supports: git
   Command is used like this: arc backout <commithash> | <diff revision>
   Entering a differential revision will only work if there is only one commit
   associated with the revision. This requires your working copy is up to date
   and that the commit exists in the working copy.
EOTEXT
    );
  }

  public function getArguments() {
    return array(
      '*' => 'input',
    );
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  /**
   * Given a differential revision ID, fetches the commit ID.
   */
  private function getCommitIDFromRevisionID($revision_id) {
    $conduit = $this->getConduit();
    $revisions = $conduit->callMethodSynchronous(
      'differential.query',
      array(
        'ids' => array($revision_id),
      ));
    if (!$revisions) {
      throw new ArcanistUsageException(
        'The revision you provided does not exist!');
    }
    $revision = $revisions[0];
    $commits = $revision['commits'];
    if (!$commits) {
      throw new ArcanistUsageException(
        'This revision has not been committed yet!');
    }
    else if (count($commits) > 1) {
      throw new ArcanistUsageException(
        'The revision you provided has multiple commits!');
    }
    $commit_phid = $commits[0];
    $commit = $conduit->callMethodSynchronous(
      'phid.query',
      array(
        'phids' => array($commit_phid),
      ));
    $commit_id = $commit[$commit_phid]['name'];
    return $commit_id;
  }

  /**
   * Fetches an array of commit info provided a Commit_id in the form of
   * rE123456 (not local commit hash).
   */
  private function getDiffusionCommit($commit_id) {
    $result = $this->getConduit()->callMethodSynchronous(
      'diffusion.getcommits',
      array(
        'commits' => array($commit_id),
      ));
    $commit = $result[$commit_id];
    // This commit was not found in Diffusion
    if (array_key_exists('error', $commit)) {
      return null;
    }
    return $commit;
  }

  /**
   * Retrieves default template from differential and pre-fills info.
   */
  private function buildCommitMessage($commit_hash) {
    $conduit = $this->getConduit();
    $repository_api = $this->getRepositoryAPI();

    $summary = $repository_api->getBackoutMessage($commit_hash);
    $fields = array(
      'summary' => $summary,
      'testPlan' => 'revert-hammer',
    );
    $template = $conduit->callMethodSynchronous(
      'differential.getcommitmessage',
      array(
        'revision_id' => null,
        'edit'        => 'create',
        'fields'      => $fields
        ));
    $template = $this->newInteractiveEditor($template)
      ->setName('new-commit')
      ->editInteractively();
    return $template;
  }

  /**
   * Performs the backout/revert of a revision and creates a commit.
   */
  public function run() {
    $console = PhutilConsole::getConsole();
    $conduit = $this->getConduit();
    $repository_api = $this->getRepositoryAPI();

    $is_git_svn = $repository_api instanceof ArcanistGitAPI &&
                  $repository_api->isGitSubversionRepo();
    $is_hg_svn = $repository_api instanceof ArcanistMercurialAPI &&
                 $repository_api->isHgSubversionRepo();
    $revision_id = null;

    if (!($repository_api instanceof ArcanistGitAPI) &&
        !($repository_api instanceof ArcanistMercurialAPI)) {
      throw new ArcanistUsageException(
        'Backout currently only supports Git and Mercurial'
      );
    }

    $console->writeOut("Starting backout\n");
    $input = $this->getArgument('input');
    if (!$input || count($input) != 1) {
      throw new ArcanistUsageException(
        'You must specify one commit to backout!');
    }

    // Input looks like a Differential revision, so
    // we try to find the commit attached to it
    $matches = array();
    if (preg_match('/^D(\d+)$/i', $input[0], $matches)) {
      $revision_id = $matches[1];
      $commit_id = $this->getCommitIDFromRevisionID($revision_id);
      $commit = $this->getDiffusionCommit($commit_id);
      $commit_hash = $commit['commitIdentifier'];
      // Convert commit hash from SVN to Git/HG (for FB case)
      if ($is_git_svn || $is_hg_svn) {
        $commit_hash = $repository_api->
          getHashFromFromSVNRevisionNumber($commit_hash);
      }
    } else {
      // Assume input is a commit hash
      $commit_hash = $input[0];
    }
    if (!$repository_api->hasLocalCommit($commit_hash)) {
      throw new ArcanistUsageException(
        'Invalid commit provided or does not exist in the working copy!');
    }

    // Run 'backout'.
    $subject = $repository_api->getCommitSummary($commit_hash);
    $console->writeOut("Backing out commit {$commit_hash} {$subject} \n");

    $repository_api->backoutCommit($commit_hash);

    // Create commit message and execute the commit
    $message = $this->buildCommitMessage($commit_hash);
    $repository_api->doCommit($message);
    $console->writeOut("Double-check the commit and push when ready\n");
  }

}
