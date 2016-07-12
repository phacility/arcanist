<?php

final class ArcanistInvalidModifiersXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 72;

  public function getLintName() {
    return pht('Invalid Modifiers');
  }

  public function process(XHPASTNode $root) {
    $methods = $root->selectDescendantsOfTypes(array(
      'n_CLASS_MEMBER_MODIFIER_LIST',
      'n_METHOD_MODIFIER_LIST',
    ));

    foreach ($methods as $method) {
      $modifiers = $method->getChildren();

      $is_abstract = false;
      $is_final    = false;
      $is_static   = false;
      $visibility  = null;

      foreach ($modifiers as $modifier) {
        switch ($modifier->getConcreteString()) {
          case 'abstract':
            if ($method->getTypeName() == 'n_CLASS_MEMBER_MODIFIER_LIST') {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                  'Properties cannot be declared `%s`.',
                  'abstract'));
            }

            if ($is_abstract) {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                  'Multiple `%s` modifiers are not allowed.',
                  'abstract'));
            }

            if ($is_final) {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                'Cannot use the `%s` modifier on an `%s` class member',
                'final',
                'abstract'));
            }

            $is_abstract = true;
            break;

          case 'final':
            if ($is_abstract) {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                'Cannot use the `%s` modifier on an `%s` class member',
                'final',
                'abstract'));
            }

            if ($is_final) {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                  'Multiple `%s` modifiers are not allowed.',
                  'final'));
            }

            $is_final = true;
            break;
          case 'public':
          case 'protected':
          case 'private':
            if ($visibility) {
              $this->raiseLintAtNode(
                $modifier,
                pht('Multiple access type modifiers are not allowed.'));
            }

            $visibility = $modifier->getConcreteString();
            break;

          case 'static':
            if ($is_static) {
              $this->raiseLintAtNode(
                $modifier,
                pht(
                  'Multiple `%s` modifiers are not allowed.',
                  'static'));
            }
            break;
        }
      }
    }
  }

}
