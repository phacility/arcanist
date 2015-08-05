<?php

final class ArcanistTodoCommentXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 16;

  public function getLintName() {
    return pht('TODO Comment');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_DISABLED;
  }

  public function process(XHPASTNode $root) {
    $comments = $root->selectTokensOfTypes(array(
      'T_COMMENT',
      'T_DOC_COMMENT',
    ));

    foreach ($comments as $token) {
      $value = $token->getValue();
      if ($token->getTypeName() === 'T_DOC_COMMENT') {
        $regex = '/(TODO|@todo)/';
      } else {
        $regex = '/TODO/';
      }

      $matches = null;
      $preg = preg_match_all(
        $regex,
        $value,
        $matches,
        PREG_OFFSET_CAPTURE);

      foreach ($matches[0] as $match) {
        list($string, $offset) = $match;
        $this->raiseLintAtOffset(
          $token->getOffset() + $offset,
          pht('This comment has a TODO.'),
          $string);
      }
    }
  }

}
