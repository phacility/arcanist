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
      $this->newWorkflowArgument('format')
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

    $formatter = $this->newUnitFormatter();
    $overseer->setFormatter($formatter);

    $overseer->execute();

    return 0;
  }

  private function newUnitFormatter() {
    $formatters = ArcanistUnitFormatter::getAllUnitFormatters();
    $format_key = $this->getArgument('format');
    if (!strlen($format_key)) {
      $format_key = ArcanistDefaultUnitFormatter::FORMATTER_KEY;
    }

    $formatter = idx($formatters, $format_key);
    if (!$formatter) {
      throw new ArcanistUsageException(
        pht(
          'Unit test output format ("%s") is unknown. Supported formats '.
          'are: %s.',
          $format_key,
          implode(', ', array_keys($formatters))));
    }

    return $formatter;
  }

}
