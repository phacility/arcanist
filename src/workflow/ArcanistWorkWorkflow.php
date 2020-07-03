<?php

final class ArcanistWorkWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'work';
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('start')
        ->setParameter('symbol')
        ->setHelp(
          pht(
            'When creating a new branch or bookmark, use this as the '.
            'branch point.')),
      $this->newWorkflowArgument('symbol')
        ->setWildcard(true),
    );
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOHELP
Begin or resume work on a branch, bookmark, task, or revision.

The __symbol__ may be a branch or bookmark name, a revision name (like "D123"),
a task name (like "T123"), or a new symbol.

If you provide a symbol which currently does not identify any ongoing work,
Arcanist will create a new branch or bookmark with the name you provide.

If you provide the name of an existing branch or bookmark, Arcanist will switch
to that branch or bookmark.

If you provide the name of a revision or task, Arcanist will look for a related
branch or bookmark that exists in the working copy. If it finds one, it will
switch to it. If it does not find one, it will attempt to create a new branch
or bookmark.

When "arc work" creates a branch or bookmark, it will use **--start** as the
branchpoint if it is provided. Otherwise, the current working copy state will
serve as the starting point.
EOHELP
      );

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Begin or resume work.'))
      ->addExample(pht('**work** [--start __start__] __symbol__'))
      ->setHelp($help);
  }

  public function runWorkflow() {
    $api = $this->getRepositoryAPI();

    $work_engine = $api->getWorkEngine();
    if (!$work_engine) {
      throw new PhutilArgumentUsageException(
        pht(
          '"arc work" must be run in a Git or Mercurial working copy.'));
    }

    $argv = $this->getArgument('symbol');
    if (count($argv) === 0) {
      throw new PhutilArgumentUsageException(
        pht(
          'Provide a branch, bookmark, task, or revision name to begin '.
          'or resume work on.'));
    } else if (count($argv) === 1) {
      $symbol_argument = $argv[0];
      if (!strlen($symbol_argument)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Provide a nonempty symbol to begin or resume work on.'));
      }
    } else {
      throw new PhutilArgumentUsageException(
        pht(
          'Too many arguments: provide exactly one argument.'));
    }

    $start_argument = $this->getArgument('start');

    $work_engine
      ->setViewer($this->getViewer())
      ->setWorkflow($this)
      ->setLogEngine($this->getLogEngine())
      ->setSymbolArgument($symbol_argument)
      ->setStartArgument($start_argument)
      ->execute();

    return 0;
  }

}
