<?php

/**
 * Manages aliases for commands with options.
 */
final class ArcanistAliasWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'alias';
  }

  public function supportsToolset(ArcanistToolset $toolset) {
    return true;
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Create an alias from __command__ to __target__ (optionally, with __options__).

Aliases allow you to create shorthands for commands and sets of flags you
commonly use, like defining "arc draft" as a shorthand for "arc diff --draft".

**Creating Aliases**

You can define "arc draft" as a shorthand for "arc diff --draft" like this:

  $ arc alias draft diff -- --draft

Now, when you run "arc draft", the command will function like
"arc diff --draft".

<bg:yellow> NOTE: </bg> Make sure you use "--" before specifying any flags you
want to pass to the command! Otherwise, the flags will be interpreted as flags
to "arc alias".

**Listing Aliases**

Without any arguments, "arc alias" will list aliases.

**Removing Aliases**

To remove an alias, run:

  $ arc alias <alias-name>

You will be prompted to remove the alias.

**Shell Commands**

If you begin an alias with "!", the remainder of the alias will be invoked as
a shell command. For example, if you want to implement "arc ls", you can do so
like this:

  $ arc alias ls '!ls'

When run, "arc ls" will now behave like "ls".

**Multiple Toolsets**

This workflow supports any toolset, even though the examples in this help text
use "arc". If you are working with another toolset, use the binary for that
toolset define aliases for it:

  $ phage alias ...

Aliases are bound to the toolset which was used to define them. If you define
an "arc draft" alias, that does not also define a "phage draft" alias.

**Builtins**

You can not overwrite the behavior of builtin workflows, including "alias"
itself, and if you install a new workflow it will take precedence over any
existing aliases with the same name.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->setSynopsis(
        pht('Create and modify command aliases.'))
      ->addExample(pht('**alias**'))
      ->addExample(pht('**alias** __command__ __target__ -- [__arguments__]'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('json')
        ->setHelp(pht('Output aliases in JSON format.')),
      $this->newWorkflowArgument('argv')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $argv = $this->getArgument('argv');

    $is_list = false;
    $is_delete = false;

    if (!$argv) {
      $is_list = true;
    } else if (count($argv) === 1) {
      $is_delete = true;
    }

    $is_json = $this->getArgument('json');
    if ($is_json && !$is_list) {
      throw new PhutilArgumentUsageException(
        pht(
          'The "--json" argument may only be used when listing aliases.'));
    }

    if ($is_list) {
      return $this->runListAliases();
    }

    if ($is_delete) {
      return $this->runDeleteAlias($argv[0]);
    }

    return $this->runCreateAlias($argv);
  }

  private function runListAliases() {
    // TOOLSETS: Actually list aliases.
    return 1;
  }

  private function runDeleteAlias($alias) {
    // TOOLSETS: Actually delete aliases.
    return 1;
  }

  private function runCreateAlias(array $argv) {
    $trigger = array_shift($argv);
    $this->validateAliasTrigger($trigger);

    $alias = id(new ArcanistAlias())
      ->setToolset($this->getToolsetKey())
      ->setTrigger($trigger)
      ->setCommand($argv);

    $aliases = $this->readAliasesForWrite();

    // TOOLSETS: Check if the user already has an alias for this trigger, and
    // prompt them to overwrite it. Needs prompting to work.

    // TOOLSETS: Don't let users set aliases which don't resolve to anything.

    $aliases[] = $alias;

    $this->writeAliases($aliases);

    // TOOLSETS: Print out a confirmation that we added the alias.

    return 0;
  }

  private function validateAliasTrigger($trigger) {
    $workflows = $this->getRuntime()->getWorkflows();

    if (isset($workflows[$trigger])) {
      throw new PhutilArgumentUsageException(
        pht(
          'You can not define an alias for "%s" because it is a builtin '.
          'workflow for the current toolset ("%s"). The "alias" workflow '.
          'can only define new commands as aliases; it can not redefine '.
          'existing commands to mean something else.',
          $trigger,
          $this->getToolsetKey()));
    }
  }

  private function getEditScope() {
    return ArcanistConfigurationSource::SCOPE_USER;
  }

  private function getAliasesConfigKey() {
    return ArcanistArcConfigurationEngineExtension::KEY_ALIASES;
  }

  private function readAliasesForWrite() {
    $key = $this->getAliasesConfigKey();
    $scope = $this->getEditScope();
    $source_list = $this->getConfigurationSourceList();

    return $source_list->getConfigFromScopes($key, array($scope));
  }

  private function writeAliases(array $aliases) {
    assert_instances_of($aliases, 'ArcanistAlias');

    $key = $this->getAliasesConfigKey();
    $scope = $this->getEditScope();

    $source_list = $this->getConfigurationSourceList();
    $source = $source_list->getWritableSourceFromScope($scope);
    $option = $source_list->getConfigOption($key);

    $option->writeValue($source, $aliases);
  }

}
