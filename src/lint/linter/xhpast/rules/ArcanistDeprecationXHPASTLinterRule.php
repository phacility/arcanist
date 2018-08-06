<?php

final class ArcanistDeprecationXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 85;

  private $deprecatedFunctions = array();

  public function getLintName() {
    return pht('Use of Deprecated Function');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function getLinterConfigurationOptions() {
    return parent::getLinterConfigurationOptions() + array(
      'xhpast.deprecated.functions' => array(
        'type' => 'optional map<string, string>',
        'help' => pht(
          'Functions which should be considered deprecated.'),
      ),
    );
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'xhpast.deprecated.functions':
        $this->deprecatedFunctions = $value;
        return;

      default:
        return parent::getLinterConfigurationOptions();
    }
  }

  public function process(XHPASTNode $root) {
    $map = $this->deprecatedFunctions;
    $function_calls = $this->getFunctionCalls($root, array_keys($map));

    foreach ($function_calls as $call) {
      $name = $call
        ->getChildByIndex(0)
        ->getConcreteString();

      $name = strtolower($name);
      if (empty($map[$name])) {
        continue;
      }

      $this->raiseLintAtNode($call, $map[$name]);
    }
  }

}
