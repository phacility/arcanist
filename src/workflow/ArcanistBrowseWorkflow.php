<?php

/**
 * Browse files in the Diffusion web interface.
 */
final class ArcanistBrowseWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'browse';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **browse** [__options__] __path__ ...
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, hg, svn
          Browse files in the Diffusion web interface.

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
      '*' => 'paths',
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

  public function run() {
    $repository_api = $this->getRepositoryAPI();
    $project_root = $this->getWorkingCopy()->getProjectRoot();

    $in_paths = $this->getArgument('paths');
    if (!$in_paths) {
      throw new ArcanistUsageException(
        pht(
          'Specify one or more paths to browse. Use the command '.
          '"arc browse ." if you want to browse this directory.'));
    }

    $paths = array();
    foreach ($in_paths as $key => $path) {
      $path = preg_replace('/:([0-9]+)$/', '$\1', $path);
      $full_path = Filesystem::resolvePath($path);

      if ($full_path == $project_root) {
        $paths[$key] = '';
      } else {
        $paths[$key] = Filesystem::readablePath($full_path, $project_root);
      }
    }

    $base_uri = $this->getBaseURI();
    $browser = $this->getBrowserCommand();

    foreach ($paths as $path) {
      $ret_code = phutil_passthru('%s %s', $browser, $base_uri.$path);
      if ($ret_code) {
        throw new ArcanistUsageException(
          "It seems we failed to open the browser; perhaps you should try to ".
          "set the 'browser' config option. The command we tried to use was: ".
          $browser);
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
