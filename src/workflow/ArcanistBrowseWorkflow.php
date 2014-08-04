<?php

/**
 * Browse files or objects in the Phabricator web interface.
 */
final class ArcanistBrowseWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'browse';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **browse** [__options__] __path__ ...
      **browse** [__options__] __object__ ...
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, hg, svn
          Open a file or object (like a task or revision) in your web browser.

            $ arc browse README   # Open a file in Diffusion.
            $ arc browse T123     # View a task.

          Set the 'browser' value using 'arc set-config' to select a browser. If
          no browser is set, the command will try to guess which browser to use.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'branch' => array(
        'param' => 'branch_name',
        'help' => pht(
          'Default branch name to view on server. Defaults to "master".'),
      ),
      'force' => array(
        'help' => pht(
          'Open arguments as paths, even if they do not exist in the '.
          'working copy.'),
      ),
      '*' => 'paths',
    );
  }

  public function desiresWorkingCopy() {
    return true;
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function desiresRepositoryAPI() {
    return true;
  }

  public function run() {
    $console = PhutilConsole::getConsole();

    $is_force = $this->getArgument('force');

    $things = $this->getArgument('paths');
    if (!$things) {
      throw new ArcanistUsageException(
        pht(
          'Specify one or more paths or objects to browse. Use the command '.
          '"arc browse ." if you want to browse this directory.'));
    }
    $things = array_fuse($things);

    $objects = $this->getConduit()->callMethodSynchronous(
      'phid.lookup',
      array(
        'names' => array_keys($things),
      ));

    $uris = array();
    foreach ($objects as $name => $object) {
      $uris[] = $object['uri'];

      $console->writeOut(
        pht(
          'Opening **%s** as an object.',
          $name)."\n");

      unset($things[$name]);
    }

    if ($this->hasRepositoryAPI()) {
      $repository_api = $this->getRepositoryAPI();
      $project_root = $this->getWorkingCopy()->getProjectRoot();

      foreach ($things as $key => $path) {
        $path = preg_replace('/:([0-9]+)$/', '$\1', $path);
        $full_path = Filesystem::resolvePath($path);

        if (!$is_force && !Filesystem::pathExists($full_path)) {
          continue;
        }

        $console->writeOut(
          pht(
            'Opening **%s** as a repository path.',
            $key)."\n");

        unset($things[$key]);

        if ($full_path == $project_root) {
          $path = '';
        } else {
          $path = Filesystem::readablePath($full_path, $project_root);
        }

        $base_uri = $this->getBaseURI();
        $uris[] = $base_uri.$path;
      }
    } else {
      if ($things) {
        $console->writeOut(
          pht(
            "The current working directory is not a repository working ".
            "copy, so remaining arguments can not be resolved as paths. ".
            "To browse paths in Diffusion, run 'arc browse' from inside ".
            "a working copy.")."\n");
      }
    }

    foreach ($things as $thing) {
      $console->writeOut(
        pht(
          'Unable to find an object named **%s**, and no such path exists '.
          'in the working copy. Use __--force__ to treat this as a path '.
          'anyway.',
          $thing)."\n");
    }

    if ($uris) {
      $browser = $this->getBrowserCommand();
      foreach ($uris as $uri) {
        $err = phutil_passthru('%s %s', $browser, $uri);
        if ($err) {
          throw new ArcanistUsageException(
            pht(
              "Failed to execute browser ('%s'). Check your 'browser' config ".
              "option."));
        }
      }
    }

    return 0;
  }

  private function getBaseURI() {
    $repo_uri = $this->getRepositoryURI();
    if ($repo_uri === null) {
      throw new ArcanistUsageException(
        pht(
          'arc is unable to determine which repository in Diffusion '.
          'this working copy belongs to. Use "arc which" to understand how '.
          'arc looks for a repository.'));
    }

    $branch = $this->getArgument('branch', 'master');

    return $repo_uri.'browse/'.$branch.'/';
  }

  private function getBrowserCommand() {
    $config = $this->getConfigFromAnySource('browser');
    if ($config) {
      return $config;
    }

    if (phutil_is_windows()) {
      return 'start';
    }

    $candidates = array('sensible-browser', 'xdg-open', 'open');

    // NOTE: The "open" command works well on OS X, but on many Linuxes "open"
    // exists and is not a browser. For now, we're just looking for other
    // commands first, but we might want to be smarter about selecting "open"
    // only on OS X.

    foreach ($candidates as $cmd) {
      if (Filesystem::binaryExists($cmd)) {
        return $cmd;
      }
    }

    throw new ArcanistUsageException(
      pht(
        "Unable to find a browser command to run. Set 'browser' in your ".
        "arc config to specify one."));
  }

}
