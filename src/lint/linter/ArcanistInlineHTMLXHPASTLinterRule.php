<?php

final class ArcanistInlineHTMLXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 78;

  public function getLintName() {
    return pht('Inline HTML');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_DISABLED;
  }

  public function process(XHPASTNode $root) {
    $inline_html = $root->selectTokensOfType('T_INLINE_HTML');

    foreach ($inline_html as $html) {
      if (substr($html->getValue(), 0, 2) == '#!') {
        // Ignore shebang lines.
        continue;
      }

      if (preg_match('/^\s*$/', $html->getValue())) {
        continue;
      }

      $this->raiseLintAtToken(
        $html,
        pht('PHP files must only contain PHP code.'));
      break;
    }
  }

}
