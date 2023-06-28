<?php

final class ArcanistClassNameLiteralXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 62;

  public function getLintName() {
    return pht('Class Name Literal');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $class_declarations = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($class_declarations as $class_declaration) {
      if ($class_declaration->getChildByIndex(1)->getTypeName() == 'n_EMPTY') {
        continue;
      }
      $class_name = $class_declaration
        ->getChildOfType(1, 'n_CLASS_NAME')
        ->getConcreteString();

      $strings = $class_declaration->selectDescendantsOfType('n_STRING_SCALAR');

      foreach ($strings as $string) {
        $contents = substr($string->getSemanticString(), 1, -1);
        $replacement = null;

        if ($contents == $class_name) {
          $replacement = '__CLASS__';
        }

        // NOTE: We're only warning when the entire string content is the
        // class name. It's okay to hard-code the class name as part of a
        // longer string, like an error or exception message.

        // Sometimes the class name (like "Filesystem") is also a valid part
        // of the message, which makes this warning a false positive.

        // Even when we're generating a true positive by detecting a class
        // name in part of a longer string, the cost of an error message
        // being out-of-date is generally very small (mild confusion, but
        // no incorrect beahavior) and using "__CLASS__" in errors is often
        // clunky.

        $regex = '(^'.preg_quote($class_name).'$)';
        if (!preg_match($regex, $contents)) {
          continue;
        }

        $this->raiseLintAtNode(
          $string,
          pht(
            'Prefer "__CLASS__" over hard-coded class names.'),
          $replacement);
      }
    }
  }

}
