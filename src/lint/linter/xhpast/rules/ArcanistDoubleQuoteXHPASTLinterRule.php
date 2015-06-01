<?php

final class ArcanistDoubleQuoteXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 41;

  public function getLintName() {
    return pht('Unnecessary Double Quotes');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $nodes = $root->selectDescendantsOfTypes(array(
      'n_CONCATENATION_LIST',
      'n_STRING_SCALAR',
    ));

    foreach ($nodes as $node) {
      $strings = array();

      if ($node->getTypeName() === 'n_CONCATENATION_LIST') {
        $strings = $node->selectDescendantsOfType('n_STRING_SCALAR');
      } else if ($node->getTypeName() === 'n_STRING_SCALAR') {
        $strings = array($node);

        if ($node->getParentNode()->getTypeName() === 'n_CONCATENATION_LIST') {
          continue;
        }
      }

      $valid = false;
      $invalid_nodes = array();
      $fixes = array();

      foreach ($strings as $string) {
        $concrete_string = $string->getConcreteString();
        $single_quoted = ($concrete_string[0] === "'");
        $contents = substr($concrete_string, 1, -1);

        // Double quoted strings are allowed when the string contains the
        // following characters.
        static $allowed_chars = array(
          '\n',
          '\r',
          '\t',
          '\v',
          '\e',
          '\f',
          '\'',
          '\0',
          '\1',
          '\2',
          '\3',
          '\4',
          '\5',
          '\6',
          '\7',
          '\x',
        );

        $contains_special_chars = false;
        foreach ($allowed_chars as $allowed_char) {
          if (strpos($contents, $allowed_char) !== false) {
            $contains_special_chars = true;
          }
        }

        if (!$string->isConstantString()) {
          $valid = true;
        } else if ($contains_special_chars && !$single_quoted) {
          $valid = true;
        } else if (!$contains_special_chars && !$single_quoted) {
          $invalid_nodes[] = $string;
          $fixes[$string->getID()] = "'".str_replace('\"', '"', $contents)."'";
        }
      }

      if (!$valid) {
        foreach ($invalid_nodes as $invalid_node) {
          $this->raiseLintAtNode(
            $invalid_node,
            pht(
              'String does not require double quotes. For consistency, '.
              'prefer single quotes.'),
            $fixes[$invalid_node->getID()]);
        }
      }
    }
  }

}
