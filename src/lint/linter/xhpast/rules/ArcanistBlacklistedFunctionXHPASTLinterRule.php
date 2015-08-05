<?php

final class ArcanistBlacklistedFunctionXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 51;

  private $blacklistedFunctions = array();

  public function getLintName() {
    return pht('Use of Blacklisted Function');
  }

  public function getLinterConfigurationOptions() {
    return parent::getLinterConfigurationOptions() + array(
      'xhpast.blacklisted.function' => array(
        'type' => 'optional map<string, string>',
        'help' => pht('Blacklisted functions which should not be used.'),
      ),
    );
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'xhpast.blacklisted.function':
        $this->blacklistedFunctions = $value;
        return;

      default:
        return parent::getLinterConfigurationOptions();
    }
  }

  public function process(XHPASTNode $root) {
    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');

    foreach ($calls as $call) {
      $node = $call->getChildByIndex(0);
      $name = $node->getConcreteString();

      $reason = idx($this->blacklistedFunctions, $name);

      if ($reason) {
        $this->raiseLintAtNode($node, $reason);
      }
    }
  }

}
