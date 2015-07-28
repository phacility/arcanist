<?php

/**
 * Runtime workflow configuration. In Arcanist, commands you type like
 * "arc diff" or "arc lint" are called "workflows". This class allows you to add
 * new workflows (and extend existing workflows) by subclassing it and then
 * pointing to your subclass in your project configuration.
 *
 * When specified as the **arcanist_configuration** class in your project's
 * ##.arcconfig##, your subclass will be instantiated (instead of this class)
 * and be able to handle all the method calls. In particular, you can:
 *
 *    - create, replace, or disable workflows by overriding buildWorkflow()
 *      and buildAllWorkflows();
 *    - add additional steps before or after workflows run by overriding
 *      willRunWorkflow() or didRunWorkflow() or didAbortWorkflow(); and
 *    - add new flags to existing workflows by overriding
 *      getCustomArgumentsForCommand().
 *
 * @concrete-extensible
 */
class ArcanistConfiguration extends Phobject {

  public function buildWorkflow($command) {
    if ($command == '--help') {
      // Special-case "arc --help" to behave like "arc help" instead of telling
      // you to type "arc help" without being helpful.
      $command = 'help';
    } else if ($command == '--version') {
      // Special-case "arc --version" to behave like "arc version".
      $command = 'version';
    }

    return idx($this->buildAllWorkflows(), $command);
  }

  public function buildAllWorkflows() {
    $workflows_by_name = array();

    $workflows_by_class_name = id(new PhutilSymbolLoader())
      ->setAncestorClass('ArcanistWorkflow')
      ->loadObjects();
    foreach ($workflows_by_class_name as $class => $workflow) {
      $name = $workflow->getWorkflowName();

      if (isset($workflows_by_name[$name])) {
        $other = get_class($workflows_by_name[$name]);
        throw new Exception(
          pht(
            'Workflows %s and %s both implement workflows named %s.',
            $class,
            $other,
            $name));
      }

      $workflows_by_name[$name] = $workflow;
    }

    return $workflows_by_name;
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

    // If the user has an alias, like 'arc alias dhelp diff help', look it up
    // and substitute it. We do this only after trying to resolve the workflow
    // normally to prevent you from doing silly things like aliasing 'alias'
    // to something else.
    $aliases = ArcanistAliasWorkflow::getAliases($configuration_manager);
    list($new_command, $args) = ArcanistAliasWorkflow::resolveAliases(
      $command,
      $this,
      $args,
      $configuration_manager);

    $full_alias = idx($aliases, $command, array());
    $full_alias = implode(' ', $full_alias);

    // Run shell command aliases.
    if (ArcanistAliasWorkflow::isShellCommandAlias($new_command)) {
      $shell_cmd = substr($full_alias, 1);

      $console->writeLog(
        "[%s: 'arc %s' -> $ %s]",
        pht('alias'),
        $command,
        $shell_cmd);

      if ($args) {
        $err = phutil_passthru('%C %Ls', $shell_cmd, $args);
      } else {
        $err = phutil_passthru('%C', $shell_cmd);
      }

      exit($err);
    }

    // Run arc command aliases.
    if ($new_command) {
      $workflow = $this->buildWorkflow($new_command);
      if ($workflow) {
        $console->writeLog(
          "[%s: 'arc %s' -> 'arc %s']\n",
          pht('alias'),
          $command,
          $full_alias);
        $command = $new_command;
        return $workflow;
      }
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
    $corrected = self::correctCommandSpelling($command, $all, 2);
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

  public static function correctCommandSpelling(
    $command,
    array $options,
    $max_distance) {

    // Adjust to the scaled edit costs we use below, so "2" roughly means
    // "2 edits".
    $max_distance = $max_distance * 3;

    // These costs are somewhat made up, but the theory is that it is far more
    // likely you will mis-strike a key ("lans" for "land") or press two keys
    // out of order ("alnd" for "land") than omit keys or press extra keys.
    $matrix = id(new PhutilEditDistanceMatrix())
      ->setInsertCost(4)
      ->setDeleteCost(4)
      ->setReplaceCost(3)
      ->setTransposeCost(2);

    return self::correctSpelling($command, $options, $matrix, $max_distance);
  }

  public static function correctArgumentSpelling($command, array $options) {
    $max_distance = 1;

    // We are stricter with arguments - we allow only one inserted or deleted
    // character. It is mainly to handle cases like --no-lint versus --nolint
    // or --reviewer versus --reviewers.
    $matrix = id(new PhutilEditDistanceMatrix())
      ->setInsertCost(1)
      ->setDeleteCost(1)
      ->setReplaceCost(10);

    return self::correctSpelling($command, $options, $matrix, $max_distance);
  }

  public static function correctSpelling(
    $input,
    array $options,
    PhutilEditDistanceMatrix $matrix,
    $max_distance) {

    $distances = array();
    $inputv = str_split($input);
    foreach ($options as $option) {
      $optionv = str_split($option);
      $matrix->setSequences($optionv, $inputv);
      $distances[$option] = $matrix->getEditDistance();
    }

    asort($distances);
    $best = min($max_distance, reset($distances));
    foreach ($distances as $option => $distance) {
      if ($distance > $best) {
        unset($distances[$option]);
      }
    }

    // Before filtering, check if we have multiple equidistant matches and
    // return them if we do. This prevents us from, e.g., matching "alnd" with
    // both "land" and "amend", then dropping "land" for being too short, and
    // incorrectly completing to "amend".
    if (count($distances) > 1) {
      return array_keys($distances);
    }

    foreach ($distances as $option => $distance) {
      if (strlen($option) < $distance) {
        unset($distances[$option]);
      }
    }

    return array_keys($distances);
  }

}
