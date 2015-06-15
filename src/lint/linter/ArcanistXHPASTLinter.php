<?php

/**
 * Uses XHPAST to apply lint rules to PHP.
 */
final class ArcanistXHPASTLinter extends ArcanistBaseXHPASTLinter {

  private $rules = array();

  public function __construct() {
    $this->rules = ArcanistXHPASTLinterRule::loadAllRules();
  }

  public function __clone() {
    $rules = $this->rules;

    $this->rules = array();
    foreach ($rules as $rule) {
      $this->rules[] = clone $rule;
    }
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
    return mpull($this->rules, 'getLintName', 'getLintID');
  }

  public function getLintSeverityMap() {
    return mpull($this->rules, 'getLintSeverity', 'getLintID');
  }

  public function getLinterConfigurationOptions() {
    return parent::getLinterConfigurationOptions() + array_mergev(
      mpull($this->rules, 'getLinterConfigurationOptions'));
  }

  public function setLinterConfigurationValue($key, $value) {
    foreach ($this->rules as $rule) {
      foreach ($rule->getLinterConfigurationOptions() as $k => $spec) {
        if ($k == $key) {
          return $rule->setLinterConfigurationValue($key, $value);
        }
      }
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
