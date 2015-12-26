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
      'T_ABSTRACT',
      'T_ARRAY',
      'T_AS',
      'T_BREAK',
      'T_CALLABLE',
      'T_CASE',
      'T_CATCH',
      'T_CLASS',
      'T_CLONE',
      'T_CONST',
      'T_CONTINUE',
      'T_DECLARE',
      'T_DEFAULT',
      'T_DO',
      'T_ECHO',
      'T_ELSE',
      'T_ELSEIF',
      'T_EMPTY',
      'T_ENDDECLARE',
      'T_ENDFOR',
      'T_ENDFOREACH',
      'T_ENDIF',
      'T_ENDSWITCH',
      'T_ENDWHILE',
      'T_EVAL',
      'T_EXIT',
      'T_EXTENDS',
      'T_FINAL',
      'T_FINALLY',
      'T_FOR',
      'T_FOREACH',
      'T_FUNCTION',
      'T_GLOBAL',
      'T_GOTO',
      'T_HALT_COMPILER',
      'T_IF',
      'T_IMPLEMENTS',
      'T_INCLUDE',
      'T_INCLUDE_ONCE',
      'T_INSTANCEOF',
      'T_INSTEADOF',
      'T_INTERFACE',
      'T_ISSET',
      'T_LIST',
      'T_LOGICAL_AND',
      'T_LOGICAL_OR',
      'T_LOGICAL_XOR',
      'T_NAMESPACE',
      'T_NEW',
      'T_PRINT',
      'T_PRIVATE',
      'T_PROTECTED',
      'T_PUBLIC',
      'T_REQUIRE',
      'T_REQUIRE_ONCE',
      'T_RETURN',
      'T_STATIC',
      'T_SWITCH',
      'T_THROW',
      'T_TRAIT',
      'T_TRY',
      'T_UNSET',
      'T_USE',
      'T_VAR',
      'T_WHILE',
      'T_YIELD',
    ));

    // Because there is no `T_SELF` or `T_PARENT` token.
    $class_static_accesses = $root
      ->selectDescendantsOfType('n_CLASS_DECLARATION')
      ->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
    foreach ($class_static_accesses as $class_static_access) {
      $class_ref = $class_static_access->getChildByIndex(0);

      switch (strtolower($class_ref->getConcreteString())) {
        case 'parent':
        case 'self':
          $tokens = $class_ref->getTokens();

          if (count($tokens) > 1) {
            throw new Exception(
              pht(
                'Unexpected tokens whilst processing `%s`.',
                __CLASS__));
          }

          $keywords[] = head($tokens);
          break;
      }
    }

    foreach ($keywords as $keyword) {
      $value = $keyword->getValue();

      if ($value != strtolower($value)) {
        $this->raiseLintAtToken(
          $keyword,
          pht(
            'Convention: spell keyword `%s` as `%s`.',
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
              'Convention: spell keyword `%s` as `%s`.',
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
