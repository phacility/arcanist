<?php

final class ArcanistImplicitFallthroughXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 30;

  private $switchhook;

  public function getLintName() {
    return pht('Implicit Fallthrough');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function getLinterConfigurationOptions() {
    return parent::getLinterConfigurationOptions() + array(
      'xhpast.switchhook' => array(
        'type' => 'optional string',
        'help' => pht(
          'Name of a concrete subclass of `%s` which tunes the '.
          'analysis of `%s` statements for this linter.',
          'ArcanistXHPASTLintSwitchHook',
          'switch'),
      ),
    );
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'xhpast.switchhook':
        $this->switchhook = $value;
        return;

      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
  }

  public function process(XHPASTNode $root) {
    $hook_obj = null;
    $hook_class = $this->switchhook;

    if ($hook_class) {
      $hook_obj = newv($hook_class, array());
      assert_instances_of(array($hook_obj), 'ArcanistXHPASTLintSwitchHook');
    }

    $switches = $root->selectDescendantsOfType('n_SWITCH');
    foreach ($switches as $switch) {
      $blocks = array();

      $cases = $switch->selectDescendantsOfType('n_CASE');
      foreach ($cases as $case) {
        $blocks[] = $case;
      }

      $defaults = $switch->selectDescendantsOfType('n_DEFAULT');
      foreach ($defaults as $default) {
        $blocks[] = $default;
      }


      foreach ($blocks as $key => $block) {
        // Collect all the tokens in this block which aren't at top level.
        // We want to ignore "break", and "continue" in these blocks.
        $lower_level = $block->selectDescendantsOfTypes(array(
          'n_WHILE',
          'n_DO_WHILE',
          'n_FOR',
          'n_FOREACH',
          'n_SWITCH',
        ));
        $lower_level_tokens = array();
        foreach ($lower_level as $lower_level_block) {
          $lower_level_tokens += $lower_level_block->getTokens();
        }

        // Collect all the tokens in this block which aren't in this scope
        // (because they're inside class, function or interface declarations).
        // We want to ignore all of these tokens.
        $decls = $block->selectDescendantsOfTypes(array(
          'n_FUNCTION_DECLARATION',
          'n_CLASS_DECLARATION',

          // For completeness; these can't actually have anything.
          'n_INTERFACE_DECLARATION',
        ));

        $different_scope_tokens = array();
        foreach ($decls as $decl) {
          $different_scope_tokens += $decl->getTokens();
        }

        $lower_level_tokens += $different_scope_tokens;

        // Get all the trailing nonsemantic tokens, since we need to look for
        // "fallthrough" comments past the end of the semantic block.

        $tokens = $block->getTokens();
        $last = end($tokens);
        while ($last && $last = $last->getNextToken()) {
          if ($last->isSemantic()) {
            break;
          }
          $tokens[$last->getTokenID()] = $last;
        }

        $blocks[$key] = array(
          $tokens,
          $lower_level_tokens,
          $different_scope_tokens,
        );
      }

      foreach ($blocks as $token_lists) {
        list(
          $tokens,
          $lower_level_tokens,
          $different_scope_tokens) = $token_lists;

        // Test each block (case or default statement) to see if it's OK. It's
        // OK if:
        //
        //  - it is empty; or
        //  - it ends in break, return, throw, continue or exit at top level; or
        //  - it has a comment with "fallthrough" in its text.

        // Empty blocks are OK, so we start this at `true` and only set it to
        // false if we find a statement.
        $block_ok = true;

        // Keeps track of whether the current statement is one that validates
        // the block (break, return, throw, continue) or something else.
        $statement_ok = false;

        foreach ($tokens as $token_id => $token) {
          if (!$token->isSemantic()) {
            // Liberally match "fall" in the comment text so that comments like
            // "fallthru", "fall through", "fallthrough", etc., are accepted.
            if (preg_match('/fall/i', $token->getValue())) {
              $block_ok = true;
              break;
            }
            continue;
          }

          $tok_type = $token->getTypeName();

          if ($tok_type === 'T_FUNCTION' ||
              $tok_type === 'T_CLASS' ||
              $tok_type === 'T_INTERFACE') {
            // These aren't statements, but mark the block as nonempty anyway.
            $block_ok = false;
            continue;
          }

          if ($tok_type === ';') {
            if ($statement_ok) {
              $statment_ok = false;
            } else {
              $block_ok = false;
            }
            continue;
          }

          if ($tok_type === 'T_BREAK' || $tok_type === 'T_CONTINUE') {
            if (empty($lower_level_tokens[$token_id])) {
              $statement_ok = true;
              $block_ok = true;
            }
            continue;
          }

          if ($tok_type === 'T_RETURN'   ||
              $tok_type === 'T_THROW'    ||
              $tok_type === 'T_EXIT'     ||
              ($hook_obj && $hook_obj->checkSwitchToken($token))) {
            if (empty($different_scope_tokens[$token_id])) {
              $statement_ok = true;
              $block_ok = true;
            }
            continue;
          }
        }

        if (!$block_ok) {
          $this->raiseLintAtToken(
            head($tokens),
            pht(
              'This `%s` or `%s` has a nonempty block which does not end '.
              'with `%s`, `%s`, `%s`, `%s` or `%s`. Did you forget to add '.
              'one of those? If you intend to fall through, add a `%s` '.
              'comment to silence this warning.',
              'case',
              'default',
              'break',
              'continue',
              'return',
              'throw',
              'exit',
              '// fallthrough'));
        }
      }
    }
  }

}
