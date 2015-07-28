<?php

final class ArcanistImplicitVisibilityXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 52;

  public function getLintName() {
    return pht('Implicit Method Visibility');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $this->lintMethodVisibility($root);
    $this->lintPropertyVisibility($root);
  }

  private function lintMethodVisibility(XHPASTNode $root) {
    static $visibilities = array(
      'public',
      'protected',
      'private',
    );

    $methods = $root->selectDescendantsOfType('n_METHOD_DECLARATION');

    foreach ($methods as $method) {
      $modifiers_list = $method->getChildOfType(0, 'n_METHOD_MODIFIER_LIST');

      foreach ($modifiers_list->getChildren() as $modifier) {
        if (in_array($modifier->getConcreteString(), $visibilities)) {
          continue 2;
        }
      }

      if ($modifiers_list->getChildren()) {
        $node = $modifiers_list;
      } else {
        $node = $method;
      }

      $this->raiseLintAtNode(
        $node,
        pht('Methods should have their visibility declared explicitly.'),
        'public '.$node->getConcreteString());
    }
  }

  private function lintPropertyVisibility(XHPASTNode $root) {
    static $visibilities = array(
      'public',
      'protected',
      'private',
    );

    $nodes = $root->selectDescendantsOfType('n_CLASS_MEMBER_MODIFIER_LIST');

    foreach ($nodes as $node) {
      $modifiers = $node->getChildren();

      foreach ($modifiers as $modifier) {
        if ($modifier->getConcreteString() == 'var') {
          $this->raiseLintAtNode(
            $modifier,
            pht(
              'Use `%s` instead of `%s` to indicate public visibility.',
              'public',
              'var'),
            'public');
          continue 2;
        }

        if (in_array($modifier->getConcreteString(), $visibilities)) {
          continue 2;
        }
      }

      $this->raiseLintAtNode(
        $node,
        pht('Properties should have their visibility declared explicitly.'),
        'public '.$node->getConcreteString());
    }
  }

}
