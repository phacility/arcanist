<?php

final class ArcanistKeywordCasingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 40;

  public function getLintName() {
    return pht('Keyword Conventions');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $keywords = $root->selectTokensOfTypes(array(
      'T_REQUIRE_ONCE',
      'T_REQUIRE',
      'T_EVAL',
      'T_INCLUDE_ONCE',
      'T_INCLUDE',
      'T_LOGICAL_OR',
      'T_LOGICAL_XOR',
      'T_LOGICAL_AND',
      'T_PRINT',
      'T_INSTANCEOF',
      'T_CLONE',
      'T_NEW',
      'T_EXIT',
      'T_IF',
      'T_ELSEIF',
      'T_ELSE',
      'T_ENDIF',
      'T_ECHO',
      'T_DO',
      'T_WHILE',
      'T_ENDWHILE',
      'T_FOR',
      'T_ENDFOR',
      'T_FOREACH',
      'T_ENDFOREACH',
      'T_DECLARE',
      'T_ENDDECLARE',
      'T_AS',
      'T_SWITCH',
      'T_ENDSWITCH',
      'T_CASE',
      'T_DEFAULT',
      'T_BREAK',
      'T_CONTINUE',
      'T_GOTO',
      'T_FUNCTION',
      'T_CONST',
      'T_RETURN',
      'T_TRY',
      'T_CATCH',
      'T_THROW',
      'T_USE',
      'T_GLOBAL',
      'T_PUBLIC',
      'T_PROTECTED',
      'T_PRIVATE',
      'T_FINAL',
      'T_ABSTRACT',
      'T_STATIC',
      'T_VAR',
      'T_UNSET',
      'T_ISSET',
      'T_EMPTY',
      'T_HALT_COMPILER',
      'T_CLASS',
      'T_INTERFACE',
      'T_EXTENDS',
      'T_IMPLEMENTS',
      'T_LIST',
      'T_ARRAY',
      'T_NAMESPACE',
      'T_INSTEADOF',
      'T_CALLABLE',
      'T_TRAIT',
      'T_YIELD',
      'T_FINALLY',
    ));
    foreach ($keywords as $keyword) {
      $value = $keyword->getValue();

      if ($value != strtolower($value)) {
        $this->raiseLintAtToken(
          $keyword,
          pht(
            "Convention: spell keyword '%s' as '%s'.",
            $value,
            strtolower($value)),
          strtolower($value));
      }
    }

    $symbols = $root->selectDescendantsOfType('n_SYMBOL_NAME');
    foreach ($symbols as $symbol) {
      static $interesting_symbols = array(
        'false' => true,
        'null'  => true,
        'true'  => true,
      );

      $symbol_name = $symbol->getConcreteString();

      if ($symbol->getParentNode()->getTypeName() == 'n_FUNCTION_CALL') {
        continue;
      }

      if (idx($interesting_symbols, strtolower($symbol_name))) {
        if ($symbol_name != strtolower($symbol_name)) {
          $this->raiseLintAtNode(
            $symbol,
            pht(
              "Convention: spell keyword '%s' as '%s'.",
              $symbol_name,
              strtolower($symbol_name)),
            strtolower($symbol_name));
        }
      }
    }

    $magic_constants = $root->selectTokensOfTypes(array(
      'T_CLASS_C',
      'T_METHOD_C',
      'T_FUNC_C',
      'T_LINE',
      'T_FILE',
      'T_NS_C',
      'T_DIR',
      'T_TRAIT_C',
    ));

    foreach ($magic_constants as $magic_constant) {
      $value = $magic_constant->getValue();

      if ($value != strtoupper($value)) {
        $this->raiseLintAtToken(
          $magic_constant,
          pht('Magic constants should be uppercase.'),
          strtoupper($value));
      }
    }
  }

}
