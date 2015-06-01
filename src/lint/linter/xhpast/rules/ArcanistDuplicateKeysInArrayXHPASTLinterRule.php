<?php

/**
 * Finds duplicate keys in array initializers, as in
 * `array(1 => 'anything', 1 => 'foo')`. Since the first entry is ignored, this
 * is almost certainly an error.
 */
final class ArcanistDuplicateKeysInArrayXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 22;

  public function getLintName() {
    return pht('Duplicate Keys in Array');
  }

  public function process(XHPASTNode $root) {
    $array_literals = $root->selectDescendantsOfType('n_ARRAY_LITERAL');

    foreach ($array_literals as $array_literal) {
      $nodes_by_key = array();
      $keys_warn = array();
      $list_node = $array_literal->getChildByIndex(0);

      foreach ($list_node->getChildren() as $array_entry) {
        $key_node = $array_entry->getChildByIndex(0);

        switch ($key_node->getTypeName()) {
          case 'n_STRING_SCALAR':
          case 'n_NUMERIC_SCALAR':
            // Scalars: array(1 => 'v1', '1' => 'v2');
            $key = 'scalar:'.(string)$key_node->evalStatic();
            break;

          case 'n_SYMBOL_NAME':
          case 'n_VARIABLE':
          case 'n_CLASS_STATIC_ACCESS':
            // Constants: array(CONST => 'v1', CONST => 'v2');
            // Variables: array($a => 'v1', $a => 'v2');
            // Class constants and vars: array(C::A => 'v1', C::A => 'v2');
            $key = $key_node->getTypeName().':'.$key_node->getConcreteString();
            break;

          default:
            $key = null;
            break;
        }

        if ($key !== null) {
          if (isset($nodes_by_key[$key])) {
            $keys_warn[$key] = true;
          }
          $nodes_by_key[$key][] = $key_node;
        }
      }

      foreach ($keys_warn as $key => $_) {
        $node = array_pop($nodes_by_key[$key]);
        $message = $this->raiseLintAtNode(
          $node,
          pht(
            'Duplicate key in array initializer. '.
            'PHP will ignore all but the last entry.'));

        $locations = array();
        foreach ($nodes_by_key[$key] as $node) {
          $locations[] = $this->getOtherLocation($node->getOffset());
        }
        $message->setOtherLocations($locations);
      }
    }
  }

}
