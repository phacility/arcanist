<?php

/**
 * Powers shell-completion scripts.
 */
final class ArcanistShellCompleteWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'shell-complete';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **shell-complete** __--current__ __N__ -- [__argv__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: bash, etc.
          Implements shell completion. To use shell completion, source the
          appropriate script from 'resources/shell/' in your .shellrc.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'current' => array(
        'param' => 'cursor_position',
        'paramtype' => 'int',
        'help' => pht('Current term in the argument list being completed.'),
      ),
      '*' => 'argv',
    );
  }

  protected function shouldShellComplete() {
    return false;
  }

  public function run() {
    $pos  = $this->getArgument('current');
    $argv = $this->getArgument('argv', array());
    $argc = count($argv);
    if ($pos === null) {
      $pos = $argc - 1;
    }

    if ($pos > $argc) {
      throw new ArcanistUsageException(
        pht(
          'Specified position is greater than the number of '.
          'arguments provided.'));
    }

    // Determine which revision control system the working copy uses, so we
    // can filter out commands and flags which aren't supported. If we can't
    // figure it out, just return all flags/commands.
    $vcs = null;

    // We have to build our own because if we requiresWorkingCopy() we'll throw
    // if we aren't in a .arcconfig directory. We probably still can't do much,
    // but commands can raise more detailed errors.
    $configuration_manager = $this->getConfigurationManager();
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath(getcwd());
    if ($working_copy->getVCSType()) {
      $configuration_manager->setWorkingCopyIdentity($working_copy);
      $repository_api = ArcanistRepositoryAPI::newAPIFromConfigurationManager(
        $configuration_manager);

      $vcs = $repository_api->getSourceControlSystemName();
    }

    $arc_config = $this->getArcanistConfiguration();

    if ($pos <= 1) {
      $workflows = $arc_config->buildAllWorkflows();

      $complete = array();
      foreach ($workflows as $name => $workflow) {
        if (!$workflow->shouldShellComplete()) {
          continue;
        }

        $workflow->setArcanistConfiguration($this->getArcanistConfiguration());
        $workflow->setConfigurationManager($this->getConfigurationManager());

        if ($vcs || $workflow->requiresWorkingCopy()) {
          $supported_vcs = $workflow->getSupportedRevisionControlSystems();
          if (!in_array($vcs, $supported_vcs)) {
            continue;
          }
        }

        $complete[] = $name;
      }

      // Also permit autocompletion of "arc alias" commands.
      $aliases =  ArcanistAliasWorkflow::getAliases($configuration_manager);
      foreach ($aliases as $key => $value) {
        $complete[] = $key;
      }

      echo implode(' ', $complete)."\n";
      return 0;
    } else {
      $workflow = $arc_config->buildWorkflow($argv[1]);
      if (!$workflow) {
        list($new_command, $new_args) = ArcanistAliasWorkflow::resolveAliases(
          $argv[1],
          $arc_config,
          array_slice($argv, 2),
          $configuration_manager);
        if ($new_command) {
          $workflow = $arc_config->buildWorkflow($new_command);
        }
        if (!$workflow) {
          return 1;
        } else {
          $argv = array_merge(
            array($argv[0]),
            array($new_command),
            $new_args);
        }
      }

      $arguments = $workflow->getArguments();

      $prev = idx($argv, $pos - 1, null);
      if (!strncmp($prev, '--', 2)) {
        $prev = substr($prev, 2);
      } else {
        $prev = null;
      }

      if ($prev !== null &&
          isset($arguments[$prev]) &&
          isset($arguments[$prev]['param'])) {

        $type = idx($arguments[$prev], 'paramtype');
        switch ($type) {
          case 'file':
            echo "FILE\n";
            break;
          case 'complete':
            echo implode(' ', $workflow->getShellCompletions($argv))."\n";
            break;
          default:
            echo "ARGUMENT\n";
            break;
        }
        return 0;
      } else {
        $output = array();
        foreach ($arguments as $argument => $spec) {
          if ($argument == '*') {
            continue;
          }
          if ($vcs &&
              isset($spec['supports']) &&
              !in_array($vcs, $spec['supports'])) {
            continue;
          }
          $output[] = '--'.$argument;
        }

        $cur = idx($argv, $pos, '');
        $any_match = false;

        if (strlen($cur)) {
          foreach ($output as $possible) {
            if (!strncmp($possible, $cur, strlen($cur))) {
              $any_match = true;
            }
          }
        }

        if (!$any_match && isset($arguments['*'])) {
          // TODO: This is mega hacktown but something else probably breaks
          // if we use a rich argument specification; fix it when we move to
          // PhutilArgumentParser since everything will need to be tested then
          // anyway.
          if ($arguments['*'] == 'branch' && isset($repository_api)) {
            $branches = $repository_api->getAllBranches();
            $branches = ipull($branches, 'name');
            $output = $branches;
          } else {
            $output = array('FILE');
          }
        }

        echo implode(' ', $output)."\n";

        return 0;
      }
    }
  }

}
