<?php

final class ArcanistUnsafeDynamicStringXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 86;

  private $dynamicStringFunctions = array();
  private $dynamicStringClasses   = array();

  public function getLintName() {
    return pht('Unsafe Usage of Dynamic String');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'xhpast.dynamic-string.classes' => array(
        'type' => 'optional map<string, string>',
        'help' => pht(
          'Classes which should should not be used because they represent the '.
          'unsafe usage of dynamic strings.'),
      ),
      'xhpast.dynamic-string.functions' => array(
        'type' => 'optional map<string, string>',
        'help' => pht(
          'Functions which should should not be used because they represent '.
          'the unsafe usage of dynamic strings.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'xhpast.dynamic-string.classes':
        $this->dynamicStringClasses = $value;
        return;

      case 'xhpast.dynamic-string.functions':
        $this->dynamicStringFunctions = $value;
        return;

      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  public function process(XHPASTNode $root) {
    $this->lintUnsafeDynamicStringClasses($root);
    $this->lintUnsafeDynamicStringFunctions($root);
  }

  private function lintUnsafeDynamicStringClasses(XHPASTNode $root) {
    $news = $root->selectDescendantsOfType('n_NEW');
    $this->lintUnsafeDynamicStringCall($news, $this->dynamicStringClasses);
  }

  private function lintUnsafeDynamicStringFunctions(XHPASTNode $root) {
    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    $this->lintUnsafeDynamicStringCall($calls, $this->dynamicStringFunctions);
  }

  private function lintUnsafeDynamicStringCall(
    AASTNodeList $calls,
    array $safe) {

    $safe = array_combine(
      array_map('strtolower', array_keys($safe)),
      $safe);

    foreach ($calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();
      $param = idx($safe, strtolower($name));

      if ($param === null) {
        continue;
      }

      $parameters = $call->getChildByIndex(1);
      if (count($parameters->getChildren()) <= $param) {
        continue;
      }

      $identifier = $parameters->getChildByIndex($param);
      if (!$identifier->isConstantString()) {
        $this->raiseLintAtNode(
          $call,
          pht(
            "Parameter %d of `%s` should be a scalar string, ".
            "otherwise it's not safe.",
            $param + 1,
            $name));
      }
    }
  }

}
