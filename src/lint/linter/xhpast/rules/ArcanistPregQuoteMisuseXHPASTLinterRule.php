<?php

/**
 * @{function:preg_quote} takes two arguments, but the second one is optional
 * because it is possible to use `()`, `[]` or `{}` as regular expression
 * delimiters. If you don't pass a second argument, you're probably going to
 * get something wrong.
 */
final class ArcanistPregQuoteMisuseXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 14;

  public function getLintName() {
    return pht('Misuse of %s', 'preg_quote()');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $function_calls = $this->getFunctionCalls($root, array('preg_quote'));

    foreach ($function_calls as $call) {
      $parameter_list = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
      if (count($parameter_list->getChildren()) !== 2) {
        $this->raiseLintAtNode(
          $call,
          pht(
            'If you use pattern delimiters that require escaping '.
            '(such as `%s`, but not `%s`) then you should pass two '.
            'arguments to %s, so that %s knows which delimiter to escape.',
            '//',
            '()',
            'preg_quote()',
            'preg_quote()'));
      }
    }
  }

}
