<?php

/**
 * Show which revision or revisions are in the working copy.
 *
 * @group workflow
 */
final class ArcanistWhichWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'which';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **which** (svn)
      **which** [commit] (hg, git)
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: svn, git, hg
          Shows which commits 'arc diff' will select, and which revision is in
          the working copy (or which revisions, if more than one matches).
EOTEXT
      );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function getArguments() {
    return array(
      'any-author' => array(
        'help' => "Show revisions by any author, not just you.",
      ),
      'any-status' => array(
        'help' => "Show committed and abandoned revisions.",
      ),
      'base' => array(
        'param' => 'rules',
        'help'  => 'Additional rules for determining base revision.',
        'nosupport' => array(
          'svn' => 'Subversion does not use base commits.',
        ),
        'supports' => array('git', 'hg'),
      ),
      'show-base' => array(
        'help'  => 'Print base commit only and exit.',
        'nosupport' => array(
          'svn' => 'Subversion does not use base commits.',
        ),
        'supports' => array('git', 'hg'),
      ),
      '*' => 'commit',
    );
  }

  public function run() {

    $repository_api = $this->getRepositoryAPI();

    $arg_commit = $this->getArgument('commit');
    if (count($arg_commit)) {
      $this->parseBaseCommitArgument($arg_commit);
    }
    $arg = $arg_commit ? ' '.head($arg_commit) : '';

    $repository_api->setBaseCommitArgumentRules(
      $this->getArgument('base', ''));

    if ($repository_api->supportsCommitRanges()) {
      $relative = $repository_api->getBaseCommit();

      if ($this->getArgument('show-base')) {
        echo $relative."\n";
        return 0;
      }

      $info = $repository_api->getLocalCommitInformation();
      if ($info) {
        $commits = array();
        foreach ($info as $commit) {
          $hash     = substr($commit['commit'], 0, 16);
          $summary  = $commit['summary'];

          $commits[] = "    {$hash}  {$summary}";
        }
        $commits = implode("\n", $commits);
      } else {
        $commits = '    (No commits.)';
      }

      $explanation = $repository_api->getBaseCommitExplanation();

      $relative_summary = $repository_api->getCommitSummary($relative);
      $relative = substr($relative, 0, 16);

      if ($repository_api instanceof ArcanistGitAPI) {
        $command = "git diff {$relative}..HEAD";
      } else if ($repository_api instanceof ArcanistMercurialAPI) {
        $command = "hg diff --rev {$relative}";
      } else {
        throw new Exception("Unknown VCS!");
      }

      echo phutil_console_wrap(
        phutil_console_format(
          "**RELATIVE COMMIT**\n".
          "If you run 'arc diff{$arg}', changes between the commit:\n\n"));

      echo  "    {$relative}  {$relative_summary}\n\n";
      echo phutil_console_wrap(
        "...and the current working copy state will be sent to ".
        "Differential, because {$explanation}\n\n".
        "You can see the exact changes that will be sent by running ".
        "this command:\n\n".
        "    $ {$command}\n\n".
        "These commits will be included in the diff:\n\n");

      echo $commits."\n\n\n";
    }

    $any_author = $this->getArgument('any-author');
    $any_status = $this->getArgument('any-status');

    $query = array(
      'authors' => $any_author
        ? null
        : array($this->getUserPHID()),
      'status' => $any_status
        ? 'status-any'
        : 'status-open',
    );

    $revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
      $this->getConduit(),
      $query);

    echo phutil_console_wrap(
      phutil_console_format(
        "**MATCHING REVISIONS**\n".
        "These Differential revisions match the changes in this working ".
        "copy:\n\n"));

    if (empty($revisions)) {
      echo "    (No revisions match.)\n";
      echo "\n";
      echo phutil_console_wrap(
        phutil_console_format(
          "Since there are no revisions in Differential which match this ".
          "working copy, a new revision will be **created** if you run ".
          "'arc diff{$arg}'.\n\n"));
    } else {
      foreach ($revisions as $revision) {
        echo '    D'.$revision['id'].' '.$revision['title']."\n";
        echo '        Reason: '.$revision['why']."\n";
        echo "\n";
      }
      if (count($revisions) == 1) {
        echo phutil_console_wrap(
          phutil_console_format(
            "Since exactly one revision in Differential matches this working ".
            "copy, it will be **updated** if you run 'arc diff{$arg}'."));
      } else {
        echo phutil_console_wrap(
          "Since more than one revision in Differential matches this working ".
          "copy, you will be asked which revision you want to update if ".
          "you run 'arc diff {$arg}'.");
      }
      echo "\n\n";
    }

    return 0;
  }
}
