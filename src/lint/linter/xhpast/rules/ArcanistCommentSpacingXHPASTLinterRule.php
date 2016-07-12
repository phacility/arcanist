<?php

final class ArcanistCommentSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 34;

  public function getLintName() {
    return pht('Comment Spaces');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    foreach ($root->selectTokensOfType('T_COMMENT') as $comment) {
      $value = $comment->getValue();

      if ($value[0] !== '#') {
        $match = null;

        if (preg_match('@^(/[/*]+)[^/*\s]@', $value, $match)) {
          $this->raiseLintAtOffset(
            $comment->getOffset(),
            pht('Put space after comment start.'),
            $match[1],
            $match[1].' ');
        }
      }
    }
  }

}
