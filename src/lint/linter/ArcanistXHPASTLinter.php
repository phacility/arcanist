<?php

/**
 * Uses XHPAST to apply lint rules to PHP.
 */
final class ArcanistXHPASTLinter extends ArcanistBaseXHPASTLinter {

  private $rules = array();

  private $lintNameMap;
  private $lintSeverityMap;

  public function __construct() {
    $this->setRules(ArcanistXHPASTLinterRule::loadAllRules());
  }

  public function __clone() {
    $rules = $this->rules;

    $this->rules = array();
    foreach ($rules as $rule) {
      $this->rules[] = clone $rule;
    }
  }

  /**
   * Set the XHPAST linter rules which are enforced by this linter.
   *
   * This is primarily useful for unit tests in which it is desirable to test
   * linter rules in isolation. By default, all linter rules will be enabled.
   *
   * @param  list<ArcanistXHPASTLinterRule>
   * @return this
   */
  public function setRules(array $rules) {
    assert_instances_of($rules, 'ArcanistXHPASTLinterRule');
    $this->rules = $rules;
    return $this;
  }

  public function getInfoName() {
    return pht('XHPAST Lint');
  }

  public function getInfoDescription() {
    return pht('Use XHPAST to enforce coding conventions on PHP source files.');
  }

  public function getLinterName() {
    return 'XHP';
  }

  public function getLinterConfigurationName() {
    return 'xhpast';
  }

  public function getLintNameMap() {
    if ($this->lintNameMap === null) {
      $this->lintNameMap = mpull(
        $this->rules,
        'getLintName',
        'getLintID');
    }

    return $this->lintNameMap;
  }

  public function getLintSeverityMap() {
    if ($this->lintSeverityMap === null) {
      $this->lintSeverityMap = mpull(
        $this->rules,
        'getLintSeverity',
        'getLintID');
    }

    return $this->lintSeverityMap;
  }

  public function getLinterConfigurationOptions() {
    return parent::getLinterConfigurationOptions() + array_mergev(
      mpull($this->rules, 'getLinterConfigurationOptions'));
  }

  public function setLinterConfigurationValue($key, $value) {
    $matched = false;

    foreach ($this->rules as $rule) {
      foreach ($rule->getLinterConfigurationOptions() as $k => $spec) {
        if ($k == $key) {
          $matched = true;
          $rule->setLinterConfigurationValue($key, $value);
        }
      }
    }

    if ($matched) {
      return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getVersion() {
    // TODO: Improve this.
    return count($this->rules);
  }

  protected function resolveFuture($path, Future $future) {
    $tree = $this->getXHPASTTreeForPath($path);
    if (!$tree) {
      $ex = $this->getXHPASTExceptionForPath($path);
      if ($ex instanceof XHPASTSyntaxErrorException) {
        $this->raiseLintAtLine(
          $ex->getErrorLine(),
          1,
          ArcanistSyntaxErrorXHPASTLinterRule::ID,
          pht(
            'This file contains a syntax error: %s',
            $ex->getMessage()));
      } else if ($ex instanceof Exception) {
        $this->raiseLintAtPath(
          ArcanistUnableToParseXHPASTLinterRule::ID,
          $ex->getMessage());
      }
      return;
    }

    $root = $tree->getRootNode();

    foreach ($this->rules as $rule) {
      if ($this->isCodeEnabled($rule->getLintID())) {
        $rule->setLinter($this);
        $rule->process($root);
      }
    }
  }

}
