<?php

abstract class ArcanistWorkEngine
  extends ArcanistWorkflowEngine {

  private $symbolArgument;
  private $startArgument;

  final public function setSymbolArgument($symbol_argument) {
    $this->symbolArgument = $symbol_argument;
    return $this;
  }

  final public function getSymbolArgument() {
    return $this->symbolArgument;
  }

  final public function setStartArgument($start_argument) {
    $this->startArgument = $start_argument;
    return $this;
  }

  final public function getStartArgument() {
    return $this->startArgument;
  }

  final public function execute() {
    $workflow = $this->getWorkflow();
    $api = $this->getRepositoryAPI();

    $local_state = $api->newLocalState()
      ->setWorkflow($workflow)
      ->saveLocalState();

    $symbol = $this->getSymbolArgument();

    $markers = $api->newMarkerRefQuery()
      ->withNames(array($symbol))
      ->execute();

    if ($markers) {
      if (count($markers) > 1) {

        // TODO: This almost certainly means the symbol is a Mercurial branch
        // with multiple heads. We can pick some head.

        throw new PhutilArgumentUsageException(
          pht(
            'Symbol "%s" is ambiguous.',
            $symbol));
      }

      $target = head($markers);
      $this->moveToMarker($target);
      $local_state->discardLocalState();
      return;
    }

    $revision_marker = $this->workOnRevision($symbol);
    if ($revision_marker) {
      $this->moveToMarker($revision_marker);
      $local_state->discardLocalState();
      return;
    }

    $task_marker = $this->workOnTask($symbol);
    if ($task_marker) {
      $this->moveToMarker($task_marker);
      $local_state->discardLocalState();
      return;
    }

    // NOTE: We're resolving this symbol so we can raise an error message if
    // it's bogus, but we're using the symbol (not the resolved version) to
    // actually create the new marker. This matters in Git because it impacts
    // the behavior of "--track" when we pass a branch name.

    $start = $this->getStartArgument();
    if ($start !== null) {
      $start_commit = $api->getCanonicalRevisionName($start);
      if (!$start_commit) {
        throw new PhutilArgumentUsageException(
          pht(
            'Unable to resolve startpoint "%s".',
            $start));
      }
    } else {
      $start = $this->getDefaultStartSymbol();
    }

    $this->newMarker($symbol, $start);
    $local_state->discardLocalState();
  }

  abstract protected function newMarker($symbol, $start);
  abstract protected function moveToMarker(ArcanistMarkerRef $marker);
  abstract protected function getDefaultStartSymbol();

  private function workOnRevision($symbol) {
    $workflow = $this->getWorkflow();
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    try {
      $revision_symbol = id(new ArcanistRevisionSymbolRef())
        ->setSymbol($symbol);
    } catch (Exception $ex) {
      return;
    }

    $workflow->loadHardpoints(
      $revision_symbol,
      ArcanistSymbolRef::HARDPOINT_OBJECT);

    $revision_ref = $revision_symbol->getObject();
    if (!$revision_ref) {
      throw new PhutilArgumentUsageException(
        pht(
          'No revision "%s" exists, or you do not have permission to '.
          'view it.',
          $symbol));
    }

    $markers = $api->newMarkerRefQuery()
      ->execute();

    $state_refs = mpull($markers, 'getWorkingCopyStateRef');

    $workflow->loadHardpoints(
      $state_refs,
      ArcanistWorkingCopyStateRef::HARDPOINT_REVISIONREFS);

    $selected = array();
    foreach ($markers as $marker) {
      $state_ref = $marker->getWorkingCopyStateRef();
      $revision_refs = $state_ref->getRevisionRefs();
      $revision_refs = mpull($revision_refs, null, 'getPHID');

      if (isset($revision_refs[$revision_ref->getPHID()])) {
        $selected[] = $marker;
      }
    }

    if (!$selected) {

      // TODO: We could patch/load here.

      throw new PhutilArgumentUsageException(
        pht(
          'Revision "%s" was not found anywhere in this working copy.',
          $revision_ref->getMonogram()));
    }

    if (count($selected) > 1) {
      $selected = msort($selected, 'getEpoch');

      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('AMBIGUOUS MARKER'),
        pht(
          'More than one marker in the local working copy is associated '.
          'with the revision "%s", using the most recent one.',
          $revision_ref->getMonogram()));

      foreach ($selected as $marker) {
        echo tsprintf('%s', $marker->newRefView());
      }

      echo tsprintf("\n");

      $target = last($selected);
    } else {
      $target = head($selected);
    }

    $log->writeStatus(
      pht('REVISION'),
      pht('Resuming work on revision:'));

    echo tsprintf('%s', $revision_ref->newRefView());
    echo tsprintf("\n");

    return $target;
  }

  private function workOnTask($symbol) {
    $workflow = $this->getWorkflow();

    try {
      $task_symbol = id(new ArcanistTaskSymbolRef())
        ->setSymbol($symbol);
    } catch (Exception $ex) {
      return;
    }

    $workflow->loadHardpoints(
      $task_symbol,
      ArcanistSymbolRef::HARDPOINT_OBJECT);

    $task_ref = $task_symbol->getObject();
    if (!$task_ref) {
      throw new PhutilArgumentUsageException(
        pht(
          'No task "%s" exists, or you do not have permission to view it.',
          $symbol));
    }

    throw new Exception(pht('TODO: Implement this workflow.'));

    $this->loadHardpoints(
      $task_ref,
      ArcanistTaskRef::HARDPOINT_REVISIONREFS);
  }

}
