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
      'types' => array(
        'param' => 'types',
        'aliases' => array('type'),
        'help' => pht(
          'Parse arguments with particular types.'),
      ),
      'force' => array(
        'help' => pht(
          '(DEPRECATED) Obsolete, use "--types path" instead.'),
      ),
      '*' => 'targets',
    );
  }

  public function desiresWorkingCopy() {
    return true;
  }

  public function desiresRepositoryAPI() {
    return true;
  }

  public function run() {
    $conduit = $this->getConduitEngine();

    $console = PhutilConsole::getConsole();

    $targets = $this->getArgument('targets');
    if (!$targets) {
      throw new ArcanistUsageException(
        pht(
          'Specify one or more paths or objects to browse. Use the '.
          'command "%s" if you want to browse this directory.',
          'arc browse .'));
    }
    $targets = array_fuse($targets);

    if (!$targets) {
      $refs = array(
        new ArcanistBrowseRef(),
      );
    } else {
      $refs = array();
      foreach ($targets as $target) {
        $refs[] = id(new ArcanistBrowseRef())
          ->setToken($target);
      }
    }

    $is_force = $this->getArgument('force');
    if ($is_force) {
      // TODO: Remove this completely.
      $this->writeWarn(
        pht('DEPRECATED'),
        pht(
          'Argument "--force" for "arc browse" is deprecated. Use '.
          '"--type %s" instead.',
          ArcanistBrowsePathURIHardpointLoader::BROWSETYPE));
    }

    $types = $this->getArgument('types');
    if ($types !== null) {
      $types = preg_split('/[\s,]+/', $types);
    } else {
      if ($is_force) {
        $types = array(ArcanistBrowsePathURIHardpointLoader::BROWSETYPE);
      } else {
        $types = array();
      }
    }

    foreach ($refs as $ref) {
      $ref->setTypes($types);
    }

    $branch = $this->getArgument('branch');
    if ($branch) {
      foreach ($refs as $ref) {
        $ref->setBranch($branch);
      }
    }

    $loaders = ArcanistBrowseURIHardpointLoader::getAllBrowseLoaders();
    foreach ($loaders as $key => $loader) {
      $loaders[$key] = clone $loader;
    }

    $query = $this->newRefQuery($refs)
      ->needHardpoints(
        array(
          'uris',
        ))
      ->setLoaders($loaders);

    foreach ($loaders as $loader) {
      $loader->willLoadBrowseURIRefs($refs);
    }

    $query->execute();

    $zero_hits = array();
    $open_uris = array();
    $many_hits = array();
    foreach ($refs as $ref) {
      $uris = $ref->getURIs();
      if (!$uris) {
        $zero_hits[] = $ref;
      } else if (count($uris) == 1) {
        $open_uris[] = $ref;
      } else {
        $many_hits[] = $ref;
      }
    }

    if ($many_hits) {
      foreach ($many_hits as $ref) {
        $token = $ref->getToken();
        if (strlen($token)) {
          $message = pht('Argument "%s" is ambiguous.', $token);
        } else {
          $message = pht('Default behavior is ambiguous.');
        }

        $this->writeWarn(pht('AMBIGUOUS'), $message);
      }

      $table = id(new PhutilConsoleTable())
        ->addColumn('argument', array('title' => pht('Argument')))
        ->addColumn('type', array('title' => pht('Type')))
        ->addColumn('uri', array('title' => pht('URI')));

      foreach ($many_hits as $ref) {
        $token_display = $ref->getToken();
        if (!strlen($token)) {
          $token_display = pht('<default>');
        }

        foreach ($ref->getURIs() as $uri) {
          $row = array(
            'argument' => $token_display,
            'type' => $uri->getType(),
            'uri' => $uri->getURI(),
          );

          $table->addRow($row);
        }
      }

      $table->draw();

      $this->writeInfo(
        pht('CHOOSE'),
        pht('Use "--types" to select between alternatives.'));
    }

    // If anything failed to resolve, this is also an error.
    if ($zero_hits) {
      foreach ($zero_hits as $ref) {
        echo tsprintf(
          "%s\n",
          pht(
            'Unable to resolve argument "%s".',
            $ref->getToken()));
      }

      foreach ($loaders as $loader) {
        $loader->didFailToLoadBrowseURIRefs($refs);
      }
    }

    $uris = array();
    foreach ($open_uris as $ref) {
      $ref_uri = head($ref->getURIs());
      $uris[] = $ref_uri->getURI();
    }

    $this->openURIsInBrowser($uris);

    return 0;
  }

}
