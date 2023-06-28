<?php

final class ArcanistPartialCatchXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 132;

  public function getLintName() {
    return pht('Partial Catch');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $catch_lists = $root->selectDescendantsOfType('n_CATCH_LIST');

    foreach ($catch_lists as $catch_list) {
      $catches = $catch_list->getChildrenOfType('n_CATCH');

      $classes = array();
      foreach ($catches as $catch) {
        $class_node = $catch->getChildOfType(0, 'n_CLASS_NAME');
        $class_name = $class_node->getConcreteString();
        $class_name = phutil_utf8_strtolower($class_name);

        $classes[$class_name] = $class_node;
      }

      $catches_exception = idx($classes, 'exception');
      $catches_throwable = idx($classes, 'throwable');

      if ($catches_exception && !$catches_throwable) {
        $this->raiseLintAtNode(
          $catches_exception,
          pht(
            'Try/catch block catches "Exception", but does not catch '.
            '"Throwable". In PHP7 and newer, some runtime exceptions '.
            'will escape this block.'));
      }
    }
  }

}
