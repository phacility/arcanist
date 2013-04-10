<?php

/**
 * @group linter
 */
abstract class ArcanistBaseXHPASTLinter extends ArcanistFutureLinter {

  protected function raiseLintAtToken(
    XHPASTToken $token,
    $code,
    $desc,
    $replace = null) {
    return $this->raiseLintAtOffset(
      $token->getOffset(),
      $code,
      $desc,
      $token->getValue(),
      $replace);
  }

  protected function raiseLintAtNode(
    XHPASTNode $node,
    $code,
    $desc,
    $replace = null) {
    return $this->raiseLintAtOffset(
      $node->getOffset(),
      $code,
      $desc,
      $node->getConcreteString(),
      $replace);
  }

}
