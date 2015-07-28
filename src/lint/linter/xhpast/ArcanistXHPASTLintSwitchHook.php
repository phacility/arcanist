<?php

/**
 * You can extend this class and set `xhpast.switchhook` in your `.arclint`
 * to have an opportunity to override results for linting `switch` statements.
 */
abstract class ArcanistXHPASTLintSwitchHook extends Phobject {

  /**
   * @return bool True if token safely ends the block.
   */
  abstract public function checkSwitchToken(XHPASTToken $token);

}
