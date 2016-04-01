<?php

final class ArcanistIsAShouldBeInstanceOfXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 111;

  public function getLintName() {
    return pht('`%s` Should Be `%s`', 'is_a', 'instanceof');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $calls = $this->getFunctionCalls($root, array('is_a'));

    foreach ($calls as $call) {
      $parameters = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');

      if (count($parameters->getChildren()) > 2) {
        // If the `$allow_string` parameter is `true` then the `instanceof`
        // operator cannot be used. Evaluating whether an expression is truthy
        // or falsely is hard, and so we only check that the `$allow_string`
        // parameter is either absent or literally `false`.
        $allow_string = $parameters->getChildByIndex(2);

        if (strtolower($allow_string->getConcreteString()) != 'false') {
          continue;
        }
      }

      $object = $parameters->getChildByIndex(0);
      $class  = $parameters->getChildByIndex(1);

      switch ($class->getTypeName()) {
        case 'n_STRING_SCALAR':
          $replacement = stripslashes(
            substr($class->getConcreteString(), 1, -1));
          break;

        case 'n_VARIABLE':
          $replacement = $class->getConcreteString();
          break;

        default:
          $replacement = null;
          break;
      }

      $this->raiseLintAtNode(
        $call,
        pht(
          'Use `%s` instead of `%s`. The former is a language '.
          'construct whereas the latter is a function call, which '.
          'has additional overhead.',
          'instanceof',
          'is_a'),
        ($replacement === null)
          ? null
          : sprintf(
            '%s instanceof %s',
            $object->getConcreteString(),
            $replacement));
    }
  }

}
