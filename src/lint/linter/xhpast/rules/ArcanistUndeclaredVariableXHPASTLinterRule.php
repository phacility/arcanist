<?php

final class ArcanistUndeclaredVariableXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 5;

  public function getLintName() {
    return pht('Use of Undeclared Variable');
  }

  public function process(XHPASTNode $root) {
    // These things declare variables in a function:
    //    Explicit parameters
    //    Assignment
    //    Assignment via list()
    //    Static
    //    Global
    //    Lexical vars
    //    Builtins ($this)
    //    foreach()
    //    catch
    //
    // These things make lexical scope unknowable:
    //    Use of extract()
    //    Assignment to variable variables ($$x)
    //    Global with variable variables
    //
    // These things don't count as "using" a variable:
    //    isset()
    //    empty()
    //    Static class variables
    //
    // The general approach here is to find each function/method declaration,
    // then:
    //
    //  1. Identify all the variable declarations, and where they first occur
    //     in the function/method declaration.
    //  2. Identify all the uses that don't really count (as above).
    //  3. Everything else must be a use of a variable.
    //  4. For each variable, check if any uses occur before the declaration
    //     and warn about them.
    //
    // We also keep track of where lexical scope becomes unknowable (e.g.,
    // because the function calls extract() or uses dynamic variables,
    // preventing us from keeping track of which variables are defined) so we
    // can stop issuing warnings after that.
    //
    // TODO: Support functions defined inside other functions which is commonly
    // used with anonymous functions.
    //
    // TODO: parse_str() also makes lexical scope unknowable, see D13857.

    $defs = $root->selectDescendantsOfTypes(array(
      'n_FUNCTION_DECLARATION',
      'n_METHOD_DECLARATION',
    ));

    foreach ($defs as $def) {

      // We keep track of the first offset where scope becomes unknowable, and
      // silence any warnings after that. Default it to INT_MAX so we can min()
      // it later to keep track of the first problem we encounter.
      $scope_destroyed_at = PHP_INT_MAX;

      $declarations = array(
        '$this'     => 0,
      ) + array_fill_keys($this->getSuperGlobalNames(), 0);
      $declaration_tokens = array();
      $exclude_tokens = array();
      $exclude_strings = array();
      $vars = array();

      // First up, find all the different kinds of declarations, as explained
      // above. Put the tokens into the $vars array.

      $param_list = $def->getChildOfType(3, 'n_DECLARATION_PARAMETER_LIST');
      $param_vars = $param_list->selectDescendantsOfType('n_VARIABLE');
      foreach ($param_vars as $var) {
        $vars[] = $var;
      }

      // This is PHP5.3 closure syntax: function () use ($x) {};
      $lexical_vars = $def
        ->getChildByIndex(4)
        ->selectDescendantsOfType('n_VARIABLE');
      foreach ($lexical_vars as $var) {
        $vars[] = $var;
      }

      $body = $def->getChildByIndex(5);
      if ($body->getTypeName() === 'n_EMPTY') {
        // Abstract method declaration.
        continue;
      }

      $static_vars = $body
        ->selectDescendantsOfType('n_STATIC_DECLARATION')
        ->selectDescendantsOfType('n_VARIABLE');
      foreach ($static_vars as $var) {
        $vars[] = $var;
      }


      $global_vars = $body
        ->selectDescendantsOfType('n_GLOBAL_DECLARATION_LIST');
      foreach ($global_vars as $var_list) {
        foreach ($var_list->getChildren() as $var) {
          if ($var->getTypeName() === 'n_VARIABLE') {
            $vars[] = $var;
          } else {
            // Dynamic global variable, i.e. "global $$x;".
            $scope_destroyed_at = min($scope_destroyed_at, $var->getOffset());
            // An error is raised elsewhere, no need to raise here.
          }
        }
      }

      // Include "catch (Exception $ex)", but not variables in the body of the
      // catch block.
      $catches = $body->selectDescendantsOfType('n_CATCH');
      foreach ($catches as $catch) {
        $vars[] = $catch->getChildOfType(1, 'n_VARIABLE');
      }

      $binary = $body->selectDescendantsOfType('n_BINARY_EXPRESSION');
      foreach ($binary as $expr) {
        if ($expr->getChildByIndex(1)->getConcreteString() !== '=') {
          continue;
        }
        $lval = $expr->getChildByIndex(0);
        if ($lval->getTypeName() === 'n_VARIABLE') {
          $vars[] = $lval;
        } else if ($lval->getTypeName() === 'n_LIST') {
          // Recursively grab everything out of list(), since the grammar
          // permits list() to be nested. Also note that list() is ONLY valid
          // as an lval assignments, so we could safely lift this out of the
          // n_BINARY_EXPRESSION branch.
          $assign_vars = $lval->selectDescendantsOfType('n_VARIABLE');
          foreach ($assign_vars as $var) {
            $vars[] = $var;
          }
        }

        if ($lval->getTypeName() === 'n_VARIABLE_VARIABLE') {
          $scope_destroyed_at = min($scope_destroyed_at, $lval->getOffset());
          // No need to raise here since we raise an error elsewhere.
        }
      }

      $calls = $body->selectDescendantsOfType('n_FUNCTION_CALL');
      foreach ($calls as $call) {
        $name = strtolower($call->getChildByIndex(0)->getConcreteString());

        if ($name === 'empty' || $name === 'isset') {
          $params = $call
            ->getChildOfType(1, 'n_CALL_PARAMETER_LIST')
            ->selectDescendantsOfType('n_VARIABLE');
          foreach ($params as $var) {
            $exclude_tokens[$var->getID()] = true;
          }
          continue;
        }
        if ($name !== 'extract') {
          continue;
        }
        $scope_destroyed_at = min($scope_destroyed_at, $call->getOffset());
      }

      $func_decls = $body->selectDescendantsOfType('n_FUNCTION_DECLARATION');
      foreach ($func_decls as $func_decl) {
        if ($func_decl->getChildByIndex(2)->getTypeName() != 'n_EMPTY') {
          continue;
        }

        foreach ($func_decl->selectDescendantsOfType('n_VARIABLE') as $var) {
          $exclude_tokens[$var->getID()] = true;
        }

        foreach (array('n_STRING_SCALAR', 'n_HEREDOC') as $type) {
          foreach ($func_decl->selectDescendantsOfType($type) as $string) {
            $exclude_strings[$string->getID()] = array();

            foreach ($string->getStringVariables() as $offset => $var) {
               $exclude_strings[$string->getID()][$var] = true;
            }
          }
        }
      }

      // Now we have every declaration except foreach(), handled below. Build
      // two maps, one which just keeps track of which tokens are part of
      // declarations ($declaration_tokens) and one which has the first offset
      // where a variable is declared ($declarations).

      foreach ($vars as $var) {
        $concrete = $this->getConcreteVariableString($var);
        $declarations[$concrete] = min(
          idx($declarations, $concrete, PHP_INT_MAX),
          $var->getOffset());
        $declaration_tokens[$var->getID()] = true;
      }

      // Excluded tokens are ones we don't "count" as being used, described
      // above. Put them into $exclude_tokens.

      $class_statics = $body
        ->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
      $class_static_vars = $class_statics
        ->selectDescendantsOfType('n_VARIABLE');
      foreach ($class_static_vars as $var) {
        $exclude_tokens[$var->getID()] = true;
      }


      // Find all the variables in scope, and figure out where they are used.
      // We want to find foreach() iterators which are both declared before and
      // used after the foreach() loop.

      $uses = array();

      $all_vars = $body->selectDescendantsOfType('n_VARIABLE');
      $all = array();

      // NOTE: $all_vars is not a real array so we can't unset() it.
      foreach ($all_vars as $var) {

        // Be strict since it's easier; we don't let you reuse an iterator you
        // declared before a loop after the loop, even if you're just assigning
        // to it.

        $concrete = $this->getConcreteVariableString($var);
        $uses[$concrete][$var->getID()] = $var->getOffset();

        if (isset($declaration_tokens[$var->getID()])) {
          // We know this is part of a declaration, so it's fine.
          continue;
        }
        if (isset($exclude_tokens[$var->getID()])) {
          // We know this is part of isset() or similar, so it's fine.
          continue;
        }

        $all[$var->getOffset()] = $concrete;
      }


      // Do foreach() last, we want to handle implicit redeclaration of a
      // variable already in scope since this probably means we're ovewriting a
      // local.

      // NOTE: Processing foreach expressions in order allows programs which
      // reuse iterator variables in other foreach() loops -- this is fine. We
      // have a separate warning to prevent nested loops from reusing the same
      // iterators.

      $foreaches = $body->selectDescendantsOfType('n_FOREACH');
      $all_foreach_vars = array();
      foreach ($foreaches as $foreach) {
        $foreach_expr = $foreach->getChildOfType(0, 'n_FOREACH_EXPRESSION');

        $foreach_vars = array();

        // Determine the end of the foreach() loop.
        $foreach_tokens = $foreach->getTokens();
        $last_token = end($foreach_tokens);
        $foreach_end = $last_token->getOffset();

        $key_var = $foreach_expr->getChildByIndex(1);
        if ($key_var->getTypeName() === 'n_VARIABLE') {
          $foreach_vars[] = $key_var;
        }

        $value_var = $foreach_expr->getChildByIndex(2);
        if ($value_var->getTypeName() === 'n_VARIABLE') {
          $foreach_vars[] = $value_var;
        } else {
          // The root-level token may be a reference, as in:
          //    foreach ($a as $b => &$c) { ... }
          // Reach into the n_VARIABLE_REFERENCE node to grab the n_VARIABLE
          // node.
          $var = $value_var->getChildByIndex(0);
          if ($var->getTypeName() === 'n_VARIABLE_VARIABLE') {
            $var = $var->getChildByIndex(0);
          }
          $foreach_vars[] = $var;
        }

        // Remove all uses of the iterators inside of the foreach() loop from
        // the $uses map.

        foreach ($foreach_vars as $var) {
          $concrete = $this->getConcreteVariableString($var);
          $offset = $var->getOffset();

          foreach ($uses[$concrete] as $id => $use_offset) {
            if (($use_offset >= $offset) && ($use_offset < $foreach_end)) {
              unset($uses[$concrete][$id]);
            }
          }

          $all_foreach_vars[] = $var;
        }
      }

      foreach ($all_foreach_vars as $var) {
        $concrete = $this->getConcreteVariableString($var);
        $offset = $var->getOffset();

        // This is a declaration, exclude it from the "declare variables prior
        // to use" check below.
        unset($all[$var->getOffset()]);

        $vars[] = $var;
      }

      // Now rebuild declarations to include foreach().

      foreach ($vars as $var) {
        $concrete = $this->getConcreteVariableString($var);
        $declarations[$concrete] = min(
          idx($declarations, $concrete, PHP_INT_MAX),
          $var->getOffset());
        $declaration_tokens[$var->getID()] = true;
      }

      foreach (array('n_STRING_SCALAR', 'n_HEREDOC') as $type) {
        foreach ($body->selectDescendantsOfType($type) as $string) {
          foreach ($string->getStringVariables() as $offset => $var) {
            if (isset($exclude_strings[$string->getID()][$var])) {
              continue;
            }

            $all[$string->getOffset() + $offset - 1] = '$'.$var;
          }
        }
      }

      // Issue a warning for every variable token, unless it appears in a
      // declaration, we know about a prior declaration, we have explicitly
      // excluded it, or scope has been made unknowable before it appears.

      $issued_warnings = array();
      foreach ($all as $offset => $concrete) {
        if ($offset >= $scope_destroyed_at) {
          // This appears after an extract() or $$var so we have no idea
          // whether it's legitimate or not. We raised a harshly-worded warning
          // when scope was made unknowable, so just ignore anything we can't
          // figure out.
          continue;
        }
        if ($offset >= idx($declarations, $concrete, PHP_INT_MAX)) {
          // The use appears after the variable is declared, so it's fine.
          continue;
        }
        if (!empty($issued_warnings[$concrete])) {
          // We've already issued a warning for this variable so we don't need
          // to issue another one.
          continue;
        }
        $this->raiseLintAtOffset(
          $offset,
          pht(
            'Declare variables prior to use (even if you are passing them '.
            'as reference parameters). You may have misspelled this '.
            'variable name.'),
          $concrete);
        $issued_warnings[$concrete] = true;
      }
    }
  }

}
