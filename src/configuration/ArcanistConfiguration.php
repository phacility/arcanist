<?php

/**
 * Runtime workflow configuration. In Arcanist, commands you type like
 * "arc diff" or "arc lint" are called "workflows". This class allows you to add
 * new workflows (and extend existing workflows) by subclassing it and then
 * pointing to your subclass in your project configuration.
 *
 * When specified as the **arcanist_configuration** class in your project's
 * `.arcconfig`, your subclass will be instantiated (instead of this class)
 * and be able to handle all the method calls. In particular, you can:
 *
 *    - create, replace, or disable workflows by overriding `buildWorkflow()`
 *      and `buildAllWorkflows()`;
 *    - add additional steps before or after workflows run by overriding
 *      `willRunWorkflow()` or `didRunWorkflow()` or `didAbortWorkflow()`; and
 *    - add new flags to existing workflows by overriding
 *      `getCustomArgumentsForCommand()`.
 *
 * @concrete-extensible
 */
class ArcanistConfiguration extends Phobject {

  public function buildWorkflow($command) {
    if ($command == '--help') {
      // Special-case "arc --help" to behave like "arc help" instead of telling
      // you to type "arc help" without being helpful.
      $command = 'help';
    }

    $workflow = idx($this->buildAllWorkflows(), $command);

    if (!$workflow) {
      return null;
    }

    return clone $workflow;
  }

  public function buildAllWorkflows() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass('ArcanistWorkflow')
      ->setUniqueMethod('getWorkflowName')
      ->execute();
  }

  final public function isValidWorkflow($workflow) {
    return (bool)$this->buildWorkflow($workflow);
  }

  public function willRunWorkflow($command, ArcanistWorkflow $workflow) {
    // This is a hook.
  }

  public function didRunWorkflow($command, ArcanistWorkflow $workflow, $err) {

    // This is a hook.
  }

  public function didAbortWorkflow($command, $workflow, Exception $ex) {
    // This is a hook.
  }

  public function getCustomArgumentsForCommand($command) {
    return array();
  }

  final public function selectWorkflow(
    &$command,
    array &$args,
    ArcanistConfigurationManager $configuration_manager,
    PhutilConsole $console) {

    // First, try to build a workflow with the exact name provided. We always
    // pick an exact match, and do not allow aliases to override it.
    $workflow = $this->buildWorkflow($command);
    if ($workflow) {
      return $workflow;
    }

    $all = array_keys($this->buildAllWorkflows());

    // We haven't found a real command or an alias, so try to locate a command
    // by unique prefix.
    $prefixes = $this->expandCommandPrefix($command, $all);

    if (count($prefixes) == 1) {
      $command = head($prefixes);
      return $this->buildWorkflow($command);
    } else if (count($prefixes) > 1) {
      $this->raiseUnknownCommand($command, $prefixes);
    }


    // We haven't found a real command, alias, or unique prefix. Try similar
    // spellings.
    $corrected = PhutilArgumentSpellingCorrector::newCommandCorrector()
      ->correctSpelling($command, $all);
    if (count($corrected) == 1) {
      $console->writeErr(
        pht(
          "(Assuming '%s' is the British spelling of '%s'.)",
          $command,
          head($corrected))."\n");
      $command = head($corrected);
      return $this->buildWorkflow($command);
    } else if (count($corrected) > 1) {
      $this->raiseUnknownCommand($command, $corrected);
    }

    $this->raiseUnknownCommand($command);
  }

  private function raiseUnknownCommand($command, array $maybe = array()) {
    $message = pht("Unknown command '%s'. Try '%s'.", $command, 'arc help');
    if ($maybe) {
      $message .= "\n\n".pht('Did you mean:')."\n";
      sort($maybe);
      foreach ($maybe as $other) {
        $message .= "    ".$other."\n";
      }
    }
    throw new ArcanistUsageException($message);
  }

  private function expandCommandPrefix($command, array $options) {
    $is_prefix = array();
    foreach ($options as $option) {
      if (strncmp($option, $command, strlen($command)) == 0) {
        $is_prefix[$option] = true;
      }
    }

    return array_keys($is_prefix);
  }

}
