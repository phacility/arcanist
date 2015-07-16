<?php

/**
 * Find cases where loops get nested inside each other but use the same
 * iterator variable. For example:
 *
 *   COUNTEREXAMPLE
 *   foreach ($list as $thing) {
 *     foreach ($stuff as $thing) { // <-- Raises an error for reuse of $thing
 *       // ...
 *     }
 *   }
 */
final class ArcanistReusedIteratorXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 23;

  public function getLintName() {
    return pht('Reuse of Iterator Variable');
  }

  public function process(XHPASTNode $root) {
    $used_vars = array();

    $for_loops = $root->selectDescendantsOfType('n_FOR');
    foreach ($for_loops as $for_loop) {
      $var_map = array();

      // Find all the variables that are assigned to in the for() expression.
      $for_expr = $for_loop->getChildOfType(0, 'n_FOR_EXPRESSION');
      $bin_exprs = $for_expr->selectDescendantsOfType('n_BINARY_EXPRESSION');
      foreach ($bin_exprs as $bin_expr) {
        if ($bin_expr->getChildByIndex(1)->getConcreteString() === '=') {
          $var = $bin_expr->getChildByIndex(0);
          $var_map[$var->getConcreteString()] = $var;
        }
      }

      $used_vars[$for_loop->getID()] = $var_map;
    }

    $foreach_loops = $root->selectDescendantsOfType('n_FOREACH');
    foreach ($foreach_loops as $foreach_loop) {
      $var_map = array();

      $foreach_expr = $foreach_loop->getChildOfType(0, 'n_FOREACH_EXPRESSION');

      // We might use one or two vars, i.e. "foreach ($x as $y => $z)" or
      // "foreach ($x as $y)".
      $possible_used_vars = array(
        $foreach_expr->getChildByIndex(1),
        $foreach_expr->getChildByIndex(2),
      );
      foreach ($possible_used_vars as $var) {
        if ($var->getTypeName() === 'n_EMPTY') {
          continue;
        }
        $name = $var->getConcreteString();
        $name = trim($name, '&'); // Get rid of ref silliness.
        $var_map[$name] = $var;
      }

      $used_vars[$foreach_loop->getID()] = $var_map;
    }

    $all_loops = $for_loops->add($foreach_loops);
    foreach ($all_loops as $loop) {
      $child_loops = $loop->selectDescendantsOfTypes(array(
        'n_FOR',
        'n_FOREACH',
      ));

      $outer_vars = $used_vars[$loop->getID()];
      foreach ($child_loops as $inner_loop) {
        $inner_vars = $used_vars[$inner_loop->getID()];
        $shared = array_intersect_key($outer_vars, $inner_vars);
        if ($shared) {
          $shared_desc = implode(', ', array_keys($shared));
          $message = $this->raiseLintAtNode(
            $inner_loop->getChildByIndex(0),
            pht(
              'This loop reuses iterator variables (%s) from an '.
              'outer loop. You might be clobbering the outer iterator. '.
              'Change the inner loop to use a different iterator name.',
              $shared_desc));

          $locations = array();
          foreach ($shared as $var) {
            $locations[] = $this->getOtherLocation($var->getOffset());
          }
          $message->setOtherLocations($locations);
        }
      }
    }
  }

}
