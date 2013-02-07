<?php

/**
 * You can extend this class and set `lint.xhpast.switchhook` in your
 * `.arcconfig` to have an opportunity to override results for linting `switch`
 * statements.
 *
 * @group lint
 */
abstract class ArcanistXHPASTLintSwitchHook {

  /**
   * @return bool True if token safely ends the block.
   */
  abstract public function checkSwitchToken(XHPASTToken $token);

}
