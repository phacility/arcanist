<?php

/**
 * Find cases where a `foreach` loop is being iterated using a variable
 * reference and the same variable is used outside of the loop without calling
 * `unset()` or reassigning the variable to another variable reference.
 *
 *   COUNTEREXAMPLE
 *   foreach ($ar as &$a) {
 *     // ...
 *   }
 *   $a = 1; // <-- Raises an error for using $a
 */
final class ArcanistReusedIteratorReferenceXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 39;

  public function getLintName() {
    return pht('Reuse of Iterator References');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $defs = $root->selectDescendantsOfTypes(array(
      'n_FUNCTION_DECLARATION',
      'n_METHOD_DECLARATION',
    ));

    foreach ($defs as $def) {
      $body = $def->getChildByIndex(6);
      if ($body->getTypeName() === 'n_EMPTY') {
        // Abstract method declaration.
        continue;
      }

      $exclude = array();

      // Exclude uses of variables, unsets, and foreach loops
      // within closures - they are checked on their own
      $func_defs = $body->selectDescendantsOfType('n_FUNCTION_DECLARATION');
      foreach ($func_defs as $func_def) {
        $vars = $func_def->selectDescendantsOfType('n_VARIABLE');
        foreach ($vars as $var) {
          $exclude[$var->getID()] = true;
        }

        $unset_lists = $func_def->selectDescendantsOfType('n_UNSET_LIST');
        foreach ($unset_lists as $unset_list) {
          $exclude[$unset_list->getID()] = true;
        }

        $foreaches = $func_def->selectDescendantsOfType('n_FOREACH');
        foreach ($foreaches as $foreach) {
          $exclude[$foreach->getID()] = true;
        }
      }

      // Find all variables that are unset within the scope
      $unset_vars = array();
      $unset_lists = $body->selectDescendantsOfType('n_UNSET_LIST');
      foreach ($unset_lists as $unset_list) {
        if (isset($exclude[$unset_list->getID()])) {
          continue;
        }

        $unset_list_vars = $unset_list->selectDescendantsOfType('n_VARIABLE');
        foreach ($unset_list_vars as $var) {
          $concrete = $this->getConcreteVariableString($var);
          $unset_vars[$concrete][] = $var->getOffset();
          $exclude[$var->getID()] = true;
        }
      }

      // Find all reference variables in foreach expressions
      $reference_vars = array();
      $foreaches = $body->selectDescendantsOfType('n_FOREACH');
      foreach ($foreaches as $foreach) {
        if (isset($exclude[$foreach->getID()])) {
          continue;
        }

        $foreach_expr = $foreach->getChildOfType(0, 'n_FOREACH_EXPRESSION');
        $var = $foreach_expr->getChildByIndex(2);
        if ($var->getTypeName() !== 'n_VARIABLE_REFERENCE') {
          continue;
        }

        $reference = $var->getChildByIndex(0);
        if ($reference->getTypeName() !== 'n_VARIABLE') {
          continue;
        }

        $reference_name = $this->getConcreteVariableString($reference);
        $reference_vars[$reference_name][] = $reference->getOffset();
        $exclude[$reference->getID()] = true;

        // Exclude uses of the reference variable within the foreach loop
        $foreach_vars = $foreach->selectDescendantsOfType('n_VARIABLE');
        foreach ($foreach_vars as $var) {
          $name = $this->getConcreteVariableString($var);
          if ($name === $reference_name) {
            $exclude[$var->getID()] = true;
          }
        }
      }

      // Allow usage if the reference variable is assigned to another
      // reference variable
      $binary = $body->selectDescendantsOfType('n_BINARY_EXPRESSION');
      foreach ($binary as $expr) {
        if ($expr->getChildByIndex(1)->getConcreteString() !== '=') {
          continue;
        }
        $lval = $expr->getChildByIndex(0);
        if ($lval->getTypeName() !== 'n_VARIABLE') {
          continue;
        }
        $rval = $expr->getChildByIndex(2);
        if ($rval->getTypeName() !== 'n_VARIABLE_REFERENCE') {
          continue;
        }

        // Counts as unsetting a variable
        $concrete = $this->getConcreteVariableString($lval);
        $unset_vars[$concrete][] = $lval->getOffset();
        $exclude[$lval->getID()] = true;
      }

      $all_vars = array();
      $all = $body->selectDescendantsOfType('n_VARIABLE');
      foreach ($all as $var) {
        if (isset($exclude[$var->getID()])) {
          continue;
        }

        $name = $this->getConcreteVariableString($var);

        if (!isset($reference_vars[$name])) {
          continue;
        }

        // Find the closest reference offset to this variable
        $reference_offset = null;
        foreach ($reference_vars[$name] as $offset) {
          if ($offset < $var->getOffset()) {
            $reference_offset = $offset;
          } else {
            break;
          }
        }
        if (!$reference_offset) {
          continue;
        }

        // Check if an unset exists between reference and usage of this
        // variable
        $warn = true;
        if (isset($unset_vars[$name])) {
          foreach ($unset_vars[$name] as $unset_offset) {
            if ($unset_offset > $reference_offset &&
                $unset_offset < $var->getOffset()) {
                $warn = false;
                break;
            }
          }
        }
        if ($warn) {
          $this->raiseLintAtNode(
            $var,
            pht(
              'This variable was used already as a by-reference iterator '.
              'variable. Such variables survive outside the `%s` loop, '.
              'do not reuse.',
              'foreach'));
        }
      }

    }
  }

}
