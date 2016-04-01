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
            $ arc browse HEAD     # View a symbolic commit.

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
          'Default branch name to view on server. Defaults to "%s".',
          'master'),
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
          '"%s" if you want to browse this directory.',
          'arc browse .'));
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

      // First, try to resolve arguments as symbolic commits.

      $commits = array();
      foreach ($things as $key => $thing) {
        if ($thing == '.') {
          // Git resolves '.' like HEAD, but it should be interpreted to mean
          // "the current directory". Just skip resolution and fall through.
          continue;
        }

        try {
          $commit = $repository_api->getCanonicalRevisionName($thing);
          if ($commit) {
            $commits[$commit] = $key;
          }
        } catch (Exception $ex) {
          // Ignore.
        }
      }

      if ($commits) {
        $commit_info = $this->getConduit()->callMethodSynchronous(
          'diffusion.querycommits',
          array(
            'repositoryPHID' => $this->getRepositoryPHID(),
            'names' => array_keys($commits),
          ));

        foreach ($commit_info['identifierMap'] as $ckey => $cphid) {
          $thing = $commits[$ckey];
          unset($things[$thing]);

          $uris[] = $commit_info['data'][$cphid]['uri'];

          $console->writeOut(
            pht(
              'Opening **%s** as a commit.',
              $thing)."\n");
        }
      }

      // If we fail, try to resolve them as paths.

      foreach ($things as $key => $path) {
        $lines = null;
        $parts = explode(':', $path);
        if (count($parts) > 1) {
          $lines = array_pop($parts);
        }
        $path = implode(':', $parts);

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
        $uri = $base_uri.$path;

        if ($lines) {
          $uri = $uri.'$'.$lines;
        }

        $uris[] = $uri;
      }
    } else {
      if ($things) {
        $console->writeOut(
          "%s\n",
          pht(
            "The current working directory is not a repository working ".
            "copy, so remaining arguments can not be resolved as paths or ".
            "commits. To browse paths or symbolic commits in Diffusion, run ".
            "'%s' from inside a working copy.",
            'arc browse'));
      }
    }

    foreach ($things as $thing) {
      $console->writeOut(
        "%s\n",
        pht(
          'Unable to find an object named **%s**, no such commit exists in '.
          'the remote, and no such path exists in the working copy. Use '.
          '__%s__ to treat this as a path anyway.',
          $thing,
          '--force'));
    }

    if ($uris) {
      $this->openURIsInBrowser($uris);
    }

    return 0;
  }

  private function getBaseURI() {
    $repo_uri = $this->getRepositoryURI();
    if ($repo_uri === null) {
      throw new ArcanistUsageException(
        pht(
          'arc is unable to determine which repository in Diffusion '.
          'this working copy belongs to. Use "%s" to understand how '.
          '%s looks for a repository.',
          'arc which',
          'arc'));
    }

    $branch = $this->getArgument('branch', 'master');
    $branch = phutil_escape_uri_path_component($branch);

    return $repo_uri.'browse/'.$branch.'/';
  }

}
