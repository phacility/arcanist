<?php

/**
 * Installable as an SVN "pre-commit" hook.
 *
 * @group workflow
 */
final class ArcanistSvnHookPreCommitWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'svn-hook-pre-commit';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **svn-hook-pre-commit** __repository__ __transaction__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: svn
          You can install this as an SVN pre-commit hook. For more information,
          see the article "Installing Arcanist SVN Hooks" in the Arcanist
          documentation.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'svnargs',
    );
  }

  public function shouldShellComplete() {
    return false;
  }

  public function run() {

    $svnargs = $this->getArgument('svnargs');
    $repository = $svnargs[0];
    $transaction = $svnargs[1];

    list($commit_message) = execx(
      'svnlook log --transaction %s %s',
      $transaction,
      $repository);

    if (strpos($commit_message, '@bypass-lint') !== false) {
      return 0;
    }


    // TODO: Do stuff with commit message.

    list($changed) = execx(
      'svnlook changed --transaction %s %s',
      $transaction,
      $repository);

    $paths = array();
    $changed = explode("\n", trim($changed));
    foreach ($changed as $line) {
      $matches = null;
      preg_match('/^..\s*(.*)$/', $line, $matches);
      $paths[$matches[1]] = strlen($matches[1]);
    }

    $resolved = array();
    $failed = array();
    $missing = array();
    $found = array();
    asort($paths);

    foreach ($paths as $path => $length) {
      foreach ($resolved as $rpath => $root) {
        if (!strncmp($path, $rpath, strlen($rpath))) {
          $resolved[$path] = $root;
          continue 2;
        }
      }
      $config = $path;

      if (basename($config) == '.arcconfig') {
        $resolved[$config] = $config;
        continue;
      }

      $config = rtrim($config, '/');
      $last_config = $config;
      do {
        if (!empty($missing[$config])) {
          break;
        } else if (!empty($found[$config])) {
          $resolved[$path] = $found[$config];
          break;
        }
        list($err) = exec_manual(
          'svnlook cat --transaction %s %s %s',
          $transaction,
          $repository,
          $config ? $config.'/.arcconfig' : '.arcconfig');
        if ($err) {
          $missing[$path] = true;
        } else {
          $resolved[$path] = $config ? $config.'/.arcconfig' : '.arcconfig';
          $found[$config] = $resolved[$path];
          break;
        }
        $config = dirname($config);
        if ($config == '.') {
          $config = '';
        }
        if ($config == $last_config) {
          break;
        }
        $last_config = $config;
      } while (true);

      if (empty($resolved[$path])) {
        $failed[] = $path;
      }
    }

    if ($failed && $resolved) {
      $failed_paths = '        '.implode("\n        ", $failed);
      $resolved_paths = '        '.implode("\n        ", array_keys($resolved));
      throw new ArcanistUsageException(
        "This commit includes a mixture of files in Arcanist projects and ".
        "outside of Arcanist projects. A commit which affects an Arcanist ".
        "project must affect only that project.\n\n".
        "Files in projects:\n\n".
        $resolved_paths."\n\n".
        "Files not in projects:\n\n".
        $failed_paths);
    }

    if (!$resolved) {
      // None of the affected paths are beneath a .arcconfig file.
      return 0;
    }

    $groups = array();
    foreach ($resolved as $path => $project) {
      $groups[$project][] = $path;
    }
    if (count($groups) > 1) {
      $message = array();
      foreach ($groups as $project => $group) {
        $message[] = "Files underneath '{$project}':\n\n";
        $message[] = "        ".implode("\n        ", $group)."\n\n";
      }
      $message = implode('', $message);
      throw new ArcanistUsageException(
        "This commit includes a mixture of files from different Arcanist ".
        "projects. A commit which affects an Arcanist project must affect ".
        "only that project.\n\n".
        $message);
    }

    $config_file = key($groups);
    $project_root = dirname($config_file);
    $paths = reset($groups);

    list($config) = execx(
      'svnlook cat --transaction %s %s %s',
      $transaction,
      $repository,
      $config_file);

    $working_copy = ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
      $project_root,
      $config,
      $config_file." (svnlook: {$transaction} {$repository})");

    $repository_api = new ArcanistSubversionHookAPI(
      $project_root,
      $transaction,
      $repository);

    $lint_engine = $working_copy->getProjectConfig('lint.engine');
    if (!$lint_engine) {
      return 0;
    }

    $engine = newv($lint_engine, array());
    $engine->setWorkingCopy($working_copy);
    $engine->setConfigurationManager($this->getConfigurationManager());
    $engine->setMinimumSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
    $engine->setPaths($paths);
    $engine->setCommitHookMode(true);
    $engine->setHookAPI($repository_api);

    try {
      $results = $engine->run();
    } catch (ArcanistNoEffectException $no_effect) {
      // Nothing to do, bail out.
      return 0;
    }

    $failures = array();
    foreach ($results as $result) {
      if (!$result->getMessages()) {
        continue;
      }
      $failures[] = $result;
    }

    if ($failures) {
      $at = '@';
      $msg = phutil_console_format(
        "\n**LINT ERRORS**\n\n".
        "This changeset has lint errors. You must fix all lint errors before ".
        "you can commit.\n\n".
        "You can add '{$at}bypass-lint' to your commit message to disable ".
        "lint checks for this commit, or '{$at}nolint' to the file with ".
        "errors to disable lint for that file.\n\n");
      echo phutil_console_wrap($msg);

      $renderer = new ArcanistLintConsoleRenderer();
      foreach ($failures as $result) {
        echo $renderer->renderLintResult($result);
      }
      return 1;
    }

    return 0;
  }
}
