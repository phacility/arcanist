<?php

final class ArcanistCommentStyleXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 18;

  public function getLintName() {
    return pht('Comment Style');
  }

  public function process(XHPASTNode $root) {
    foreach ($root->selectTokensOfType('T_COMMENT') as $comment) {
      $value = $comment->getValue();

      if ($value[0] !== '#') {
        continue;
      }

      // Don't warn about PHP comment directives. In particular, we need
      // to use "#[\ReturnTypeWillChange]" to implement "Iterator" in a way
      // that is compatible with PHP 8.1 and older versions of PHP prior
      // to the introduction of return types. See T13588.
      if (preg_match('/^#\\[\\\\/', $value)) {
        continue;
      }

      $this->raiseLintAtOffset(
        $comment->getOffset(),
        pht(
          'Use `%s` single-line comments, not `%s`.',
          '//',
          '#'),
        '#',
        preg_match('/^#\S/', $value) ? '// ' : '//');
    }
  }

}
