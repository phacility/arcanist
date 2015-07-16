<?php

final class ArcanistTestXHPASTLintSwitchHook
  extends ArcanistXHPASTLintSwitchHook {

  public function checkSwitchToken(XHPASTToken $token) {
    if ($token->getTypeName() == 'T_STRING') {
      switch (strtolower($token->getValue())) {
        case 'throw_exception':
          return true;
      }
    }
    return false;
  }

}
