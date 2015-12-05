<?php

final class ArcanistPublicPropertyXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 130;

  public function getLintName() {
    return pht('Use of `%s` Properties', 'public');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $members = $root->selectDescendantsOfType(
      'n_CLASS_MEMBER_DECLARATION_LIST');

    foreach ($members as $member) {
      $modifiers = $this->getModifiers($member);

      if (isset($modifiers['public'])) {
        $this->raiseLintAtNode(
          $member,
          pht(
            '`%s` properties should be avoided. Instead of exposing '.
            'the property value directly, consider using getter '.
            'and setter methods.',
            'public'));
      }
    }
  }

}
