<?php

final class ArcanistShellCompleteWorkflow
  extends ArcanistWorkflow {

  public function supportsToolset(ArcanistToolset $toolset) {
    return true;
  }

  public function getWorkflowName() {
    return 'shell-complete';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Install shell completion so you can use the "tab" key to autocomplete
commands and flags in your shell for Arcanist toolsets and workflows.

The **bash** shell is supported.

**Installing Completion**

To install shell completion, run the command:

  $ arc shell-complete

This will install shell completion into your current shell. After installing,
you may need to start a new shell (or open a new terminal window) to pick up
the updated configuration.

Once installed, completion should work across all Arcanist toolsets.

**Using Completion**

After completion is installed, use the "tab" key to automatically complete
workflows and flags. For example, if you type:

  $ arc diff --draf<tab>

...your shell should automatically expand the flag to:

  $ arc diff --draft

**Updating Completion**

To update shell completion, run the same command:

  $ arc shell-complete

You can update shell completion without reinstalling it by running:

  $ arc shell-complete --generate

You may need to update shell completion if:

  - you install new Arcanist toolsets; or
  - you move the Arcanist directory; or
  - you upgrade Arcanist and the new version fixes shell completion bugs.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Install shell completion.'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('current')
        ->setParameter('cursor-position')
        ->setHelp(
          pht(
            'Internal. Current term in the argument list being completed.')),
      $this->newWorkflowArgument('generate')
        ->setHelp(
          pht(
            'Regenerate shell completion rules, without installing any '.
            'configuration.')),
      $this->newWorkflowArgument('shell')
        ->setParameter('shell-name')
        ->setHelp(
          pht(
            'Install completion support for a particular shell.')),
      $this->newWorkflowArgument('argv')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $log = $this->getLogEngine();

    $argv = $this->getArgument('argv');

    $is_generate = $this->getArgument('generate');
    $is_shell = (bool)strlen($this->getArgument('shell'));
    $is_current = $this->getArgument('current');

    if ($argv) {
      $should_install = false;
      $should_generate = false;

      if ($is_generate) {
        throw new PhutilArgumentUsageException(
          pht(
            'You can not use "--generate" when completing arguments.'));
      }

      if ($is_shell) {
        throw new PhutilArgumentUsageException(
          pht(
            'You can not use "--shell" when completing arguments.'));
      }

    } else if ($is_generate) {
      $should_install = false;
      $should_generate = true;

      if ($is_current) {
        throw new PhutilArgumentUsageException(
          pht(
            'You can not use "--current" when generating rules.'));
      }

      if ($is_shell) {
        throw new PhutilArgumentUsageException(
          pht(
            'The flags "--generate" and "--shell" are mutually exclusive. '.
            'The "--shell" flag selects which shell to install support for, '.
            'but the "--generate" suppresses installation.'));
      }

    } else {
      $should_install = true;
      $should_generate = true;

      if ($is_current) {
        throw new PhutilArgumentUsageException(
          pht(
            'You can not use "--current" when installing support.'));
      }
    }

    if ($should_install) {
      $this->runInstall();
    }

    if ($should_generate) {
      $this->runGenerate();
    }

    if ($should_install || $should_generate) {
      $log->writeHint(
        pht('NOTE'),
        pht(
          'You may need to open a new terminal window or launch a new shell '.
          'before the changes take effect.'));
      return 0;
    }

    $this->runAutocomplete();
  }

  protected function newPrompts() {
    return array(
      $this->newPrompt('arc.shell-complete.install')
        ->setDescription(
          pht(
            'Confirms writing to to "~/.profile" (or another similar file) '.
            'to install shell completion.')),
    );
  }

  private function runInstall() {
    $log = $this->getLogEngine();

    $shells = array(
      array(
        'key' => 'bash',
        'path' => '/bin/bash',
        'file' => '.profile',
        'source' => 'hooks/bash-completion.sh',
      ),
    );
    $shells = ipull($shells, null, 'key');

    $shell = $this->getArgument('shell');
    if (!$shell) {
      $shell = $this->detectShell($shells);
    } else {
      $shell = $this->selectShell($shells, $shell);
    }

    $spec = $shells[$shell];
    $file = $spec['file'];
    $home = getenv('HOME');

    if (!strlen($home)) {
      throw new PhutilArgumentUsageException(
        pht(
          'The "HOME" environment variable is not defined, so this workflow '.
          'can not identify where to install shell completion.'));
    }

    $file_path = getenv('HOME').'/'.$file;
    $file_display = '~/'.$file;

    if (Filesystem::pathExists($file_path)) {
      $file_path = Filesystem::resolvePath($file_path);
      $data = Filesystem::readFile($file_path);
      $is_new = false;
    } else {
      $data = '';
      $is_new = true;
    }

    $line = csprintf(
      'source %R # arcanist-shell-complete',
      $this->getShellPath($spec['source']));

    $matches = null;
    $replace = preg_match(
      '/(\s*\n)?[^\n]+# arcanist-shell-complete\s*(\n\s*)?/',
      $data,
      $matches,
      PREG_OFFSET_CAPTURE);

    $log->writeSuccess(
      pht('INSTALL'),
      pht(
        'Installing shell completion support for "%s" into "%s".',
        $shell,
        $file_display));

    if ($replace) {
      $replace_pos = $matches[0][1];
      $replace_line = $matches[0][0];
      $replace_len = strlen($replace_line);
      $replace_display = trim($replace_line);

      if ($replace_pos === 0) {
        $new_line = $line."\n";
      } else {
        $new_line = "\n\n".$line."\n";
      }

      $new_data = substr_replace($data, $new_line, $replace_pos, $replace_len);

      if ($new_data === $data) {
        // If we aren't changing anything in the file, just skip the write
        // completely.
        $needs_write = false;

        $log->writeStatus(
          pht('SKIP'),
          pht('Shell completion for "%s" is already installed.', $shell));

        return;
      }

      echo tsprintf(
        "%s\n\n    %s\n\n%s\n\n    %s\n",
        pht(
          'To update shell completion support for "%s", your existing '.
          '"%s" file will be modified. This line will be removed:',
          $shell,
          $file_display),
        $replace_display,
        pht('This line will be added:'),
        $line);

      $prompt = pht('Rewrite this file?');
    } else {
      if ($is_new) {
        $new_data = $line."\n";

        echo tsprintf(
          "%s\n\n    %s\n",
          pht(
            'To install shell completion support for "%s", a new "%s" file '.
            'will be created with this content:',
            $shell,
            $file_display),
          $line);

        $prompt = pht('Create this file?');
      } else {
        $new_data = rtrim($data)."\n\n".$line."\n";

        echo tsprintf(
          "%s\n\n     %s\n",
          pht(
            'To install shell completion support for "%s", this line will be '.
            'added to your existing "%s" file:',
            $shell,
            $file_display),
          $line);

        $prompt = pht('Append to this file?');
      }
    }

    $this->getPrompt('arc.shell-complete.install')
      ->setQuery($prompt)
      ->execute();

    Filesystem::writeFile($file_path, $new_data);

    $log->writeSuccess(
      pht('INSTALLED'),
      pht(
        'Installed shell completion support for "%s" to "%s".',
        $shell,
        $file_display));
  }

  private function selectShell(array $shells, $shell_arg) {
    foreach ($shells as $shell) {
      if ($shell['key'] === $shell_arg) {
        return $shell_arg;
      }
    }

    throw new PhutilArgumentUsageException(
      pht(
        'The shell "%s" is not supported. Supported shells are: %s.',
        $shell_arg,
        implode(', ', ipull($shells, 'key'))));
  }

  private function detectShell(array $shells) {
    // NOTE: The "BASH_VERSION" and "ZSH_VERSION" shell variables are not
    // passed to subprocesses, so we can't inspect them to figure out which
    // shell launched us. If we could figure this out in some other way, it
    // would be nice to do so.

    // Instead, just look at "SHELL" (the user's startup shell).

    $log = $this->getLogEngine();

    $detected = array();
    $log->writeStatus(
      pht('DETECT'),
      pht('Detecting current shell...'));

    $shell_env = getenv('SHELL');
    if (!strlen($shell_env)) {
      $log->writeWarning(
        pht('SHELL'),
        pht(
          'The "SHELL" environment variable is not defined, so it can '.
          'not be used to detect the shell to install rules for.'));
    } else {
      $found = false;
      foreach ($shells as $shell) {
        if ($shell['path'] !== $shell_env) {
          continue;
        }

        $found = true;
        $detected[] = $shell['key'];

        $log->writeSuccess(
          pht('SHELL'),
          pht(
            'The "SHELL" environment variable has value "%s", so the '.
            'target shell was detected as "%s".',
            $shell_env,
            $shell['key']));
      }

      if (!$found) {
        $log->writeStatus(
          pht('SHELL'),
          pht(
            'The "SHELL" environment variable does not match any recognized '.
            'shell.'));
      }
    }

    if (!$detected) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to detect any supported shell, so autocompletion rules '.
          'can not be installed. Use "--shell" to select a shell.'));
    } else if (count($detected) > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Multiple supported shells were detected. Unable to determine '.
          'which shell to install autocompletion rules for. Use "--shell" '.
          'to select a shell.'));
    }

    return head($detected);
  }

  private function runGenerate() {
    $log = $this->getLogEngine();

    $toolsets = ArcanistToolset::newToolsetMap();

    $log->writeStatus(
      pht('GENERATE'),
      pht('Generating shell completion rules...'));

    $shells = array('bash');
    foreach ($shells as $shell) {

      $rules = array();
      foreach ($toolsets as $toolset) {
        $rules[] = $this->newCompletionRules($toolset, $shell);
      }
      $rules = implode("\n", $rules);

      $rules_path = $this->getShellPath('rules/'.$shell.'-rules.sh');

      // If a write wouldn't change anything, skip the write. This allows
      // "arc shell-complete" to work if "arcanist/" is on a read-only NFS
      // filesystem or something unusual like that.

      $skip_write = false;
      if (Filesystem::pathExists($rules_path)) {
        $current = Filesystem::readFile($rules_path);
        if ($current === $rules) {
          $skip_write = true;
        }
      }

      if ($skip_write) {
        $log->writeStatus(
          pht('SKIP'),
          pht(
            'Rules are already up to date for "%s" in: %s',
            $shell,
            Filesystem::readablePath($rules_path)));
      } else {
        Filesystem::writeFile($rules_path, $rules);
        $log->writeStatus(
          pht('RULES'),
          pht(
            'Wrote updated completion rules for "%s" to: %s.',
            $shell,
            Filesystem::readablePath($rules_path)));
      }
    }
  }

  private function newCompletionRules(ArcanistToolset $toolset, $shell) {
    $template_path = $this->getShellPath('templates/'.$shell.'-template.sh');
    $template = Filesystem::readFile($template_path);

    $variables = array(
      'BIN' => $toolset->getToolsetKey(),
    );

    foreach ($variables as $key => $value) {
      $template = str_replace('{{{'.$key.'}}}', $value, $template);
    }

    return $template;
  }

  private function getShellPath($to_file = null) {
    $arc_root = dirname(phutil_get_library_root('arcanist'));
    return $arc_root.'/support/shell/'.$to_file;
  }

  private function runAutocomplete() {
    $argv = $this->getArgument('argv');
    $argc = count($argv);

    $pos = $this->getArgument('current');
    if (!$pos) {
      $pos = $argc - 1;
    }

    if ($pos >= $argc) {
      throw new ArcanistUsageException(
        pht(
          'Argument position specified with "--current" ("%s") is greater '.
          'than the number of arguments provided ("%s").',
          new PhutilNumber($pos),
          new PhutilNumber($argc)));
    }

    $workflows = $this->getRuntime()->getWorkflows();

    // NOTE: This isn't quite right. For example, "arc --con<tab>" will
    // try to autocomplete workflows named "--con", but it should actually
    // autocomplete global flags and suggest "--config".

    $is_workflow = ($pos <= 1);

    if ($is_workflow) {
      // NOTE: There was previously some logic to try to filter impossible
      // workflows out of the completion list based on the VCS in the current
      // working directory: for example, if you're in an SVN working directory,
      // "arc a" is unlikely to complete to "arc amend" because "amend" does
      // not support SVN. It's not clear this logic is valuable, but we could
      // consider restoring it if good use cases arise.

      // TOOLSETS: Restore the ability for workflows to opt out of shell
      // completion. It is exceptionally unlikely that users want to shell
      // complete obscure or internal workflows, like "arc shell-complete"
      // itself. Perhaps a good behavior would be to offer these as
      // completions if they are the ONLY available completion, since a user
      // who has typed "arc shell-comp<tab>" likely does want "shell-complete".

      $complete = array();
      foreach ($workflows as $workflow) {
        $complete[] = $workflow->getWorkflowName();
      }

      foreach ($this->getConfig('aliases') as $alias) {
        if ($alias->getException()) {
          continue;
        }

        if ($alias->getToolset() !== $this->getToolsetKey()) {
          continue;
        }

        $complete[] = $alias->getTrigger();
      }

      $partial = $argv[$pos];
      $complete = $this->getMatches($complete, $partial);

      if ($complete) {
        return $this->suggestStrings($complete);
      } else {
        return $this->suggestNothing();
      }

    } else {
      // TOOLSETS: We should resolve aliases before picking a workflow, so
      // that if you alias "arc draft" to "arc diff --draft", we can suggest
      // other "diff" flags when you type "arc draft --q<tab>".

      // TOOLSETS: It's possible the workflow isn't in position 1. The user
      // may be running "arc --trace diff --dra<tab>", for example.

      $workflow = idx($workflows, $argv[1]);
      if (!$workflow) {
        return $this->suggestNothing();
      }

      $arguments = $workflow->getWorkflowArguments();
      $arguments = mpull($arguments, null, 'getKey');
      $current = idx($argv, $pos, '');

      $argument = null;
      $prev = idx($argv, $pos - 1, null);
      if (!strncmp($prev, '--', 2)) {
        $prev = substr($prev, 2);
        $argument = idx($arguments, $prev);
      }

      // If the last argument was a "--thing" argument, test if "--thing" is
      // a parameterized argument. If it is, the next argument should be a
      // parameter.

      if ($argument && strlen($argument->getParameter())) {
        if ($argument->getIsPathArgument()) {
          return $this->suggestPaths($current);
        } else {
          return $this->suggestNothing();
        }

        // TOOLSETS: We can allow workflows and arguments to provide a specific
        // list of completeable values, like the "--shell" argument for this
        // workflow.
      }

      $flags = array();
      $wildcard = null;
      foreach ($arguments as $argument) {
        if ($argument->getWildcard()) {
          $wildcard = $argument;
          continue;
        }

        $flags[] = '--'.$argument->getKey();
      }

      $matches = $this->getMatches($flags, $current);

      // If whatever the user is completing does not match the prefix of any
      // flag (or is entirely empty), try to autcomplete a wildcard argument
      // if it has some kind of meaningful completion. For example, "arc lint
      // READ<tab>" should autocomplete a file, and "arc lint <tab>" should
      // suggest files in the current directory.

      if (!strlen($current) || !$matches) {
        $try_paths = true;
      } else {
        $try_paths = false;
      }

      if ($try_paths && $wildcard) {
        // TOOLSETS: There was previously some very questionable support for
        // autocompleting branches here. This could be moved into Arguments
        // and Workflows.

        if ($wildcard->getIsPathArgument()) {
          return $this->suggestPaths($current);
        }
      }

      // TODO: If a command has only one flag, like "--json", don't suggest
      // it if the user hasn't typed anything or has only typed "--".

      // TODO: Don't suggest "--flag" arguments which aren't repeatable if
      // they are already present in the argument list.

      return $this->suggestStrings($matches);
    }
  }

  private function suggestPaths($prefix) {
    // NOTE: We are returning a directive to the bash script to run "compgen"
    // for us rather than running it ourselves. If we run:
    //
    //   compgen -A file -- %s
    //
    // ...from this context, it fails (exits with error code 1 and no output)
    // if the prefix is "foo\ ", on my machine. See T9116 for some dicussion.
    echo '<compgen:file>';
    return 0;
  }

  private function suggestNothing() {
    return $this->suggestStrings(array());
  }

  private function suggestStrings(array $strings) {
    sort($strings);
    echo implode("\n", $strings);
    return 0;
  }

  private function getMatches(array $candidates, $prefix) {
    $matches = array();

    if (strlen($prefix)) {
      foreach ($candidates as $possible) {
        if (!strncmp($possible, $prefix, strlen($prefix))) {
          $matches[] = $possible;
        }
      }

      // If we matched nothing, try a case-insensitive match.
      if (!$matches) {
        foreach ($candidates as $possible) {
          if (!strncasecmp($possible, $prefix, strlen($prefix))) {
            $matches[] = $possible;
          }
        }
      }
    } else {
      $matches = $candidates;
    }

    return $matches;
  }

}
