<?php

/**
 * Base class for linters which operate by invoking an external program and
 * parsing results.
 *
 * @task bin      Interpreters, Binaries and Flags
 * @task parse    Parsing Linter Output
 * @task exec     Executing the Linter
 */
abstract class ArcanistExternalLinter extends ArcanistFutureLinter {

  private $bin;
  private $interpreter;
  private $flags;
  private $versionRequirement;


/* -(  Interpreters, Binaries and Flags  )----------------------------------- */

  /**
   * Return the default binary name or binary path where the external linter
   * lives. This can either be a binary name which is expected to be installed
   * in PATH (like "jshint"), or a relative path from the project root
   * (like "resources/support/bin/linter") or an absolute path.
   *
   * If the binary needs an interpreter (like "python" or "node"), you should
   * also override @{method:shouldUseInterpreter} and provide the interpreter
   * in @{method:getDefaultInterpreter}.
   *
   * @return string Default binary to execute.
   * @task bin
   */
  abstract public function getDefaultBinary();

  /**
   * Return a human-readable string describing how to install the linter. This
   * is normally something like "Install such-and-such by running `npm install
   * -g such-and-such`.", but will differ from linter to linter.
   *
   * @return string Human readable install instructions
   * @task bin
   */
  abstract public function getInstallInstructions();

  /**
   * Return a human-readable string describing how to upgrade the linter.
   *
   * @return string Human readable upgrade instructions
   * @task bin
   */
  public function getUpgradeInstructions() {
      return null;
  }

  /**
   * Return true to continue when the external linter exits with an error code.
   * By default, linters which exit with an error code are assumed to have
   * failed. However, some linters exit with a specific code to indicate that
   * lint messages were detected.
   *
   * If the linter sometimes raises errors during normal operation, override
   * this method and return true so execution continues when it exits with
   * a nonzero status.
   *
   * @param bool  Return true to continue on nonzero error code.
   * @task bin
   */
  public function shouldExpectCommandErrors() {
    return true;
  }

  /**
   * Provide mandatory, non-overridable flags to the linter. Generally these
   * are format flags, like `--format=xml`, which must always be given for
   * the output to be usable.
   *
   * Flags which are not mandatory should be provided in
   * @{method:getDefaultFlags} instead.
   *
   * @return list<string>  Mandatory flags, like `"--format=xml"`.
   * @task bin
   */
  protected function getMandatoryFlags() {
    return array();
  }

  /**
   * Provide default, overridable flags to the linter. Generally these are
   * configuration flags which affect behavior but aren't critical. Flags
   * which are required should be provided in @{method:getMandatoryFlags}
   * instead.
   *
   * Default flags can be overridden with @{method:setFlags}.
   *
   * @return list<string>  Overridable default flags.
   * @task bin
   */
  protected function getDefaultFlags() {
    return array();
  }

  /**
   * Override default flags with custom flags. If not overridden, flags provided
   * by @{method:getDefaultFlags} are used.
   *
   * @param list<string> New flags.
   * @return this
   * @task bin
   */
  final public function setFlags(array $flags) {
    $this->flags = $flags;
    return $this;
  }

  /**
   * Set the binary's version requirement.
   *
   * @param string Version requirement.
   * @return this
   * @task bin
   */
  final public function setVersionRequirement($version) {
    $this->versionRequirement = trim($version);
    return $this;
  }

  /**
   * Return the binary or script to execute. This method synthesizes defaults
   * and configuration. You can override the binary with @{method:setBinary}.
   *
   * @return string Binary to execute.
   * @task bin
   */
  final public function getBinary() {
    return coalesce($this->bin, $this->getDefaultBinary());
  }

  /**
   * Override the default binary with a new one.
   *
   * @param string  New binary.
   * @return this
   * @task bin
   */
  final public function setBinary($bin) {
    $this->bin = $bin;
    return $this;
  }

  /**
   * Return true if this linter should use an interpreter (like "python" or
   * "node") in addition to the script.
   *
   * After overriding this method to return `true`, override
   * @{method:getDefaultInterpreter} to set a default.
   *
   * @return bool True to use an interpreter.
   * @task bin
   */
  public function shouldUseInterpreter() {
    return false;
  }

  /**
   * Return the default interpreter, like "python" or "node". This method is
   * only invoked if @{method:shouldUseInterpreter} has been overridden to
   * return `true`.
   *
   * @return string Default interpreter.
   * @task bin
   */
  public function getDefaultInterpreter() {
    throw new PhutilMethodNotImplementedException();
  }

  /**
   * Get the effective interpreter. This method synthesizes configuration and
   * defaults.
   *
   * @return string Effective interpreter.
   * @task bin
   */
  final public function getInterpreter() {
    return coalesce($this->interpreter, $this->getDefaultInterpreter());
  }

  /**
   * Set the interpreter, overriding any default.
   *
   * @param string New interpreter.
   * @return this
   * @task bin
   */
  final public function setInterpreter($interpreter) {
    $this->interpreter = $interpreter;
    return $this;
  }


/* -(  Parsing Linter Output  )---------------------------------------------- */

  /**
   * Parse the output of the external lint program into objects of class
   * @{class:ArcanistLintMessage} which `arc` can consume. Generally, this
   * means examining the output and converting each warning or error into a
   * message.
   *
   * If parsing fails, returning `false` will cause the caller to throw an
   * appropriate exception. (You can also throw a more specific exception if
   * you're able to detect a more specific condition.) Otherwise, return a list
   * of messages.
   *
   * @param  string   Path to the file being linted.
   * @param  int      Exit code of the linter.
   * @param  string   Stdout of the linter.
   * @param  string   Stderr of the linter.
   * @return list<ArcanistLintMessage>|false  List of lint messages, or false
   *                                          to indicate parser failure.
   * @task parse
   */
  abstract protected function parseLinterOutput($path, $err, $stdout, $stderr);


/* -(  Executing the Linter  )----------------------------------------------- */

  /**
   * Check that the binary and interpreter (if applicable) exist, and throw
   * an exception with a message about how to install them if they do not.
   *
   * @return void
   */
  final public function checkBinaryConfiguration() {
    $interpreter = null;
    if ($this->shouldUseInterpreter()) {
      $interpreter = $this->getInterpreter();
    }

    $binary = $this->getBinary();

    // NOTE: If we have an interpreter, we don't require the script to be
    // executable (so we just check that the path exists). Otherwise, the
    // binary must be executable.

    if ($interpreter) {
      if (!Filesystem::binaryExists($interpreter)) {
        throw new ArcanistMissingLinterException(
          pht(
            'Unable to locate interpreter "%s" to run linter %s. You may need '.
            'to install the interpreter, or adjust your linter configuration.',
            $interpreter,
            get_class($this)));
      }
      if (!Filesystem::pathExists($binary)) {
        throw new ArcanistMissingLinterException(
          sprintf(
            "%s\n%s",
            pht(
              'Unable to locate script "%s" to run linter %s. You may need '.
              'to install the script, or adjust your linter configuration.',
              $binary,
              get_class($this)),
            pht(
              'TO INSTALL: %s',
              $this->getInstallInstructions())));
      }
    } else {
      if (!Filesystem::binaryExists($binary)) {
        throw new ArcanistMissingLinterException(
          sprintf(
            "%s\n%s",
            pht(
              'Unable to locate binary "%s" to run linter %s. You may need '.
              'to install the binary, or adjust your linter configuration.',
              $binary,
              get_class($this)),
            pht(
              'TO INSTALL: %s',
              $this->getInstallInstructions())));
      }
    }
  }

  /**
   * If a binary version requirement has been specified, compare the version
   * of the configured binary to the required version, and if the binary's
   * version is not supported, throw an exception.
   *
   * @param  string   Version string to check.
   * @return void
   */
  final protected function checkBinaryVersion($version) {
    if (!$this->versionRequirement) {
      return;
    }

    if (!$version) {
      $message = pht(
        'Linter %s requires %s version %s. Unable to determine the version '.
        'that you have installed.',
         get_class($this),
         $this->getBinary(),
         $this->versionRequirement);

      $instructions = $this->getUpgradeInstructions();
      if ($instructions) {
        $message .= "\n".pht('TO UPGRADE: %s', $instructions);
      }

      throw new ArcanistMissingLinterException($message);
    }

    $operator = '==';
    $compare_to = $this->versionRequirement;

    $matches = null;
    if (preg_match('/^([<>]=?|=)\s*(.*)$/', $compare_to, $matches)) {
      $operator = $matches[1];
      $compare_to = $matches[2];
      if ($operator === '=') {
        $operator = '==';
      }
    }

    if (!version_compare($version, $compare_to, $operator)) {
      $message = pht(
        'Linter %s requires %s version %s. You have version %s.',
        get_class($this),
        $this->getBinary(),
        $this->versionRequirement,
        $version);

      $instructions = $this->getUpgradeInstructions();
      if ($instructions) {
        $message .= "\n".pht('TO UPGRADE: %s', $instructions);
      }

      throw new ArcanistMissingLinterException($message);
    }
  }

  /**
   * Get the composed executable command, including the interpreter and binary
   * but without flags or paths. This can be used to execute `--version`
   * commands.
   *
   * @return string Command to execute the raw linter.
   * @task exec
   */
  final protected function getExecutableCommand() {
    $this->checkBinaryConfiguration();

    $interpreter = null;
    if ($this->shouldUseInterpreter()) {
      $interpreter = $this->getInterpreter();
    }

    $binary = $this->getBinary();

    if ($interpreter) {
      $bin = csprintf('%s %s', $interpreter, $binary);
    } else {
      $bin = csprintf('%s', $binary);
    }

    return $bin;
  }

  /**
   * Get the composed flags for the executable, including both mandatory and
   * configured flags.
   *
   * @return list<string> Composed flags.
   * @task exec
   */
  final protected function getCommandFlags() {
    return array_merge(
      $this->getMandatoryFlags(),
      nonempty($this->flags, $this->getDefaultFlags()));
  }

  public function getCacheVersion() {
    try {
      $this->checkBinaryConfiguration();
    } catch (ArcanistMissingLinterException $e) {
      return null;
    }

    $version = $this->getVersion();

    if ($version) {
      $this->checkBinaryVersion($version);
      return $version.'-'.json_encode($this->getCommandFlags());
    } else {
      // Either we failed to parse the version number or the `getVersion`
      // function hasn't been implemented.
      return json_encode($this->getCommandFlags());
    }
  }

  /**
   * Prepare the path to be added to the command string.
   *
   * This method is expected to return an already escaped string.
   *
   * @param string Path to the file being linted
   * @return string The command-ready file argument
   */
  protected function getPathArgumentForLinterFuture($path) {
    return csprintf('%s', $path);
  }

  protected function buildFutures(array $paths) {
    $executable = $this->getExecutableCommand();

    $bin = csprintf('%C %Ls', $executable, $this->getCommandFlags());

    $futures = array();
    foreach ($paths as $path) {
      $disk_path = $this->getEngine()->getFilePathOnDisk($path);
      $path_argument = $this->getPathArgumentForLinterFuture($disk_path);
      $future = new ExecFuture('%C %C', $bin, $path_argument);

      $future->setCWD($this->getProjectRoot());
      $futures[$path] = $future;
    }

    return $futures;
  }

  protected function resolveFuture($path, Future $future) {
    list($err, $stdout, $stderr) = $future->resolve();
    if ($err && !$this->shouldExpectCommandErrors()) {
      $future->resolvex();
    }

    $messages = $this->parseLinterOutput($path, $err, $stdout, $stderr);

    if ($err && $this->shouldExpectCommandErrors() && !$messages) {
      // We assume that if the future exits with a non-zero status and we
      // failed to parse any linter messages, then something must've gone wrong
      // during parsing.
      $messages = false;
    }

    if ($messages === false) {
      if ($err) {
        $future->resolvex();
      } else {
        throw new Exception(
          sprintf(
            "%s\n\nSTDOUT\n%s\n\nSTDERR\n%s",
            pht('Linter failed to parse output!'),
            $stdout,
            $stderr));
      }
    }

    foreach ($messages as $message) {
      $this->addLintMessage($message);
    }
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'bin' => array(
        'type' => 'optional string | list<string>',
        'help' => pht(
          'Specify a string (or list of strings) identifying the binary '.
          'which should be invoked to execute this linter. This overrides '.
          'the default binary. If you provide a list of possible binaries, '.
          'the first one which exists will be used.'),
      ),
      'flags' => array(
        'type' => 'optional list<string>',
        'help' => pht(
          'Provide a list of additional flags to pass to the linter on the '.
          'command line.'),
      ),
      'version' => array(
        'type' => 'optional string',
        'help' => pht(
          'Specify a version requirement for the binary. The version number '.
          'may be prefixed with <, <=, >, >=, or = to specify the version '.
          'comparison operator (default: =).'),
      ),
    );

    if ($this->shouldUseInterpreter()) {
      $options['interpreter'] = array(
        'type' => 'optional string | list<string>',
        'help' => pht(
          'Specify a string (or list of strings) identifying the interpreter '.
          'which should be used to invoke the linter binary. If you provide '.
          'a list of possible interpreters, the first one that exists '.
          'will be used.'),
      );
    }

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'interpreter':
        $root = $this->getProjectRoot();

        foreach ((array)$value as $path) {
          if (Filesystem::binaryExists($path)) {
            $this->setInterpreter($path);
            return;
          }

          $path = Filesystem::resolvePath($path, $root);

          if (Filesystem::binaryExists($path)) {
            $this->setInterpreter($path);
            return;
          }
        }

        throw new Exception(
          pht('None of the configured interpreters can be located.'));
      case 'bin':
        $is_script = $this->shouldUseInterpreter();

        $root = $this->getProjectRoot();

        foreach ((array)$value as $path) {
          if (!$is_script && Filesystem::binaryExists($path)) {
            $this->setBinary($path);
            return;
          }

          $path = Filesystem::resolvePath($path, $root);
          if ((!$is_script && Filesystem::binaryExists($path)) ||
              ($is_script && Filesystem::pathExists($path))) {
            $this->setBinary($path);
            return;
          }
        }

        throw new Exception(
          pht('None of the configured binaries can be located.'));
      case 'flags':
        $this->setFlags($value);
        return;
      case 'version':
        $this->setVersionRequirement($value);
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  /**
   * Map a configuration lint code to an `arc` lint code. Primarily, this is
   * intended for validation, but can also be used to normalize case or
   * otherwise be more permissive in accepted inputs.
   *
   * If the code is not recognized, you should throw an exception.
   *
   * @param string  Code specified in configuration.
   * @return string  Normalized code to use in severity map.
   */
  protected function getLintCodeFromLinterConfigurationKey($code) {
    return $code;
  }

}
