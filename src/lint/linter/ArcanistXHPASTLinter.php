<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Uses XHPAST to apply lint rules to PHP.
 *
 * @group linter
 */
final class ArcanistXHPASTLinter extends ArcanistLinter {

  protected $trees = array();

  const LINT_PHP_SYNTAX_ERROR          = 1;
  const LINT_UNABLE_TO_PARSE           = 2;
  const LINT_VARIABLE_VARIABLE         = 3;
  const LINT_EXTRACT_USE               = 4;
  const LINT_UNDECLARED_VARIABLE       = 5;
  const LINT_PHP_SHORT_TAG             = 6;
  const LINT_PHP_ECHO_TAG              = 7;
  const LINT_PHP_CLOSE_TAG             = 8;
  const LINT_NAMING_CONVENTIONS        = 9;
  const LINT_IMPLICIT_CONSTRUCTOR      = 10;
  const LINT_DYNAMIC_DEFINE            = 12;
  const LINT_STATIC_THIS               = 13;
  const LINT_PREG_QUOTE_MISUSE         = 14;
  const LINT_PHP_OPEN_TAG              = 15;
  const LINT_TODO_COMMENT              = 16;
  const LINT_EXIT_EXPRESSION           = 17;
  const LINT_COMMENT_STYLE             = 18;
  const LINT_CLASS_FILENAME_MISMATCH   = 19;
  const LINT_TAUTOLOGICAL_EXPRESSION   = 20;
  const LINT_PLUS_OPERATOR_ON_STRINGS  = 21;
  const LINT_DUPLICATE_KEYS_IN_ARRAY   = 22;
  const LINT_REUSED_ITERATORS          = 23;
  const LINT_BRACE_FORMATTING          = 24;
  const LINT_PARENTHESES_SPACING       = 25;
  const LINT_CONTROL_STATEMENT_SPACING = 26;
  const LINT_BINARY_EXPRESSION_SPACING = 27;
  const LINT_ARRAY_INDEX_SPACING       = 28;
  const LINT_RAGGED_CLASSTREE_EDGE     = 29;
  const LINT_IMPLICIT_FALLTHROUGH      = 30;
  const LINT_PHP_53_FEATURES           = 31;
  const LINT_REUSED_AS_ITERATOR        = 32;
  const LINT_PHT_WITH_DYNAMIC_STRING   = 33;
  const LINT_COMMENT_SPACING           = 34;
  const LINT_PHP_54_FEATURES           = 35;
  const LINT_SLOWNESS                  = 36;


  public function getLintNameMap() {
    return array(
      self::LINT_PHP_SYNTAX_ERROR          => 'PHP Syntax Error!',
      self::LINT_UNABLE_TO_PARSE           => 'Unable to Parse',
      self::LINT_VARIABLE_VARIABLE         => 'Use of Variable Variable',
      self::LINT_EXTRACT_USE               => 'Use of extract()',
      self::LINT_UNDECLARED_VARIABLE       => 'Use of Undeclared Variable',
      self::LINT_PHP_SHORT_TAG             => 'Use of Short Tag "<?"',
      self::LINT_PHP_ECHO_TAG              => 'Use of Echo Tag "<?="',
      self::LINT_PHP_CLOSE_TAG             => 'Use of Close Tag "?>"',
      self::LINT_NAMING_CONVENTIONS        => 'Naming Conventions',
      self::LINT_IMPLICIT_CONSTRUCTOR      => 'Implicit Constructor',
      self::LINT_DYNAMIC_DEFINE            => 'Dynamic define()',
      self::LINT_STATIC_THIS               => 'Use of $this in Static Context',
      self::LINT_PREG_QUOTE_MISUSE         => 'Misuse of preg_quote()',
      self::LINT_PHP_OPEN_TAG              => 'Expected Open Tag',
      self::LINT_TODO_COMMENT              => 'TODO Comment',
      self::LINT_EXIT_EXPRESSION           => 'Exit Used as Expression',
      self::LINT_COMMENT_STYLE             => 'Comment Style',
      self::LINT_CLASS_FILENAME_MISMATCH   => 'Class-Filename Mismatch',
      self::LINT_TAUTOLOGICAL_EXPRESSION   => 'Tautological Expression',
      self::LINT_PLUS_OPERATOR_ON_STRINGS  => 'Not String Concatenation',
      self::LINT_DUPLICATE_KEYS_IN_ARRAY   => 'Duplicate Keys in Array',
      self::LINT_REUSED_ITERATORS          => 'Reuse of Iterator Variable',
      self::LINT_BRACE_FORMATTING          => 'Brace placement',
      self::LINT_PARENTHESES_SPACING       => 'Spaces Inside Parentheses',
      self::LINT_CONTROL_STATEMENT_SPACING => 'Space After Control Statement',
      self::LINT_BINARY_EXPRESSION_SPACING => 'Space Around Binary Operator',
      self::LINT_ARRAY_INDEX_SPACING       => 'Spacing Before Array Index',
      self::LINT_RAGGED_CLASSTREE_EDGE     => 'Class Not abstract Or final',
      self::LINT_IMPLICIT_FALLTHROUGH      => 'Implicit Fallthrough',
      self::LINT_PHP_53_FEATURES           => 'Use Of PHP 5.3 Features',
      self::LINT_PHP_54_FEATURES           => 'Use Of PHP 5.4 Features',
      self::LINT_REUSED_AS_ITERATOR        => 'Variable Reused As Iterator',
      self::LINT_PHT_WITH_DYNAMIC_STRING   => 'Use of pht() on Dynamic String',
      self::LINT_COMMENT_SPACING           => 'Comment Spaces',
      self::LINT_SLOWNESS                  => 'Slow Construct',
    );
  }

  public function getLinterName() {
    return 'XHP';
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_TODO_COMMENT => ArcanistLintSeverity::SEVERITY_DISABLED,
      self::LINT_UNABLE_TO_PARSE
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_NAMING_CONVENTIONS
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_PREG_QUOTE_MISUSE
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_BRACE_FORMATTING
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_PARENTHESES_SPACING
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_CONTROL_STATEMENT_SPACING
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_BINARY_EXPRESSION_SPACING
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_ARRAY_INDEX_SPACING
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_IMPLICIT_FALLTHROUGH
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_PHT_WITH_DYNAMIC_STRING
        => ArcanistLintSeverity::SEVERITY_WARNING,
      self::LINT_SLOWNESS
        => ArcanistLintSeverity::SEVERITY_WARNING,

      self::LINT_COMMENT_SPACING
        => ArcanistLintSeverity::SEVERITY_ADVICE,

      // This is disabled by default because it implies a very strict policy
      // which isn't necessary in the general case.
      self::LINT_RAGGED_CLASSTREE_EDGE
        => ArcanistLintSeverity::SEVERITY_DISABLED,

      // This is disabled by default because projects don't necessarily target
      // a specific minimum version.
      self::LINT_PHP_53_FEATURES
        => ArcanistLintSeverity::SEVERITY_DISABLED,
      self::LINT_PHP_54_FEATURES
        => ArcanistLintSeverity::SEVERITY_DISABLED,
    );
  }

  public function willLintPaths(array $paths) {
    $futures = array();
    foreach ($paths as $path) {
      $futures[$path] = xhpast_get_parser_future($this->getData($path));
    }
    foreach (Futures($futures)->limit(8) as $path => $future) {
      $this->willLintPath($path);
      try {
        $this->trees[$path] = XHPASTTree::newFromDataAndResolvedExecFuture(
          $this->getData($path),
          $future->resolve());
      } catch (XHPASTSyntaxErrorException $ex) {
        $this->raiseLintAtLine(
          $ex->getErrorLine(),
          1,
          self::LINT_PHP_SYNTAX_ERROR,
          'This file contains a syntax error: '.$ex->getMessage());
        $this->stopAllLinters();
        return;
      } catch (Exception $ex) {
        $this->raiseLintAtPath(
          self::LINT_UNABLE_TO_PARSE,
          'XHPAST could not parse this file, probably because the AST is too '.
          'deep. Some lint issues may not have been detected. You may safely '.
          'ignore this warning.');
        return;
      }
    }
  }

  public function getXHPASTTreeForPath($path) {
    return idx($this->trees, $path);
  }

  public function lintPath($path) {
    if (empty($this->trees[$path])) {
      return;
    }

    $root = $this->trees[$path]->getRootNode();

    $root->buildSelectCache();
    $root->buildTokenCache();

    $this->lintUseOfThisInStaticMethods($root);
    $this->lintDynamicDefines($root);
    $this->lintSurpriseConstructors($root);
    $this->lintPHPTagUse($root);
    $this->lintVariableVariables($root);
    $this->lintTODOComments($root);
    $this->lintExitExpressions($root);
    $this->lintSpaceAroundBinaryOperators($root);
    $this->lintSpaceAfterControlStatementKeywords($root);
    $this->lintParenthesesShouldHugExpressions($root);
    $this->lintNamingConventions($root);
    $this->lintPregQuote($root);
    $this->lintUndeclaredVariables($root);
    $this->lintArrayIndexWhitespace($root);
    $this->lintCommentSpaces($root);
    $this->lintHashComments($root);
    $this->lintPrimaryDeclarationFilenameMatch($root);
    $this->lintTautologicalExpressions($root);
    $this->lintPlusOperatorOnStrings($root);
    $this->lintDuplicateKeysInArray($root);
    $this->lintReusedIterators($root);
    $this->lintBraceFormatting($root);
    $this->lintRaggedClasstreeEdges($root);
    $this->lintImplicitFallthrough($root);
    $this->lintPHP53Features($root);
    $this->lintPHP54Features($root);
    $this->lintPHT($root);
    $this->lintStrposUsedForStart($root);
    $this->lintStrstrUsedForCheck($root);
  }

  public function lintStrstrUsedForCheck($root) {
    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');
    foreach ($expressions as $expression) {
      $operator = $expression->getChildOfType(1, 'n_OPERATOR');
      $operator = $operator->getConcreteString();

      if ($operator != '===' && $operator != '!==') {
        continue;
      }

      $false = $expression->getChildByIndex(0);
      if ($false->getTypeName() == 'n_SYMBOL_NAME' &&
          $false->getConcreteString() == 'false') {
        $strstr = $expression->getChildByIndex(2);
      } else {
        $strstr = $false;
        $false = $expression->getChildByIndex(2);
        if ($false->getTypeName() != 'n_SYMBOL_NAME' ||
            $false->getConcreteString() != 'false') {
          continue;
        }
      }

      if ($strstr->getTypeName() != 'n_FUNCTION_CALL') {
        continue;
      }

      $name = strtolower($strstr->getChildByIndex(0)->getConcreteString());
      if ($name == 'strstr' || $name == 'strchr') {
        $this->raiseLintAtNode(
          $strstr,
          self::LINT_SLOWNESS,
          "Use strpos() for checking if the string contains something.");
      } else if ($name == 'stristr') {
        $this->raiseLintAtNode(
          $strstr,
          self::LINT_SLOWNESS,
          "Use stripos() for checking if the string contains something.");
      }
    }
  }

  public function lintStrposUsedForStart($root) {
    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');
    foreach ($expressions as $expression) {
      $operator = $expression->getChildOfType(1, 'n_OPERATOR');
      $operator = $operator->getConcreteString();

      if ($operator != '===' && $operator != '!==') {
        continue;
      }

      $zero = $expression->getChildByIndex(0);
      if ($zero->getTypeName() == 'n_NUMERIC_SCALAR' &&
          $zero->getConcreteString() == '0') {
        $strpos = $expression->getChildByIndex(2);
      } else {
        $strpos = $zero;
        $zero = $expression->getChildByIndex(2);
        if ($zero->getTypeName() != 'n_NUMERIC_SCALAR' ||
            $zero->getConcreteString() != '0') {
          continue;
        }
      }

      if ($strpos->getTypeName() != 'n_FUNCTION_CALL') {
        continue;
      }

      $name = strtolower($strpos->getChildByIndex(0)->getConcreteString());
      if ($name == 'strpos') {
        $this->raiseLintAtNode(
          $strpos,
          self::LINT_SLOWNESS,
          "Use strncmp() for checking if the string starts with something.");
      } else if ($name == 'stripos') {
        $this->raiseLintAtNode(
          $strpos,
          self::LINT_SLOWNESS,
          "Use strncasecmp() for checking if the string starts with ".
            "something.");
      }
    }
  }

  public function lintPHT($root) {
    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($calls as $call) {
      $name = strtolower($call->getChildByIndex(0)->getConcreteString());
      if ($name != 'pht') {
        continue;
      }

      $parameters = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
      if (!$parameters->getChildren()) {
        continue;
      }

      $identifier = $parameters->getChildByIndex(0);
      if ($identifier->getTypeName() == 'n_STRING_SCALAR') {
        continue;
      }

      if ($identifier->getTypeName() == 'n_CONCATENATION_LIST') {
        foreach ($identifier->getChildren() as $child) {
          if ($child->getTypeName() == 'n_STRING_SCALAR' ||
              $child->getTypeName() == 'n_OPERATOR') {
            continue 2;
          }
        }
      }

      $this->raiseLintAtNode(
        $call,
        self::LINT_PHT_WITH_DYNAMIC_STRING,
        "The first parameter of pht() can be only a scalar string, ".
          "otherwise it can't be extracted.");
    }
  }

  public function lintPHP53Features($root) {

    $functions = $root->selectTokensOfType('T_FUNCTION');
    foreach ($functions as $function) {

      $next = $function->getNextToken();
      while ($next) {
        if ($next->isSemantic()) {
          break;
        }
        $next = $next->getNextToken();
      }

      if ($next) {
        if ($next->getTypeName() == '(') {
          $this->raiseLintAtToken(
            $function,
            self::LINT_PHP_53_FEATURES,
            'This codebase targets PHP 5.2, but anonymous functions were '.
            'not introduced until PHP 5.3.');
        }
      }
    }

    $namespaces = $root->selectTokensOfType('T_NAMESPACE');
    foreach ($namespaces as $namespace) {
      $this->raiseLintAtToken(
        $namespace,
        self::LINT_PHP_53_FEATURES,
        'This codebase targets PHP 5.2, but namespaces were not introduced '.
        'until PHP 5.3.');
    }

    // NOTE: This is only "use x;", in anonymous functions the node type is
    // n_LEXICAL_VARIABLE_LIST even though both tokens are T_USE.

    // TODO: We parse n_USE in a slightly crazy way right now; that would be
    // a better selector once it's fixed.

    $uses = $root->selectDescendantsOfType('n_USE_LIST');
    foreach ($uses as $use) {
      $this->raiseLintAtNode(
        $use,
        self::LINT_PHP_53_FEATURES,
        'This codebase targets PHP 5.2, but namespaces were not introduced '.
        'until PHP 5.3.');
    }

    $statics = $root->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
    foreach ($statics as $static) {
      $name = $static->getChildOfType(0, 'n_CLASS_NAME');
      if ($name->getConcreteString() == 'static') {
        $this->raiseLintAtNode(
          $name,
          self::LINT_PHP_53_FEATURES,
          'This codebase targets PHP 5.2, but `static::` was not introduced '.
          'until PHP 5.3.');
      }
    }

    $this->lintPHP53Functions($root);
  }

  private function lintPHP53Functions($root) {
    $target = dirname(__FILE__).'/../../../resources/php_compat_info.json';
    $compat_info = json_decode(file_get_contents($target), true);

    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($calls as $call) {
      $node = $call->getChildByIndex(0);
      $name = strtolower($node->getConcreteString());
      $version = idx($compat_info['functions'], $name);
      if ($version) {
        $this->raiseLintAtNode(
          $node,
          self::LINT_PHP_53_FEATURES,
          "This codebase targets PHP 5.2.3, but `{$name}()` was not ".
          "introduced until PHP {$version}.");
      } else if (array_key_exists($name, $compat_info['params'])) {
        $params = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
        foreach (array_values($params->getChildren()) as $i => $param) {
          $version = idx($compat_info['params'][$name], $i);
          if ($version) {
            $this->raiseLintAtNode(
              $param,
              self::LINT_PHP_53_FEATURES,
              "This codebase targets PHP 5.2.3, but parameter ".($i + 1)." ".
              "of `{$name}()` was not introduced until PHP {$version}.");
          }
        }
      }
    }

    $classes = $root->selectDescendantsOfType('n_CLASS_NAME');
    foreach ($classes as $node) {
      $name = strtolower($node->getConcreteString());
      $version = idx($compat_info['interfaces'], $name);
      $version = idx($compat_info['classes'], $name, $version);
      if ($version) {
        $this->raiseLintAtNode(
          $node,
          self::LINT_PHP_53_FEATURES,
          "This codebase targets PHP 5.2.3, but `{$name}` was not ".
          "introduced until PHP {$version}.");
      }
    }

  }

  public function lintPHP54Features($root) {
    $indexes = $root->selectDescendantsOfType('n_INDEX_ACCESS');
    foreach ($indexes as $index) {
      $left = $index->getChildByIndex(0);
      switch ($left->getTypeName()) {
        case 'n_FUNCTION_CALL':
          $this->raiseLintAtNode(
            $index->getChildByIndex(1),
            self::LINT_PHP_54_FEATURES,
            "The f()[...] syntax was not introduced until PHP 5.4, but this ".
            "codebase targets an earlier version of PHP. You can rewrite ".
            "this expression using idx().");
          break;
      }
    }
  }

  private function lintImplicitFallthrough($root) {
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
        $tokens = $block->getTokens();

        // Get all the trailing nonsemantic tokens, since we need to look for
        // "fallthrough" comments past the end of the semantic block.

        $last = end($tokens);
        while ($last && $last = $last->getNextToken()) {
          if (!$last->isSemantic()) {
            $tokens[] = $last;
          }
        }

        $blocks[$key] = $tokens;
      }

      foreach ($blocks as $tokens) {

        // Test each block (case or default statement) to see if it's OK. It's
        // OK if:
        //
        //  - it is empty; or
        //  - it ends in break, return, throw, continue or exit; or
        //  - it has a comment with "fallthrough" in its text.

        // Empty blocks are OK, so we start this at `true` and only set it to
        // false if we find a statement.
        $block_ok = true;

        // Keeps track of whether the current statement is one that validates
        // the block (break, return, throw, continue) or something else.
        $statement_ok = false;

        foreach ($tokens as $token) {
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

          if ($tok_type == ';') {
            if ($statement_ok) {
              $statment_ok = false;
            } else {
              $block_ok = false;
            }
            continue;
          }

          if ($tok_type == 'T_RETURN'   ||
              $tok_type == 'T_BREAK'    ||
              $tok_type == 'T_CONTINUE' ||
              $tok_type == 'T_THROW'    ||
              $tok_type == 'T_EXIT') {
            $statement_ok = true;
            $block_ok = true;
          }
        }

        if (!$block_ok) {
          $this->raiseLintAtToken(
            head($tokens),
            self::LINT_IMPLICIT_FALLTHROUGH,
            "This 'case' or 'default' has a nonempty block which does not ".
            "end with 'break', 'continue', 'return', 'throw' or 'exit'. Did ".
            "you forget to add one of those? If you intend to fall through, ".
            "add a '// fallthrough' comment to silence this warning.");
        }
      }
    }
  }

  private function lintBraceFormatting($root) {

    foreach ($root->selectDescendantsOfType('n_STATEMENT_LIST') as $list) {
      $tokens = $list->getTokens();
      if (!$tokens || head($tokens)->getValue() != '{') {
        continue;
      }
      list($before, $after) = $list->getSurroundingNonsemanticTokens();
      if (!$before) {
        $first = head($tokens);

        // Only insert the space if we're after a closing parenthesis. If
        // we're in a construct like "else{}", other rules will insert space
        // after the 'else' correctly.
        $prev = $first->getPrevToken();
        if (!$prev || $prev->getValue() != ')') {
          continue;
        }

        $this->raiseLintAtToken(
          $first,
          self::LINT_BRACE_FORMATTING,
          'Put opening braces on the same line as control statements and '.
          'declarations, with a single space before them.',
          ' '.$first->getValue());
      } else if (count($before) == 1) {
        $before = reset($before);
        if ($before->getValue() != ' ') {
          $this->raiseLintAtToken(
            $before,
            self::LINT_BRACE_FORMATTING,
            'Put opening braces on the same line as control statements and '.
            'declarations, with a single space before them.',
            ' ');
        }
      }
    }

  }

  private function lintTautologicalExpressions($root) {
    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');

    static $operators = array(
      '-'   => true,
      '/'   => true,
      '-='  => true,
      '/='  => true,
      '<='  => true,
      '<'   => true,
      '=='  => true,
      '===' => true,
      '!='  => true,
      '!==' => true,
      '>='  => true,
      '>'   => true,
    );

    static $logical = array(
      '||'  => true,
      '&&'  => true,
    );

    foreach ($expressions as $expr) {
      $operator = $expr->getChildByIndex(1)->getConcreteString();
      if (!empty($operators[$operator])) {
        $left = $expr->getChildByIndex(0)->getSemanticString();
        $right = $expr->getChildByIndex(2)->getSemanticString();

        if ($left == $right) {
          $this->raiseLintAtNode(
            $expr,
            self::LINT_TAUTOLOGICAL_EXPRESSION,
            'Both sides of this expression are identical, so it always '.
            'evaluates to a constant.');
        }
      }

      if (!empty($logical[$operator])) {
        $left = $expr->getChildByIndex(0)->getSemanticString();
        $right = $expr->getChildByIndex(2)->getSemanticString();

        // NOTE: These will be null to indicate "could not evaluate".
        $left = $this->evaluateStaticBoolean($left);
        $right = $this->evaluateStaticBoolean($right);

        if (($operator == '||' && ($left === true || $right === true)) ||
            ($operator == '&&' && ($left === false || $right === false))) {
          $this->raiseLintAtNode(
            $expr,
            self::LINT_TAUTOLOGICAL_EXPRESSION,
            'The logical value of this expression is static. Did you forget '.
            'to remove some debugging code?');
        }
      }
    }
  }


  /**
   * Statically evaluate a boolean value from an XHP tree.
   *
   * TODO: Improve this and move it to XHPAST proper?
   *
   * @param  string The "semantic string" of a single value.
   * @return mixed  ##true## or ##false## if the value could be evaluated
   *                statically; ##null## if static evaluation was not possible.
   */
  private function evaluateStaticBoolean($string) {
    switch (strtolower($string)) {
      case '0':
      case 'null':
      case 'false':
        return false;
      case '1':
      case 'true':
        return true;
    }
    return null;
  }


  protected function lintCommentSpaces($root) {
    foreach ($root->selectTokensOfType('T_COMMENT') as $comment) {
      $value = $comment->getValue();
      if ($value[0] != '#') {
        $match = null;
        if (preg_match('@^(/[/*]+)[^/*\s]@', $value, $match)) {
          $this->raiseLintAtOffset(
            $comment->getOffset(),
            self::LINT_COMMENT_SPACING,
            'Put space after comment start.',
            $match[1],
            $match[1].' ');
        }
      }
    }
  }


  protected function lintHashComments($root) {
    foreach ($root->selectTokensOfType('T_COMMENT') as $comment) {
      $value = $comment->getValue();
      if ($value[0] != '#') {
        continue;
      }

      $this->raiseLintAtOffset(
        $comment->getOffset(),
        self::LINT_COMMENT_STYLE,
        'Use "//" single-line comments, not "#".',
        '#',
        (preg_match('/^#\S/', $value) ? '// ' : '//'));
    }
  }

  /**
   * Find cases where loops get nested inside each other but use the same
   * iterator variable. For example:
   *
   *  COUNTEREXAMPLE
   *  foreach ($list as $thing) {
   *    foreach ($stuff as $thing) { // <-- Raises an error for reuse of $thing
   *      // ...
   *    }
   *  }
   *
   */
  private function lintReusedIterators($root) {
    $used_vars = array();

    $for_loops = $root->selectDescendantsOfType('n_FOR');
    foreach ($for_loops as $for_loop) {
      $var_map = array();

      // Find all the variables that are assigned to in the for() expression.
      $for_expr = $for_loop->getChildOfType(0, 'n_FOR_EXPRESSION');
      $bin_exprs = $for_expr->selectDescendantsOfType('n_BINARY_EXPRESSION');
      foreach ($bin_exprs as $bin_expr) {
        if ($bin_expr->getChildByIndex(1)->getConcreteString() == '=') {
          $var_map[$bin_expr->getChildByIndex(0)->getConcreteString()] = true;
        }
      }

      $used_vars[$for_loop->getID()] = $var_map;
    }

    $foreach_loops = $root->selectDescendantsOfType('n_FOREACH');
    foreach ($foreach_loops as $foreach_loop) {
      $var_map = array();

      $foreach_expr = $foreach_loop->getChildOftype(0, 'n_FOREACH_EXPRESSION');

      // We might use one or two vars, i.e. "foreach ($x as $y => $z)" or
      // "foreach ($x as $y)".
      $possible_used_vars = array(
        $foreach_expr->getChildByIndex(1),
        $foreach_expr->getChildByIndex(2),
      );
      foreach ($possible_used_vars as $var) {
        if ($var->getTypeName() == 'n_EMPTY') {
          continue;
        }
        $name = $var->getConcreteString();
        $name = trim($name, '&'); // Get rid of ref silliness.
        $var_map[$name] = true;
      }

      $used_vars[$foreach_loop->getID()] = $var_map;
    }

    $all_loops = $for_loops->add($foreach_loops);
    foreach ($all_loops as $loop) {
      $child_for_loops = $loop->selectDescendantsOfType('n_FOR');
      $child_foreach_loops = $loop->selectDescendantsOfType('n_FOREACH');
      $child_loops = $child_for_loops->add($child_foreach_loops);

      $outer_vars = $used_vars[$loop->getID()];
      foreach ($child_loops as $inner_loop) {
        $inner_vars = $used_vars[$inner_loop->getID()];
        $shared = array_intersect_key($outer_vars, $inner_vars);
        if ($shared) {
          $shared_desc = implode(', ', array_keys($shared));
          $this->raiseLintAtNode(
            $inner_loop->getChildByIndex(0),
            self::LINT_REUSED_ITERATORS,
            "This loop reuses iterator variables ({$shared_desc}) from an ".
            "outer loop. You might be clobbering the outer iterator. Change ".
            "the inner loop to use a different iterator name.");
        }
      }
    }
  }

  protected function lintVariableVariables($root) {
    $vvars = $root->selectDescendantsOfType('n_VARIABLE_VARIABLE');
    foreach ($vvars as $vvar) {
      $this->raiseLintAtNode(
        $vvar,
        self::LINT_VARIABLE_VARIABLE,
        'Rewrite this code to use an array. Variable variables are unclear '.
        'and hinder static analysis.');
    }
  }

  protected function lintUndeclaredVariables($root) {
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

    $fdefs = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
    $mdefs = $root->selectDescendantsOfType('n_METHOD_DECLARATION');
    $defs = $fdefs->add($mdefs);

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
      if ($body->getTypeName() == 'n_EMPTY') {
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
          if ($var->getTypeName() == 'n_VARIABLE') {
            $vars[] = $var;
          } else {
            // Dynamic global variable, i.e. "global $$x;".
            $scope_destroyed_at = min($scope_destroyed_at, $var->getOffset());
            // An error is raised elsewhere, no need to raise here.
          }
        }
      }

      $catches = $body
        ->selectDescendantsOfType('n_CATCH')
        ->selectDescendantsOfType('n_VARIABLE');
      foreach ($catches as $var) {
        $vars[] = $var;
      }

      $binary = $body->selectDescendantsOfType('n_BINARY_EXPRESSION');
      foreach ($binary as $expr) {
        if ($expr->getChildByIndex(1)->getConcreteString() != '=') {
          continue;
        }
        $lval = $expr->getChildByIndex(0);
        if ($lval->getTypeName() == 'n_VARIABLE') {
          $vars[] = $lval;
        } else if ($lval->getTypeName() == 'n_LIST') {
          // Recursivey grab everything out of list(), since the grammar
          // permits list() to be nested. Also note that list() is ONLY valid
          // as an lval assignments, so we could safely lift this out of the
          // n_BINARY_EXPRESSION branch.
          $assign_vars = $lval->selectDescendantsOfType('n_VARIABLE');
          foreach ($assign_vars as $var) {
            $vars[] = $var;
          }
        }

        if ($lval->getTypeName() == 'n_VARIABLE_VARIABLE') {
          $scope_destroyed_at = min($scope_destroyed_at, $lval->getOffset());
          // No need to raise here since we raise an error elsewhere.
        }
      }

      $calls = $body->selectDescendantsOfType('n_FUNCTION_CALL');
      foreach ($calls as $call) {
        $name = strtolower($call->getChildByIndex(0)->getConcreteString());

        if ($name == 'empty' || $name == 'isset') {
          $params = $call
            ->getChildOfType(1, 'n_CALL_PARAMETER_LIST')
            ->selectDescendantsOfType('n_VARIABLE');
          foreach ($params as $var) {
            $exclude_tokens[$var->getID()] = true;
          }
          continue;
        }
        if ($name != 'extract') {
          continue;
        }
        $scope_destroyed_at = min($scope_destroyed_at, $call->getOffset());
        $this->raiseLintAtNode(
          $call,
          self::LINT_EXTRACT_USE,
          'Avoid extract(). It is confusing and hinders static analysis.');
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

      // Excluded tokens are ones we don't "count" as being uses, described
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

        $concrete = $var->getConcreteString();
        $uses[$concrete][$var->getID()] = $var->getOffset();

        if (isset($declaration_tokens[$var->getID()])) {
          // We know this is part of a declaration, so it's fine.
          continue;
        }
        if (isset($exclude_tokens[$var->getID()])) {
          // We know this is part of isset() or similar, so it's fine.
          continue;
        }

        $all[$var->getID()] = $var;
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
        if ($key_var->getTypeName() == 'n_VARIABLE') {
          $foreach_vars[] = $key_var;
        }

        $value_var = $foreach_expr->getChildByIndex(2);
        if ($value_var->getTypeName() == 'n_VARIABLE') {
          $foreach_vars[] = $value_var;
        } else {
          // The root-level token may be a reference, as in:
          //    foreach ($a as $b => &$c) { ... }
          // Reach into the n_VARIABLE_REFERENCE node to grab the n_VARIABLE
          // node.
          $foreach_vars[] = $value_var->getChildOfType(0, 'n_VARIABLE');
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

        // If a variable was declared before a foreach() and is used after
        // it, raise a message.

        if (isset($declarations[$concrete])) {
          if ($declarations[$concrete] < $offset) {
            if (!empty($uses[$concrete]) &&
                max($uses[$concrete]) > $offset) {
              $this->raiseLintAtNode(
                $var,
                self::LINT_REUSED_AS_ITERATOR,
                'This iterator variable is a previously declared local '.
                'variable. To avoid overwriting locals, do not reuse them '.
                'as iterator variables.');
            }
          }
        }

        // This is a declration, exclude it from the "declare variables prior
        // to use" check below.
        unset($all[$var->getID()]);

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

      // Issue a warning for every variable token, unless it appears in a
      // declaration, we know about a prior declaration, we have explicitly
      // exlcuded it, or scope has been made unknowable before it appears.

      $issued_warnings = array();
      foreach ($all as $id => $var) {
        if ($var->getOffset() >= $scope_destroyed_at) {
          // This appears after an extract() or $$var so we have no idea
          // whether it's legitimate or not. We raised a harshly-worded warning
          // when scope was made unknowable, so just ignore anything we can't
          // figure out.
          continue;
        }
        $concrete = $this->getConcreteVariableString($var);
        if ($var->getOffset() >= idx($declarations, $concrete, PHP_INT_MAX)) {
          // The use appears after the variable is declared, so it's fine.
          continue;
        }
        if (!empty($issued_warnings[$concrete])) {
          // We've already issued a warning for this variable so we don't need
          // to issue another one.
          continue;
        }
        $this->raiseLintAtNode(
          $var,
          self::LINT_UNDECLARED_VARIABLE,
          'Declare variables prior to use (even if you are passing them '.
          'as reference parameters). You may have misspelled this '.
          'variable name.');
        $issued_warnings[$concrete] = true;
      }
    }
  }

  private function getConcreteVariableString($var) {
    $concrete = $var->getConcreteString();
    // Strip off curly braces as in $obj->{$property}.
    $concrete = trim($concrete, '{}');
    return $concrete;
  }

  protected function lintPHPTagUse($root) {
    $tokens = $root->getTokens();
    foreach ($tokens as $token) {
      if ($token->getTypeName() == 'T_OPEN_TAG') {
        if (trim($token->getValue()) == '<?') {
          $this->raiseLintAtToken(
            $token,
            self::LINT_PHP_SHORT_TAG,
            'Use the full form of the PHP open tag, "<?php".',
            "<?php\n");
        }
        break;
      } else if ($token->getTypeName() == 'T_OPEN_TAG_WITH_ECHO') {
        $this->raiseLintAtToken(
          $token,
          self::LINT_PHP_ECHO_TAG,
          'Avoid the PHP echo short form, "<?=".');
        break;
      } else {
        if (!preg_match('/^#!/', $token->getValue())) {
          $this->raiseLintAtToken(
            $token,
            self::LINT_PHP_OPEN_TAG,
            'PHP files should start with "<?php", which may be preceded by '.
            'a "#!" line for scripts.');
        }
        break;
      }
    }

    foreach ($root->selectTokensOfType('T_CLOSE_TAG') as $token) {
      $this->raiseLintAtToken(
        $token,
        self::LINT_PHP_CLOSE_TAG,
        'Do not use the PHP closing tag, "?>".');
    }
  }

  protected function lintNamingConventions($root) {

    // We're going to build up a list of <type, name, token, error> tuples
    // and then try to instantiate a hook class which has the opportunity to
    // override us.
    $names = array();

    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    foreach ($classes as $class) {
      $name_token = $class->getChildByIndex(1);
      $name_string = $name_token->getConcreteString();

      $names[] = array(
        'class',
        $name_string,
        $name_token,
        ArcanistXHPASTLintNamingHook::isUpperCamelCase($name_string)
          ? null
          : 'Follow naming conventions: classes should be named using '.
            'UpperCamelCase.',
      );
    }

    $ifaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');
    foreach ($ifaces as $iface) {
      $name_token = $iface->getChildByIndex(1);
      $name_string = $name_token->getConcreteString();
      $names[] = array(
        'interface',
        $name_string,
        $name_token,
        ArcanistXHPASTLintNamingHook::isUpperCamelCase($name_string)
          ? null
          : 'Follow naming conventions: interfaces should be named using '.
            'UpperCamelCase.',
      );
    }


    $functions = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
    foreach ($functions as $function) {
      $name_token = $function->getChildByIndex(2);
      if ($name_token->getTypeName() == 'n_EMPTY') {
        // Unnamed closure.
        continue;
      }
      $name_string = $name_token->getConcreteString();
      $names[] = array(
        'function',
        $name_string,
        $name_token,
        ArcanistXHPASTLintNamingHook::isLowercaseWithUnderscores(
          ArcanistXHPASTLintNamingHook::stripPHPFunction($name_string))
          ? null
          : 'Follow naming conventions: functions should be named using '.
            'lowercase_with_underscores.',
      );
    }


    $methods = $root->selectDescendantsOfType('n_METHOD_DECLARATION');
    foreach ($methods as $method) {
      $name_token = $method->getChildByIndex(2);
      $name_string = $name_token->getConcreteString();
      $names[] = array(
        'method',
        $name_string,
        $name_token,
        ArcanistXHPASTLintNamingHook::isLowerCamelCase(
          ArcanistXHPASTLintNamingHook::stripPHPFunction($name_string))
          ? null
          : 'Follow naming conventions: methods should be named using '.
            'lowerCamelCase.',
      );
    }

    $param_tokens = array();

    $params = $root->selectDescendantsOfType('n_DECLARATION_PARAMETER_LIST');
    foreach ($params as $param_list) {
      foreach ($param_list->getChildren() as $param) {
        $name_token = $param->getChildByIndex(1);
        if ($name_token->getTypeName() == 'n_VARIABLE_REFERENCE') {
          $name_token = $name_token->getChildOfType(0, 'n_VARIABLE');
        }
        $param_tokens[$name_token->getID()] = true;
        $name_string = $name_token->getConcreteString();

        $names[] = array(
          'parameter',
          $name_string,
          $name_token,
          ArcanistXHPASTLintNamingHook::isLowercaseWithUnderscores(
            ArcanistXHPASTLintNamingHook::stripPHPVariable($name_string))
            ? null
            : 'Follow naming conventions: parameters should be named using '.
              'lowercase_with_underscores.',
        );
      }
    }


    $constants = $root->selectDescendantsOfType(
      'n_CLASS_CONSTANT_DECLARATION_LIST');
    foreach ($constants as $constant_list) {
      foreach ($constant_list->getChildren() as $constant) {
        $name_token = $constant->getChildByIndex(0);
        $name_string = $name_token->getConcreteString();
        $names[] = array(
          'constant',
          $name_string,
          $name_token,
          ArcanistXHPASTLintNamingHook::isUppercaseWithUnderscores($name_string)
            ? null
            : 'Follow naming conventions: class constants should be named '.
              'using UPPERCASE_WITH_UNDERSCORES.',
        );
      }
    }

    $member_tokens = array();

    $props = $root->selectDescendantsOfType('n_CLASS_MEMBER_DECLARATION_LIST');
    foreach ($props as $prop_list) {
      foreach ($prop_list->getChildren() as $token_id => $prop) {
        if ($prop->getTypeName() == 'n_CLASS_MEMBER_MODIFIER_LIST') {
          continue;
        }

        $name_token = $prop->getChildByIndex(0);
        $member_tokens[$name_token->getID()] = true;

        $name_string = $name_token->getConcreteString();
        $names[] = array(
          'member',
          $name_string,
          $name_token,
          ArcanistXHPASTLintNamingHook::isLowerCamelCase(
            ArcanistXHPASTLintNamingHook::stripPHPVariable($name_string))
            ? null
            : 'Follow naming conventions: class properties should be named '.
              'using lowerCamelCase.',
        );
      }
    }

    $superglobal_map = array_fill_keys(
      $this->getSuperGlobalNames(),
      true);


    $fdefs = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
    $mdefs = $root->selectDescendantsOfType('n_METHOD_DECLARATION');
    $defs = $fdefs->add($mdefs);

    foreach ($defs as $def) {
      $globals = $def->selectDescendantsOfType('n_GLOBAL_DECLARATION_LIST');
      $globals = $globals->selectDescendantsOfType('n_VARIABLE');

      $globals_map = array();
      foreach ($globals as $global) {
        $global_string = $global->getConcreteString();
        $globals_map[$global_string] = true;
        $names[] = array(
          'global',
          $global_string,
          $global,

          // No advice for globals, but hooks have an option to provide some.
          null);
      }

      // Exclude access of static properties, since lint will be raised at
      // their declaration if they're invalid and they may not conform to
      // variable rules. This is slightly overbroad (includes the entire
      // rhs of a "Class::..." token) to cover cases like "Class:$x[0]". These
      // varaibles are simply made exempt from naming conventions.
      $exclude_tokens = array();
      $statics = $def->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
      foreach ($statics as $static) {
        $rhs = $static->getChildByIndex(1);
        $rhs_vars = $def->selectDescendantsOfType('n_VARIABLE');
        foreach ($rhs_vars as $var) {
          $exclude_tokens[$var->getID()] = true;
        }
      }

      $vars = $def->selectDescendantsOfType('n_VARIABLE');
      foreach ($vars as $token_id => $var) {
        if (isset($member_tokens[$token_id])) {
          continue;
        }
        if (isset($param_tokens[$token_id])) {
          continue;
        }
        if (isset($exclude_tokens[$token_id])) {
          continue;
        }

        $var_string = $var->getConcreteString();

        // Awkward artifact of "$o->{$x}".
        $var_string = trim($var_string, '{}');

        if (isset($superglobal_map[$var_string])) {
          continue;
        }
        if (isset($globals_map[$var_string])) {
          continue;
        }

        $names[] = array(
          'variable',
          $var_string,
          $var,
          ArcanistXHPASTLintNamingHook::isLowercaseWithUnderscores(
            ArcanistXHPASTLintNamingHook::stripPHPVariable($var_string))
              ? null
              : 'Follow naming conventions: variables should be named using '.
                'lowercase_with_underscores.',
        );
      }
    }

    $engine = $this->getEngine();
    $working_copy = $engine->getWorkingCopy();

    if ($working_copy) {
      // If a naming hook is configured, give it a chance to override the
      // default results for all the symbol names.
      $hook_class = $working_copy->getConfig('lint.xhpast.naminghook');
      if ($hook_class) {
        $hook_obj = newv($hook_class, array());
        foreach ($names as $k => $name_attrs) {
          list($type, $name, $token, $default) = $name_attrs;
          $result = $hook_obj->lintSymbolName($type, $name, $default);
          $names[$k][3] = $result;
        }
      }
    }

    // Raise anything we're left with.
    foreach ($names as $k => $name_attrs) {
      list($type, $name, $token, $result) = $name_attrs;
      if ($result) {
        $this->raiseLintAtNode(
          $token,
          self::LINT_NAMING_CONVENTIONS,
          $result);
      }
    }
  }

  protected function lintSurpriseConstructors($root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    foreach ($classes as $class) {
      $class_name = $class->getChildByIndex(1)->getConcreteString();
      $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');
      foreach ($methods as $method) {
        $method_name_token = $method->getChildByIndex(2);
        $method_name = $method_name_token->getConcreteString();
        if (strtolower($class_name) == strtolower($method_name)) {
          $this->raiseLintAtNode(
            $method_name_token,
            self::LINT_IMPLICIT_CONSTRUCTOR,
            'Name constructors __construct() explicitly. This method is a '.
            'constructor because it has the same name as the class it is '.
            'defined in.');
        }
      }
    }
  }

  protected function lintParenthesesShouldHugExpressions($root) {
    $calls = $root->selectDescendantsOfType('n_CALL_PARAMETER_LIST');
    $controls = $root->selectDescendantsOfType('n_CONTROL_CONDITION');
    $fors = $root->selectDescendantsOfType('n_FOR_EXPRESSION');
    $foreach = $root->selectDescendantsOfType('n_FOREACH_EXPRESSION');
    $decl = $root->selectDescendantsOfType('n_DECLARATION_PARAMETER_LIST');

    $all_paren_groups = $calls
      ->add($controls)
      ->add($fors)
      ->add($foreach)
      ->add($decl);
    foreach ($all_paren_groups as $group) {
      $tokens = $group->getTokens();

      $token_o = array_shift($tokens);
      $token_c = array_pop($tokens);
      if ($token_o->getTypeName() != '(') {
        throw new Exception('Expected open paren!');
      }
      if ($token_c->getTypeName() != ')') {
        throw new Exception('Expected close paren!');
      }

      $nonsem_o = $token_o->getNonsemanticTokensAfter();
      $nonsem_c = $token_c->getNonsemanticTokensBefore();

      if (!$nonsem_o) {
        continue;
      }

      $raise = array();

      $string_o = implode('', mpull($nonsem_o, 'getValue'));
      if (preg_match('/^[ ]+$/', $string_o)) {
        $raise[] = array($nonsem_o, $string_o);
      }

      if ($nonsem_o !== $nonsem_c) {
        $string_c = implode('', mpull($nonsem_c, 'getValue'));
        if (preg_match('/^[ ]+$/', $string_c)) {
          $raise[] = array($nonsem_c, $string_c);
        }
      }

      foreach ($raise as $warning) {
        list($tokens, $string) = $warning;
        $this->raiseLintAtOffset(
          reset($tokens)->getOffset(),
          self::LINT_PARENTHESES_SPACING,
          'Parentheses should hug their contents.',
          $string,
          '');
      }
    }
  }

  protected function lintSpaceAfterControlStatementKeywords($root) {
    foreach ($root->getTokens() as $id => $token) {
      switch ($token->getTypeName()) {
        case 'T_IF':
        case 'T_ELSE':
        case 'T_FOR':
        case 'T_FOREACH':
        case 'T_WHILE':
        case 'T_DO':
        case 'T_SWITCH':
          $after = $token->getNonsemanticTokensAfter();
          if (empty($after)) {
            $this->raiseLintAtToken(
              $token,
              self::LINT_CONTROL_STATEMENT_SPACING,
              'Convention: put a space after control statements.',
              $token->getValue().' ');
          } else if (count($after) == 1) {
            $space = head($after);

            // If we have an else clause with braces, $space may not be
            // a single white space. e.g.,
            //
            //  if ($x)
            //    echo 'foo'
            //  else          // <- $space is not " " but "\n  ".
            //    echo 'bar'
            //
            // We just require it starts with either a whitespace or a newline.
            if ($token->getTypeName() == 'T_ELSE' ||
                $token->getTypeName() == 'T_DO') {
              break;
            }

            if ($space->isAnyWhitespace() && $space->getValue() != ' ') {
              $this->raiseLintAtToken(
                $space,
                self::LINT_CONTROL_STATEMENT_SPACING,
                'Convention: put a single space after control statements.',
                ' ');
            }
          }
          break;
      }
    }
  }

  protected function lintSpaceAroundBinaryOperators($root) {

    // NOTE: '.' is parsed as n_CONCATENATION_LIST, not n_BINARY_EXPRESSION,
    // so we don't select it here.

    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');
    foreach ($expressions as $expression) {
      $operator = $expression->getChildByIndex(1);
      $operator_value = $operator->getConcreteString();
      list($before, $after) = $operator->getSurroundingNonsemanticTokens();

      $replace = null;
      if (empty($before) && empty($after)) {
        $replace = " {$operator_value} ";
      } else if (empty($before)) {
        $replace = " {$operator_value}";
      } else if (empty($after)) {
        $replace = "{$operator_value} ";
      }

      if ($replace !== null) {
        $this->raiseLintAtNode(
          $operator,
          self::LINT_BINARY_EXPRESSION_SPACING,
          'Convention: logical and arithmetic operators should be '.
          'surrounded by whitespace.',
          $replace);
      }
    }

    $tokens = $root->selectTokensOfType(',');
    foreach ($tokens as $token) {
      $next = $token->getNextToken();
      switch ($next->getTypeName()) {
        case ')':
        case 'T_WHITESPACE':
          break;
          break;
        default:
          $this->raiseLintAtToken(
            $token,
            self::LINT_BINARY_EXPRESSION_SPACING,
            'Convention: comma should be followed by space.',
            ', ');
          break;
      }
    }

    // TODO: Spacing around ".".
    // TODO: Spacing around default parameter assignment in function/method
    // declarations (which is not n_BINARY_EXPRESSION).
  }

  protected function lintDynamicDefines($root) {
    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();
      if (strtolower($name) == 'define') {
        $parameter_list = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
        $defined = $parameter_list->getChildByIndex(0);
        if (!$defined->isStaticScalar()) {
          $this->raiseLintAtNode(
            $defined,
            self::LINT_DYNAMIC_DEFINE,
            'First argument to define() must be a string literal.');
        }
      }
    }
  }

  protected function lintUseOfThisInStaticMethods($root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    foreach ($classes as $class) {
      $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');
      foreach ($methods as $method) {

        $attributes = $method
          ->getChildByIndex(0, 'n_METHOD_MODIFIER_LIST')
          ->selectDescendantsOfType('n_STRING');

        $method_is_static = false;
        $method_is_abstract = false;
        foreach ($attributes as $attribute) {
          if (strtolower($attribute->getConcreteString()) == 'static') {
            $method_is_static = true;
          }
          if (strtolower($attribute->getConcreteString()) == 'abstract') {
            $method_is_abstract = true;
          }
        }

        if ($method_is_abstract) {
          continue;
        }

        if (!$method_is_static) {
          continue;
        }

        $body = $method->getChildOfType(5, 'n_STATEMENT_LIST');

        $variables = $body->selectDescendantsOfType('n_VARIABLE');
        foreach ($variables as $variable) {
          if ($method_is_static &&
              strtolower($variable->getConcreteString()) == '$this') {
            $this->raiseLintAtNode(
              $variable,
              self::LINT_STATIC_THIS,
              'You can not reference "$this" inside a static method.');
          }
        }
      }
    }
  }

  /**
   * preg_quote() takes two arguments, but the second one is optional because
   * it is possible to use (), [] or {} as regular expression delimiters.  If
   * you don't pass a second argument, you're probably going to get something
   * wrong.
   */
  protected function lintPregQuote($root) {
    $function_calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($function_calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();
      if (strtolower($name) === 'preg_quote') {
        $parameter_list = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
        if (count($parameter_list->getChildren()) !== 2) {
          $this->raiseLintAtNode(
            $call,
            self::LINT_PREG_QUOTE_MISUSE,
            'You should always pass two arguments to preg_quote(), so that ' .
            'preg_quote() knows which delimiter to escape.');
        }
      }
    }
  }

  /**
   * Exit is parsed as an expression, but using it as such is almost always
   * wrong. That is, this is valid:
   *
   *    strtoupper(33 * exit - 6);
   *
   * When exit is used as an expression, it causes the program to terminate with
   * exit code 0. This is likely not what is intended; these statements have
   * different effects:
   *
   *    exit(-1);
   *    exit -1;
   *
   * The former exits with a failure code, the latter with a success code!
   */
  protected function lintExitExpressions($root) {
    $unaries = $root->selectDescendantsOfType('n_UNARY_PREFIX_EXPRESSION');
    foreach ($unaries as $unary) {
      $operator = $unary->getChildByIndex(0)->getConcreteString();
      if (strtolower($operator) == 'exit') {
        if ($unary->getParentNode()->getTypeName() != 'n_STATEMENT') {
          $this->raiseLintAtNode(
            $unary,
            self::LINT_EXIT_EXPRESSION,
            "Use exit as a statement, not an expression.");
        }
      }
    }
  }

  private function lintArrayIndexWhitespace($root) {
    $indexes = $root->selectDescendantsOfType('n_INDEX_ACCESS');
    foreach ($indexes as $index) {
      $tokens = $index->getChildByIndex(0)->getTokens();
      $last = array_pop($tokens);
      $trailing = $last->getNonsemanticTokensAfter();
      $trailing_text = implode('', mpull($trailing, 'getValue'));
      if (preg_match('/^ +$/', $trailing_text)) {
        $this->raiseLintAtOffset(
          $last->getOffset() + strlen($last->getValue()),
          self::LINT_ARRAY_INDEX_SPACING,
          'Convention: no spaces before index access.',
          $trailing_text,
          '');
      }
    }
  }

  protected function lintTODOComments($root) {
    $comments = $root->selectTokensOfType('T_COMMENT') +
                $root->selectTokensOfType('T_DOC_COMMENT');

    foreach ($comments as $token) {
      $value = $token->getValue();
      $matches = null;
      $preg = preg_match_all(
        '/TODO/',
        $value,
        $matches,
        PREG_OFFSET_CAPTURE);

      foreach ($matches[0] as $match) {
        list($string, $offset) = $match;
        $this->raiseLintAtOffset(
          $token->getOffset() + $offset,
          self::LINT_TODO_COMMENT,
          'This comment has a TODO.',
          $string);
      }
    }
  }

  /**
   * Lint that if the file declares exactly one interface or class,
   * the name of the file matches the name of the class,
   * unless the classname is funky like an XHP element.
   */
  private function lintPrimaryDeclarationFilenameMatch($root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    $interfaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');

    if (count($classes) + count($interfaces) != 1) {
      return;
    }

    $declarations = count($classes) ? $classes : $interfaces;
    $declarations->rewind();
    $declaration = $declarations->current();

    $decl_name = $declaration->getChildByIndex(1);
    $decl_string = $decl_name->getConcreteString();

    // Exclude strangely named classes, e.g. XHP tags.
    if (!preg_match('/^\w+$/', $decl_string)) {
      return;
    }

    $rename = $decl_string.'.php';

    $path = $this->getActivePath();
    $filename = basename($path);

    if ($rename == $filename) {
      return;
    }

    $this->raiseLintAtNode(
      $decl_name,
      self::LINT_CLASS_FILENAME_MISMATCH,
      "The name of this file differs from the name of the class or interface ".
      "it declares. Rename the file to '{$rename}'."
    );
  }

  private function lintPlusOperatorOnStrings($root) {
    $binops = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');
    foreach ($binops as $binop) {
      $op = $binop->getChildByIndex(1);
      if ($op->getConcreteString() != '+') {
        continue;
      }

      $left = $binop->getChildByIndex(0);
      $right = $binop->getChildByIndex(2);
      if (($left->getTypeName() == 'n_STRING_SCALAR') ||
          ($right->getTypeName() == 'n_STRING_SCALAR')) {
        $this->raiseLintAtNode(
          $binop,
          self::LINT_PLUS_OPERATOR_ON_STRINGS,
          "In PHP, '.' is the string concatenation operator, not '+'. This ".
          "expression uses '+' with a string literal as an operand.");
      }
    }
  }

  /**
   * Finds duplicate keys in array initializers, as in
   * array(1 => 'anything', 1 => 'foo').  Since the first entry is ignored,
   * this is almost certainly an error.
   */
  private function lintDuplicateKeysInArray($root) {
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
        foreach ($nodes_by_key[$key] as $node) {
          $this->raiseLintAtNode(
            $node,
            self::LINT_DUPLICATE_KEYS_IN_ARRAY,
            "Duplicate key in array initializer. PHP will ignore all ".
            "but the last entry.");
        }
      }
    }
  }

  private function lintRaggedClasstreeEdges($root) {
    $parser = new PhutilDocblockParser();

    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
    foreach ($classes as $class) {

      $is_final = false;
      $is_abstract = false;
      $is_concrete_extensible = false;

      $attributes = $class->getChildOfType(0, 'n_CLASS_ATTRIBUTES');
      foreach ($attributes->getChildren() as $child) {
        if ($child->getConcreteString() == 'final') {
          $is_final = true;
        }
        if ($child->getConcreteString() == 'abstract') {
          $is_abstract = true;
        }
      }

      $docblock = $class->getDocblockToken();
      if ($docblock) {
        list($text, $specials) = $parser->parse($docblock->getValue());
        $is_concrete_extensible = idx($specials, 'concrete-extensible');
      }

      if (!$is_final && !$is_abstract && !$is_concrete_extensible) {
        $this->raiseLintAtNode(
          $class->getChildOfType(1, 'n_CLASS_NAME'),
          self::LINT_RAGGED_CLASSTREE_EDGE,
          "This class is neither 'final' nor 'abstract', and does not have ".
          "a docblock marking it '@concrete-extensible'.");
      }
    }
  }

  protected function raiseLintAtToken(
    XHPASTToken $token,
    $code,
    $desc,
    $replace = null) {
    return $this->raiseLintAtOffset(
      $token->getOffset(),
      $code,
      $desc,
      $token->getValue(),
      $replace);
  }

  protected function raiseLintAtNode(
    XHPASTNode $node,
    $code,
    $desc,
    $replace = null) {
    return $this->raiseLintAtOffset(
      $node->getOffset(),
      $code,
      $desc,
      $node->getConcreteString(),
      $replace);
  }

  public function getSuperGlobalNames() {
    return array(
      '$GLOBALS',
      '$_SERVER',
      '$_GET',
      '$_POST',
      '$_FILES',
      '$_COOKIE',
      '$_SESSION',
      '$_REQUEST',
      '$_ENV',
    );
  }

}
