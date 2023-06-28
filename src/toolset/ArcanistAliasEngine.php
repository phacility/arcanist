<?php

final class ArcanistAliasEngine
  extends Phobject {

  private $runtime;
  private $toolset;
  private $workflows;
  private $configurationSourceList;

  public function setRuntime(ArcanistRuntime $runtime) {
    $this->runtime = $runtime;
    return $this;
  }

  public function getRuntime() {
    return $this->runtime;
  }

  public function setToolset(ArcanistToolset $toolset) {
    $this->toolset = $toolset;
    return $this;
  }

  public function getToolset() {
    return $this->toolset;
  }

  public function setWorkflows(array $workflows) {
    assert_instances_of($workflows, 'ArcanistWorkflow');
    $this->workflows = $workflows;
    return $this;
  }

  public function getWorkflows() {
    return $this->workflows;
  }

  public function setConfigurationSourceList(
    ArcanistConfigurationSourceList $config) {
    $this->configurationSourceList = $config;
    return $this;
  }

  public function getConfigurationSourceList() {
    return $this->configurationSourceList;
  }

  public function resolveAliases(array $argv) {
    $aliases_key = ArcanistArcConfigurationEngineExtension::KEY_ALIASES;
    $source_list = $this->getConfigurationSourceList();
    $aliases = $source_list->getConfig($aliases_key);

    $results = array();

    // Identify aliases which had some kind of format or specification issue
    // when loading config. We could possibly do this earlier, but it's nice
    // to handle all the alias stuff in one place.

    foreach ($aliases as $key => $alias) {
      $exception = $alias->getException();

      if (!$exception) {
        continue;
      }

      // This alias is not defined properly, so we're going to ignore it.
      unset($aliases[$key]);

      $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_CONFIGURATION)
        ->setMessage(
          pht(
            'Configuration source ("%s") defines an invalid alias, which '.
            'will be ignored: %s',
            $alias->getConfigurationSource()->getSourceDisplayName(),
            $exception->getMessage()));
    }

    $command = array_shift($argv);

    $stack = array();
    return $this->resolveAliasesForCommand(
      $aliases,
      $command,
      $argv,
      $results,
      $stack);
  }

  private function resolveAliasesForCommand(
    array $aliases,
    $command,
    array $argv,
    array $results,
    array $stack) {

    $toolset = $this->getToolset();
    $toolset_key = $toolset->getToolsetKey();

    // If we have a command which resolves to a real workflow, match it and
    // finish resolution. You can not overwrite a real workflow with an alias.

    $workflows = $this->getWorkflows();
    if (isset($workflows[$command])) {
      $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_RESOLUTION)
        ->setCommand($command)
        ->setArguments($argv);
      return $results;
    }

    // Find all the aliases which match whatever the user typed, like "draft".
    // We look for aliases in other toolsets, too, so we can provide the user
    // a hint when they type "phage draft" and mean "arc draft".

    $matches = array();
    $toolset_matches = array();
    foreach ($aliases as $alias) {
      if ($alias->getTrigger() === $command) {
        $matches[] = $alias;
        if ($alias->getToolset() == $toolset_key) {
          $toolset_matches[] = $alias;
        }
      }
    }

    if (!$toolset_matches) {

      // If the user typed "phage draft" and meant "arc draft", give them a
      // hint that the alias exists somewhere else and they may have specified
      // the wrong toolset.

      foreach ($matches as $match) {
        $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_SUGGEST)
          ->setMessage(
            pht(
              'No "%s %s" alias is defined, did you mean "%s %s"?',
              $toolset_key,
              $command,
              $match->getToolset(),
              $command));
      }

      // If the user misspells a command (like "arc hlep") and it doesn't match
      // anything (no alias or workflow), we want to pass it through unmodified
      // and let the parser try to correct the spelling into a real workflow
      // later on.

      // However, if the user correctly types a command (like "arc draft") that
      // resolves at least once (so it hits a valid alias) but does not
      // ultimately resolve into a valid workflow, we want to treat this as a
      // hard failure.

      // This could happen if you manually defined a bad alias, or a workflow
      // you'd previously aliased to was removed, or you stacked aliases and
      // then deleted one.

      if ($stack) {
        $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_NOTFOUND)
          ->setMessage(
            pht(
              'Alias resolved to "%s", but this is not a valid workflow or '.
              'alias name. This alias or workflow might have previously '.
              'existed and been removed.',
              $command));
      } else {
        $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_RESOLUTION)
          ->setCommand($command)
          ->setArguments($argv);
      }

      return $results;
    }

    $alias = array_pop($toolset_matches);

    if ($toolset_matches) {
      $source = $alias->getConfigurationSource();

      $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_IGNORED)
        ->setMessage(
          pht(
            'Multiple configuration sources define an alias for "%s %s". '.
            'The last definition in the most specific source ("%s") will '.
            'be used.',
            $toolset_key,
            $command,
            $source->getSourceDisplayName()));

      foreach ($toolset_matches as $ignored_match) {
        $source = $ignored_match->getConfigurationSource();

        $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_IGNORED)
          ->setMessage(
            pht(
              'A definition of "%s %s" in "%s" will be ignored.',
              $toolset_key,
              $command,
              $source->getSourceDisplayName()));
      }
    }

    $alias_argv = $alias->getCommand();
    $alias_command = array_shift($alias_argv);

    if ($alias->isShellCommandAlias()) {
      $shell_command = substr($alias_command, 1);

      $shell_argv = array_merge(
        array($shell_command),
        $alias_argv,
        $argv);

      $shell_display = csprintf('%Ls', $shell_argv);

      $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_SHELL)
        ->setMessage(
          pht(
            '%s %s -> $ %s',
            $toolset_key,
            $command,
            $shell_display))
        ->setArguments($shell_argv);

      return $results;
    }

    if (isset($stack[$alias_command])) {

      $cycle = array_keys($stack);
      $cycle[] = $alias_command;
      $cycle = implode(' -> ', $cycle);

      $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_CYCLE)
        ->setMessage(
          pht(
            'Alias definitions form a cycle which can not be resolved: %s.',
            $cycle));

      return $results;
    }

    $stack[$alias_command] = true;

    $stack_limit = 16;
    if (count($stack) >= $stack_limit) {
      $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_STACK)
        ->setMessage(
          pht(
            'Alias definitions form an unreasonably deep stack. A chain of '.
            'aliases may not resolve more than %s times.',
            new PhutilNumber($stack_limit)));
      return $results;
    }

    $display_argv = (string)csprintf('%LR', $alias_argv);

    $results[] = $this->newEffect(ArcanistAliasEffect::EFFECT_ALIAS)
      ->setMessage(
        pht(
          '%s %s -> %s %s %s',
          $toolset_key,
          $command,
          $toolset_key,
          $alias_command,
          $display_argv));

    $argv = array_merge($alias_argv, $argv);

    return $this->resolveAliasesForCommand(
      $aliases,
      $alias_command,
      $argv,
      $results,
      $stack);
  }

  protected function newEffect($effect_type) {
    return id(new ArcanistAliasEffect())
      ->setType($effect_type);
  }

}
