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

        $regex = '/\b'.preg_quote($class_name, '/').'\b/';
        if (!preg_match($regex, $contents)) {
          continue;
        }

        $this->raiseLintAtNode(
          $string,
          pht(
            "Don't hard-code class names, use `%s` instead.",
            '__CLASS__'),
          $replacement);
      }
    }
  }

}
