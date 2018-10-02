<?php

/** This linter invokes shellcheck to check on shell code standards */
final class UberShellCheckLinter extends ArcanistExternalLinter {

  private $excluded_rules = NULL;

  private $shell = 'bash';

  private $warning_as_error = FALSE;

  private $defaultSeverityMap = array();

  public function getInfoName() {
    return 'ShellCheck';
  }

  public function getInfoURI() {
    return 'http://www.shellcheck.net/';
  }

  public function getInfoDescription() {
    return pht(
      'ShellCheck is a static analysis and linting tool for %s scripts.',
      'sh/bash');
  }

  public function getLinterName() {
    return 'SHELLCHECK';
  }

  public function getLinterConfigurationName() {
    return 'shellcheck';
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'shellcheck.script' => array(
        'type' => 'optional string',
        'help' => pht('Shellcheck script to execute. Script must output shellcheck in XML to $stdout'),
      ),
      'shellcheck.shell' => array(
        'type' => 'optional string',
        'help' => pht(
          'Specify shell dialect (%s, %s, %s, %s).',
          'bash',
          'sh',
          'ksh',
          'zsh'),
      ),
      'shellcheck.excluded_rules' => array(
        'type' => 'optional list<string>',
        'help' => pht('List of excluded shellcheck rule(s)'),
      ),
      'shellcheck.warning_as_error' => array(
        'type' => 'optional bool',
        'help' => pht('Whether to treat warnings as errors'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'shellcheck.script':
        $this->setBinary($value);
        return;
      case 'shellcheck.shell':
        $this->setShell($value);
        return;
      case 'shellcheck.excluded_rules':
        $this->setExcludedRules($value);
        return;
      case 'shellcheck.warning_as_error':
        $this->setWarningAsError($value);
        return;

      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
  }

  public function setExcludedRules($excluded_rules) {
    $this->excluded_rules = $excluded_rules;
    return $this;
  }

  public function setShell($shell) {
    $this->shell = $shell;
    return $this;
  }

  public function setWarningAsError($warning_as_error) {
    $this->warning_as_error = $warning_as_error;
    return $this;
  }

  public function getDefaultBinary() {
    return 'shellcheck';
  }

  public function getInstallInstructions() {
    return pht(
      'Install ShellCheck with `%s`.',
      'brew install shellcheck');
  }

  protected function getMandatoryFlags() {
    $options = array();

    if ($this->excluded_rules && count($this->excluded_rules) > 0) {
      $options[] = '--exclude='.implode(',', $this->excluded_rules);
    }
    $options[] = '--format=checkstyle';

    if ($this->shell) {
      $options[] = '--shell='.$this->shell;
    }

    return $options;
  }

  public function getVersion() {
    list($stdout, $stderr) = execx(
      '%C --version', $this->getExecutableCommand());

    $matches = null;
    if (preg_match('/^version: (\d(?:\.\d){2})$/', $stdout, $matches)) {
      return $matches[1];
    }

    return null;
  }

  protected function getDefaultMessageSeverity($code) {
    switch ($this->defaultSeverityMap[$code]) {
      case 'error':
        return ArcanistLintSeverity::SEVERITY_ERROR;
      case 'warning':
        return $this->warning_as_error
          ? ArcanistLintSeverity::SEVERITY_ERROR
          : ArcanistLintSeverity::SEVERITY_WARNING;
      case 'info':
        return ArcanistLintSeverity::SEVERITY_ADVICE;
      default:
        return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $report_dom = new DOMDocument();
    $ok = @$report_dom->loadXML($stdout);

    if (!$ok) {
      return false;
    }

    $files = $report_dom->getElementsByTagName('file');
    $messages = array();

    foreach ($files as $file) {
      foreach ($file->getElementsByTagName('error') as $child) {
        $line = $child->getAttribute('line');

        $code = str_replace('ShellCheck.', '', $child->getAttribute('source'));
        $this->defaultSeverityMap[$code] = $child->getAttribute('severity');

        $message = id(new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($child->getAttribute('line'))
          ->setChar($child->getAttribute('column'))
          ->setName($this->getLinterName())
          ->setCode($code)
          ->setDescription($child->getAttribute('message'))
          ->setSeverity($this->getLintMessageSeverity($code));

        $messages[] = $message;
      }
    }

    return $messages;
  }
}
