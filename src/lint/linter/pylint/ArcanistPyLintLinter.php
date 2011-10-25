<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Uses "PyLint" to detect various errors in Python code. To use this linter,
 * you must install pylint and configure which codes you want to be reported as
 * errors, warnings and advice.
 *
 * You should be able to install pylint with ##sudo easy_install pylint##. If
 * your system is unusual, you can manually specify the location of pylint and
 * its dependencies by configuring these keys in your .arcconfig:
 *
 *   lint.pylint.prefix
 *   lint.pylint.logilab_astng.prefix
 *   lint.pylint.logilab_common.prefix
 *
 * You can specify additional command-line options to pass to PyLint by
 * setting ##lint.pylint.options##. You may also specify a list of additional
 * entries for PYTHONPATH with ##lint.pylint.pythonpath##. Those can be
 * absolute or relative to the project root.
 *
 * If you have a PyLint rcfile, specify its path with
 * ##lint.pylint.rcfile##. It can be absolute or relative to the project
 * root. Be sure not to define ##output-format##, or if you do, set it to
 * ##text##.
 *
 * Specify which PyLint messages map to which Arcanist messages by defining
 * the following regular expressions:
 *
 *   lint.pylint.codes.error
 *   lint.pylint.codes.warning
 *   lint.pylint.codes.advice
 *
 * The regexps are run in that order; the first to match determines which
 * Arcanist severity applies, if any. For example, to capture all PyLint
 * "E...." errors as Arcanist errors, set ##lint.pylint.codes.error## to:
 *
 *    ^E.*
 *
 * You can also match more granularly:
 *
 *    ^E(0001|0002)$
 *
 * According to ##man pylint##, there are 5 kind of messages:
 *
 *   (C) convention, for programming standard violation
 *   (R) refactor, for bad code smell
 *   (W) warning, for python specific problems
 *   (E) error, for probable bugs in the code
 *   (F) fatal, if an error occurred which prevented pylint from
 *       doing further processing.
 *
 * @group linter
 */
class ArcanistPyLintLinter extends ArcanistLinter {

  private function getMessageCodeSeverity($code) {

    $working_copy = $this->getEngine()->getWorkingCopy();

    $error_regexp   = $working_copy->getConfig('lint.pylint.codes.error');
    $warning_regexp = $working_copy->getConfig('lint.pylint.codes.warning');
    $advice_regexp  = $working_copy->getConfig('lint.pylint.codes.advice');

    if (!$error_regexp && !$warning_regexp && !$advice_regexp) {
      throw new ArcanistUsageException(
        "You are invoking the PyLint linter but have not configured any of ".
        "'lint.pylint.codes.error', 'lint.pylint.codes.warning', or ".
        "'lint.pylint.codes.advice'. Consult the documentation for ".
        "ArcanistPyLintLinter.");
    }

    $code_map = array(
      ArcanistLintSeverity::SEVERITY_ERROR    => $error_regexp,
      ArcanistLintSeverity::SEVERITY_WARNING  => $warning_regexp,
      ArcanistLintSeverity::SEVERITY_ADVICE   => $advice_regexp,
    );

    foreach ($code_map as $sev => $codes) {
      if ($codes === null) {
        continue;
      }
      if (!is_array($codes)) {
        $codes = array($codes);
      }
      foreach ($codes as $code_re) {
        if (preg_match("/{$code_re}/", $code)) {
          return $sev;
        }
      }
    }

    // If the message code doesn't match any of the provided regex's,
    // then just disable it.
    return ArcanistLintSeverity::SEVERITY_DISABLED;
  }

  private function getPyLintPath() {
    $pylint_bin = "pylint";

    // Use the PyLint prefix specified in the config file
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getConfig('lint.pylint.prefix');
    if ($prefix !== null) {
      $pylint_bin = $prefix."/bin/".$pylint_bin;
    }

    if (!Filesystem::pathExists($pylint_bin)) {

      list($err) = exec_manual('which %s', $pylint_bin);
      if ($err) {
        throw new ArcanistUsageException(
          "PyLint does not appear to be installed on this system. Install it ".
          "(e.g., with 'sudo easy_install pylint') or configure ".
          "'lint.pylint.prefix' in your .arcconfig to point to the directory ".
          "where it resides.");
      }
    }

    return $pylint_bin;
  }

  private function getPyLintPythonPath() {
    // Get non-default install locations for pylint and its dependencies
    // libraries.
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefixes = array(
      $working_copy->getConfig('lint.pylint.prefix'),
      $working_copy->getConfig('lint.pylint.logilab_astng.prefix'),
      $working_copy->getConfig('lint.pylint.logilab_common.prefix'),
    );

    // Add the libraries to the python search path
    $python_path = array();
    foreach ($prefixes as $prefix) {
      if ($prefix !== null) {
        $python_path[] = $prefix.'/lib/python2.6/site-packages';
      }
    }

    $config_paths = $working_copy->getConfig('lint.pylint.pythonpath');
    if ($config_paths !== null) {
      foreach ($config_paths as $config_path) {
        if ($config_path !== null) {
          $python_path[] =
            Filesystem::resolvePath($config_path,
                                    $working_copy->getProjectRoot());
        }
      }
    }

    $python_path[] = '';
    return implode(":", $python_path);
  }

  private function getPyLintOptions() {
    // '-rn': don't print lint report/summary at end
    // '-iy': show message codes for lint warnings/errors
    $options = array('-rn',  '-iy');

    $working_copy = $this->getEngine()->getWorkingCopy();

    // Specify an --rcfile, either absolute or relative to the project root.
    // Stupidly, the command line args above are overridden by rcfile, so be
    // careful.
    $rcfile = $working_copy->getConfig('lint.pylint.rcfile');
    if ($rcfile !== null) {
      $rcfile = Filesystem::resolvePath(
                   $rcfile,
                   $working_copy->getProjectRoot());
      $options[] = csprintf('--rcfile=%s', $rcfile);
    }

    // Add any options defined in the config file for PyLint
    $config_options = $working_copy->getConfig('lint.pylint.options');
    if ($config_options !== null) {
      $options = array_merge($options, $config_options);
    }

    return implode(" ", $options);
  }

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'PyLint';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array();
  }

  public function lintPath($path) {
    $pylint_bin = $this->getPyLintPath();
    $python_path = $this->getPyLintPythonPath();
    $options = $this->getPyLintOptions();
    $path_on_disk = $this->getEngine()->getFilePathOnDisk($path);

    try {
      list($stdout, $_) = execx(
          "/usr/bin/env PYTHONPATH=%s\$PYTHONPATH ".
            "{$pylint_bin} {$options} {$path_on_disk}",
          $python_path);
    } catch (CommandException $e) {
      if ($e->getError() == 32) {
        // According to ##man pylint## the exit status of 32 means there was a
        // usage error. That's bad, so actually exit abnormally.
        throw $e;
      } else {
        // The other non-zero exit codes mean there were messages issued,
        // which is expected, so don't exit.
        $stdout = $e->getStdout();
      }
    }

    $lines = explode("\n", $stdout);
    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match('/([A-Z]\d+): *(\d+): *(.*)$/', $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      $message->setCode($matches[1]);
      $message->setName($this->getLinterName()." ".$matches[1]);
      $message->setDescription($matches[3]);
      $message->setSeverity($this->getMessageCodeSeverity($matches[1]));
      $this->addLintMessage($message);
    }
  }

}
