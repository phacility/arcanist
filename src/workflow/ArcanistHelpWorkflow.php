<?php

/**
 * Seduces the reader with majestic prose.
 */
final class ArcanistHelpWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'help';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **help** [__command__]
      **help** --full
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: english
          Shows this help. With __command__, shows help about a specific
          command.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'full' => array(
        'help' => pht('Print detailed information about each command.'),
      ),
      '*' => 'command',
    );
  }

  public function run() {

    $arc_config = $this->getArcanistConfiguration();
    $workflows = $arc_config->buildAllWorkflows();
    ksort($workflows);

    $target = null;
    if ($this->getArgument('command')) {
      $target = head($this->getArgument('command'));
      if (empty($workflows[$target])) {
        throw new ArcanistUsageException(
          pht(
            "Unrecognized command '%s'. Try '%s'.",
            $target,
            'arc help'));
      }
    }

    $cmdref = array();
    foreach ($workflows as $command => $workflow) {
      if ($target && $target != $command) {
        continue;
      }
      if (!$target && !$this->getArgument('full')) {
        $cmdref[] = $workflow->getCommandSynopses();
        continue;
      }
      $optref = array();
      $arguments = $workflow->getArguments();

      $config_arguments = $arc_config->getCustomArgumentsForCommand($command);

      // This juggling is to put the extension arguments after the normal
      // arguments, and make sure the normal arguments aren't overwritten.
      ksort($arguments);
      ksort($config_arguments);
      foreach ($config_arguments as $argument => $spec) {
        if (empty($arguments[$argument])) {
          $arguments[$argument] = $spec;
        }
      }

      foreach ($arguments as $argument => $spec) {
        if ($argument == '*') {
          continue;
        }
        if (!empty($spec['hide'])) {
          continue;
        }
        if (isset($spec['param'])) {
          if (isset($spec['short'])) {
            $optref[] = phutil_console_format(
              '          __--%s__ __%s__, __-%s__ __%s__',
              $argument,
              $spec['param'],
              $spec['short'],
              $spec['param']);
          } else {
            $optref[] = phutil_console_format(
              '          __--%s__ __%s__',
              $argument,
              $spec['param']);
          }
        } else {
          if (isset($spec['short'])) {
            $optref[] = phutil_console_format(
              '          __--%s__, __-%s__',
              $argument,
              $spec['short']);
          } else {
            $optref[] = phutil_console_format(
              '          __--%s__',
              $argument);
          }
        }

        if (isset($config_arguments[$argument])) {
          $optref[] = '              '.
            pht('(This is a custom option for this project.)');
        }

        if (isset($spec['supports'])) {
          $optref[] = '              '.
            pht('Supports: %s', implode(', ', $spec['supports']));
        }

        if (isset($spec['help'])) {
          $docs = $spec['help'];
        } else {
          $docs = pht('This option is not documented.');
        }
        $docs = phutil_console_wrap($docs, 14);
        $optref[] = "{$docs}\n";
      }
      if ($optref) {
        $optref = implode("\n", $optref);
        $optref = "\n\n".$optref;
      } else {
        $optref = "\n";
      }

      $cmdref[] =
        $workflow->getCommandSynopses()."\n".
        $workflow->getCommandHelp().
        $optref;
    }
    $cmdref = implode("\n\n", $cmdref);

    if ($target) {
      echo "\n".$cmdref."\n";
      return;
    }

    $self = 'arc';
    echo phutil_console_format(<<<EOTEXT
**NAME**
      **{$self}** - arcanist, a code review and revision management utility

**SYNOPSIS**
      **{$self}** __command__ [__options__] [__args__]
      This help file provides a detailed command reference.

**COMMAND REFERENCE**

{$cmdref}


EOTEXT
    );

    if (!$this->getArgument('full')) {
      echo pht(
        "Run '%s' to get commands and options descriptions.\n",
        'arc help --full');
      return;
    }

    echo phutil_console_format(<<<EOTEXT
**OPTION REFERENCE**

      __--trace__
          Debugging command. Shows underlying commands as they are executed,
          and full stack traces when exceptions are thrown.

      __--no-ansi__
          Output in plain ASCII text only, without color or style.

      __--ansi__
          Use formatting even in environments which probably don't support it.
          Example: arc --ansi unit | less -r

      __--load-phutil-library=/path/to/library__
          Ignore libraries listed in .arcconfig and explicitly load specified
          libraries instead. Mostly useful for Arcanist development.

      __--conduit-uri__ __uri__
          Ignore configured Conduit URI and use an explicit one instead. Mostly
          useful for Arcanist development.

      __--conduit-token__ __token__
          Ignore configured credentials and use an explicit API token instead.

      __--conduit-version__ __version__
          Ignore software version and claim to be running some other version
          instead. Mostly useful for Arcanist development. May cause bad things
          to happen.

      __--conduit-timeout__ __timeout__
          Override the default Conduit timeout. Specified in seconds.

      __--config__ __key=value__
          Specify a runtime configuration value. This will take precedence
          over static values, and only affect the current arcanist invocation.

      __--skip-arcconfig__
          Skip the working copy configuration file

      __--arcrc-file__ __filename__
          Use provided file instead of ~/.arcrc.

EOTEXT
    );
  }
}
