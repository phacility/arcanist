<?php

/**
 * Browse files or objects in the Phabricator web interface.
 */
final class ArcanistBrowseWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'browse';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Open a file or object (like a task or revision) in a local web browser.

  $ arc browse README   # Open a file in Diffusion.
  $ arc browse T123     # View a task.
  $ arc browse HEAD     # View a symbolic commit.

To choose a browser binary to invoke, use:

  $ arc set-config browser __browser-binary__

If no browser is set, the command will try to guess which browser to use.
EOTEXT
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Open a file or object in a local web browser.'))
      ->addExample('**browse** [options] -- __target__ ...')
      ->addExample('**browse** -- __file-name__')
      ->addExample('**browse** -- __object-name__')
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('branch')
        ->setParameter('branch-name')
        ->setHelp(
          pht(
            'Default branch name to view on server. Defaults to "%s".',
            'master')),
      $this->newWorkflowArgument('types')
        ->setParameter('type-list')
        ->setHelp(
          pht(
            'Force targets to be interpreted as naming particular types of '.
            'resources.')),
      $this->newWorkflowArgument('force')
        ->setHelp(
          pht(
            '(DEPRECATED) Obsolete, use "--types path" instead.')),
      $this->newWorkflowArgument('targets')
        ->setIsPathArgument(true)
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $targets = $this->getArgument('targets');
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
          ArcanistBrowsePathURIHardpointQuery::BROWSETYPE));
    }

    $types = $this->getArgument('types');
    if ($types !== null) {
      $types = preg_split('/[\s,]+/', $types);
    } else {
      if ($is_force) {
        $types = array(ArcanistBrowsePathURIHardpointQuery::BROWSETYPE);
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

    // TODO: The "Path" and "Commit" queries should regain the ability to warn
    // when this command is not run in a working copy that belongs to a
    // recognized repository, so they won't ever be able to resolve things.

    // TODO: When you run "arc browse" with no arguments, we should either
    // take you to the repository home page or show help.

    // TODO: When you "arc browse something/like/a/path.c" but it does not
    // exist on disk, it is not resolved unless you explicitly use "--type
    // path". This should be explained more clearly again.

    $this->loadHardpoints(
      $refs,
      ArcanistBrowseRef::HARDPOINT_URIS);

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

    $pick_map = array();
    $pick_selection = null;
    $pick_id = 0;
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

      $is_single_ref = (count($refs) == 1);

      $table = id(new PhutilConsoleTable());

      if ($is_single_ref) {
        $table->addColumn('pick', array('title' => pht('Pick')));
      } else {
        $table->addColumn('argument', array('title' => pht('Argument')));
      }

      $table
        ->addColumn('type', array('title' => pht('Type')))
        ->addColumn('uri', array('title' => pht('URI')));

      foreach ($many_hits as $ref) {
        $token_display = $ref->getToken();
        if (!strlen($token)) {
          $token_display = pht('<default>');
        }

        foreach ($ref->getURIs() as $uri) {
          ++$pick_id;
          $pick_map[$pick_id] = $uri;

          $row = array(
            'pick' => $pick_id,
            'argument' => $token_display,
            'type' => $uri->getType(),
            'uri' => $uri->getURI(),
          );

          $table->addRow($row);
        }
      }

      $table->draw();

      if ($is_single_ref) {
        $pick_selection = phutil_console_select(
          pht('Which URI do you want to open?'),
          1,
          $pick_id);
        $open_uris[] = $ref;
      } else {
        $this->writeInfo(
          pht('CHOOSE'),
          pht('Use "--types" to select between alternatives.'));
      }
    }

    // If anything failed to resolve, this is also an error.
    if ($zero_hits) {
      foreach ($zero_hits as $ref) {
        $token = $ref->getToken();
        if ($token === null) {
          echo tsprintf(
            "%s\n",
            pht(
              'Unable to resolve default browse target.'));
        } else {
          echo tsprintf(
            "%s\n",
            pht(
              'Unable to resolve argument "%s".',
              $ref->getToken()));
        }
      }
    }

    $uris = array();
    foreach ($open_uris as $ref) {
      $ref_uris = $ref->getURIs();

      if (count($ref_uris) > 1) {
        foreach ($ref_uris as $uri_key => $uri) {
          if ($pick_map[$pick_selection] !== $uri) {
            unset($ref_uris[$uri_key]);
          }
        }
      }

      $ref_uri = head($ref_uris);

      $raw_uri = $ref_uri->getURI();
      $raw_uri = $this->getAbsoluteURI($raw_uri);

      $uris[] = $raw_uri;
    }

    $this->openURIsInBrowser($uris);

    return 0;
  }

}
