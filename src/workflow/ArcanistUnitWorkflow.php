<?php

final class ArcanistUnitWorkflow
  extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'unit';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Run unit tests.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->addExample(pht('**unit** [__options__] __path__ __path__ ...'))
      ->addExample(pht('**unit** [__options__] --commit __commit__'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('commit')
        ->setParameter('commit'),
      $this->newWorkflowArgument('sink')
        ->setParameter('format'),
      $this->newWorkflowArgument('everything'),
      $this->newWorkflowArgument('paths')
        ->setWildcard(true),

      // TOOLSETS: Restore "--target".
    );
  }

  public function runWorkflow() {
    // If we're in a working copy, run tests from the working copy root.
    // Otherwise, run tests from the current working directory.

    $working_copy = $this->getWorkingCopy();
    if ($working_copy) {
      $directory = $working_copy->getPath();
    } else {
      $directory = getcwd();
    }

    $overseer = id(new ArcanistUnitOverseer())
      ->setDirectory($directory);

    // TOOLSETS: For now, we're treating every invocation of "arc unit" as
    // though it is "arc unit --everything", and ignoring the "--commit" flag
    // and "paths" arguments.

    $sinks = array();
    $sinks[] = $this->newUnitSink();
    $overseer->setSinks($sinks);

    $overseer->execute();

    foreach ($sinks as $sink) {
      $result = $sink->getOutput();
      if ($result !== null) {
        echo $result;
      }
    }

    return 0;
  }

  private function newUnitSink() {
    $sinks = ArcanistUnitSink::getAllUnitSinks();
    $sink_key = $this->getArgument('sink');
    if (!strlen($sink_key)) {
      $sink_key = ArcanistDefaultUnitSink::SINKKEY;
    }

    $sink = idx($sinks, $sink_key);
    if (!$sink) {
      throw new ArcanistUsageException(
        pht(
          'Unit test output sink ("%s") is unknown. Supported sinks '.
          'are: %s.',
          $sink_key,
          implode(', ', array_keys($sinks))));
    }

    return $sink;
  }

}
