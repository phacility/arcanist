<?php

final class ArcanistUnnecessarySymbolAliasXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 99;

  public function getLintName() {
    return pht('Unnecessary Symbol Alias');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $uses = $root->selectDescendantsOfType('n_USE');

    foreach ($uses as $use) {
      $symbol = $use->getChildOfType(0, 'n_SYMBOL_NAME');
      $alias  = $use->getChildByIndex(1);

      if ($alias->getTypeName() == 'n_EMPTY') {
        continue;
      }

      $symbol_name = last(explode('\\', $symbol->getConcreteString()));
      $alias_name  = $alias->getConcreteString();

      if ($symbol_name == $alias_name) {
        $this->raiseLintAtNode(
          $use,
          pht(
            'Importing `%s` with `%s` is unnecessary because the aliased '.
            'name is identical to the imported symbol name.',
            $symbol->getConcreteString(),
            sprintf('as %s', $alias_name)),
          $symbol->getConcreteString());
      }
    }
  }

}
