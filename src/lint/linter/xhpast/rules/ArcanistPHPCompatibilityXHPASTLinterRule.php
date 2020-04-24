<?php

final class ArcanistPHPCompatibilityXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 45;

  public function getLintName() {
    return pht('PHP Compatibility');
  }

  public function process(XHPASTNode $root) {
    static $compat_info;

    if (!$this->version) {
      return;
    }

    if ($compat_info === null) {
      $target = phutil_get_library_root('arcanist').
        '/../resources/php/symbol-information.json';
      $compat_info = phutil_json_decode(Filesystem::readFile($target));
    }

    // Create a whitelist for symbols which are being used conditionally.
    $whitelist = array(
      'class'    => array(),
      'function' => array(),
      'constant' => array(),
    );

    $conditionals = $root->selectDescendantsOfType('n_IF');
    foreach ($conditionals as $conditional) {
      $condition = $conditional->getChildOfType(0, 'n_CONTROL_CONDITION');
      $function  = $condition->getChildByIndex(0);

      if ($function->getTypeName() != 'n_FUNCTION_CALL') {
        continue;
      }

      $function_token = $function
        ->getChildByIndex(0);

      if ($function_token->getTypeName() != 'n_SYMBOL_NAME') {
        // This may be `Class::method(...)` or `$var(...)`.
        continue;
      }

      $function_name = $function_token->getConcreteString();

      switch ($function_name) {
        case 'class_exists':
        case 'function_exists':
        case 'interface_exists':
        case 'defined':
          $type = null;
          switch ($function_name) {
            case 'class_exists':
              $type = 'class';
              break;

            case 'function_exists':
              $type = 'function';
              break;

            case 'interface_exists':
              $type = 'interface';
              break;

            case 'defined':
              $type = 'constant';
              break;
          }

          $params = $function->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
          $symbol = $params->getChildByIndex(0);

          if (!$symbol->isStaticScalar()) {
            break;
          }

          $symbol_name = $symbol->evalStatic();
          if (!idx($whitelist[$type], $symbol_name)) {
            $whitelist[$type][$symbol_name] = array();
          }

          $span = $conditional
            ->getChildByIndex(1)
            ->getTokens();

          $whitelist[$type][$symbol_name][] = range(
            head_key($span),
            last_key($span));
          break;
      }
    }

    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($calls as $call) {
      $node = $call->getChildByIndex(0);
      $name = $node->getConcreteString();

      $version = idx($compat_info['functions'], $name, array());
      $min = idx($version, 'php.min');
      $max = idx($version, 'php.max');

      $whitelisted = false;
      foreach (idx($whitelist['function'], $name, array()) as $range) {
        if (array_intersect($range, array_keys($node->getTokens()))) {
          $whitelisted = true;
          break;
        }
      }

      if ($whitelisted) {
        continue;
      }

      if ($min && version_compare($min, $this->version, '>')) {
        $this->raiseLintAtNode(
          $node,
          pht(
            'This codebase targets PHP %s, but `%s()` was not '.
            'introduced until PHP %s.',
            $this->version,
            $name,
            $min));
      } else if ($max && version_compare($max, $this->version, '<')) {
        $this->raiseLintAtNode(
          $node,
          pht(
            'This codebase targets PHP %s, but `%s()` was '.
            'removed in PHP %s.',
            $this->version,
            $name,
            $max));
      } else if (array_key_exists($name, $compat_info['params'])) {
        $params = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
        foreach (array_values($params->getChildren()) as $i => $param) {
          $version = idx($compat_info['params'][$name], $i);
          if ($version && version_compare($version, $this->version, '>')) {
            $this->raiseLintAtNode(
              $param,
              pht(
                'This codebase targets PHP %s, but parameter %d '.
                'of `%s()` was not introduced until PHP %s.',
                $this->version,
                $i + 1,
                $name,
                $version));
          }
        }
      }

      if ($this->windowsVersion) {
        $windows = idx($compat_info['functions_windows'], $name);

        if ($windows === false) {
          $this->raiseLintAtNode(
            $node,
            pht(
              'This codebase targets PHP %s on Windows, '.
              'but `%s()` is not available there.',
              $this->windowsVersion,
              $name));
        } else if (version_compare($windows, $this->windowsVersion, '>')) {
          $this->raiseLintAtNode(
            $node,
            pht(
              'This codebase targets PHP %s on Windows, '.
              'but `%s()` is not available there until PHP %s.',
              $this->windowsVersion,
              $name,
              $windows));
        }
      }
    }

    $classes = $root->selectDescendantsOfType('n_CLASS_NAME');
    foreach ($classes as $node) {
      $name = $node->getConcreteString();
      $version = idx($compat_info['interfaces'], $name, array());
      $version = idx($compat_info['classes'], $name, $version);
      $min = idx($version, 'php.min');
      $max = idx($version, 'php.max');

      $whitelisted = false;
      foreach (idx($whitelist['class'], $name, array()) as $range) {
        if (array_intersect($range, array_keys($node->getTokens()))) {
          $whitelisted = true;
          break;
        }
      }

      if ($whitelisted) {
        continue;
      }

      if ($min && version_compare($min, $this->version, '>')) {
        $this->raiseLintAtNode(
          $node,
          pht(
            'This codebase targets PHP %s, but `%s` was not '.
            'introduced until PHP %s.',
            $this->version,
            $name,
            $min));
      } else if ($max && version_compare($max, $this->version, '<')) {
        $this->raiseLintAtNode(
          $node,
          pht(
            'This codebase targets PHP %s, but `%s` was '.
            'removed in PHP %s.',
            $this->version,
            $name,
            $max));
      }
    }

    // TODO: Technically, this will include function names. This is unlikely to
    // cause any issues (unless, of course, there existed a function that had
    // the same name as some constant).
    $constants = $root->selectDescendantsOfTypes(array(
      'n_SYMBOL_NAME',
      'n_MAGIC_SCALAR',
    ));
    foreach ($constants as $node) {
      $name = $node->getConcreteString();
      $version = idx($compat_info['constants'], $name, array());
      $min = idx($version, 'php.min');
      $max = idx($version, 'php.max');

      $whitelisted = false;
      foreach (idx($whitelist['constant'], $name, array()) as $range) {
        if (array_intersect($range, array_keys($node->getTokens()))) {
          $whitelisted = true;
          break;
        }
      }

      if ($whitelisted) {
        continue;
      }

      if ($min && version_compare($min, $this->version, '>')) {
        $this->raiseLintAtNode(
          $node,
          pht(
            'This codebase targets PHP %s, but `%s` was not '.
            'introduced until PHP %s.',
            $this->version,
            $name,
            $min));
      } else if ($max && version_compare($max, $this->version, '<')) {
        $this->raiseLintAtNode(
          $node,
          pht(
            'This codebase targets PHP %s, but `%s` was '.
            'removed in PHP %s.',
            $this->version,
            $name,
            $max));
      }
    }

    if (version_compare($this->version, '5.3.0') < 0) {
      $this->lintPHP53Features($root);
    } else {
      $this->lintPHP53Incompatibilities($root);
    }

    if (version_compare($this->version, '5.4.0') < 0) {
      $this->lintPHP54Features($root);
    } else {
      $this->lintPHP54Incompatibilities($root);
    }
  }

  private function lintPHP53Features(XHPASTNode $root) {
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
        if ($next->getTypeName() === '(') {
          $this->raiseLintAtToken(
            $function,
            pht(
              'This codebase targets PHP %s, but anonymous '.
              'functions were not introduced until PHP 5.3.',
              $this->version));
        }
      }
    }

    $namespaces = $root->selectTokensOfType('T_NAMESPACE');
    foreach ($namespaces as $namespace) {
      $this->raiseLintAtToken(
        $namespace,
        pht(
          'This codebase targets PHP %s, but namespaces were not '.
          'introduced until PHP 5.3.',
          $this->version));
    }

    // NOTE: This is only "use x;", in anonymous functions the node type is
    // n_LEXICAL_VARIABLE_LIST even though both tokens are T_USE.

    $uses = $root->selectDescendantsOfType('n_USE_LIST');
    foreach ($uses as $use) {
      $this->raiseLintAtNode(
        $use,
        pht(
          'This codebase targets PHP %s, but namespaces were not '.
          'introduced until PHP 5.3.',
          $this->version));
    }

    $statics = $root->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
    foreach ($statics as $static) {
      $name = $static->getChildByIndex(0);
      if ($name->getTypeName() != 'n_CLASS_NAME') {
        continue;
      }
      if ($name->getConcreteString() === 'static') {
        $this->raiseLintAtNode(
          $name,
          pht(
            'This codebase targets PHP %s, but `%s` was not '.
            'introduced until PHP 5.3.',
            $this->version,
            'static::'));
      }
    }

    $ternaries = $root->selectDescendantsOfType('n_TERNARY_EXPRESSION');
    foreach ($ternaries as $ternary) {
      $yes = $ternary->getChildByIndex(2);
      if ($yes->getTypeName() === 'n_EMPTY') {
        $this->raiseLintAtNode(
          $ternary,
          pht(
            'This codebase targets PHP %s, but short ternary was '.
            'not introduced until PHP 5.3.',
            $this->version));
      }
    }

    $heredocs = $root->selectDescendantsOfType('n_HEREDOC');
    foreach ($heredocs as $heredoc) {
      if (preg_match('/^<<<[\'"]/', $heredoc->getConcreteString())) {
        $this->raiseLintAtNode(
          $heredoc,
          pht(
            'This codebase targets PHP %s, but nowdoc was not '.
            'introduced until PHP 5.3.',
            $this->version));
      }
    }
  }

  private function lintPHP53Incompatibilities(XHPASTNode $root) {}

  private function lintPHP54Features(XHPASTNode $root) {
    $indexes = $root->selectDescendantsOfType('n_INDEX_ACCESS');

    foreach ($indexes as $index) {
      switch ($index->getChildByIndex(0)->getTypeName()) {
        case 'n_FUNCTION_CALL':
        case 'n_METHOD_CALL':
          $this->raiseLintAtNode(
            $index->getChildByIndex(1),
            pht(
              'The `%s` syntax was not introduced until PHP 5.4, but this '.
              'codebase targets an earlier version of PHP. You can rewrite '.
              'this expression using `%s`.',
              'f()[...]',
              'idx()'));
          break;
      }
    }

    $literals = $root->selectDescendantsOfType('n_ARRAY_LITERAL');
    foreach ($literals as $literal) {
      $open_token = head($literal->getTokens())->getValue();
      if ($open_token == '[') {
        $this->raiseLintAtNode(
          $literal,
          pht(
            'The short array syntax ("[...]") was not introduced until '.
            'PHP 5.4, but this codebase targets an earlier version of PHP. '.
            'You can rewrite this expression using `array(...)` instead.'));
      }
    }

    $closures = $this->getAnonymousClosures($root);
    foreach ($closures as $closure) {
      $static_accesses = $closure
        ->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');

      foreach ($static_accesses as $static_access) {
        $class = $static_access->getChildByIndex(0);

        if ($class->getTypeName() != 'n_CLASS_NAME') {
          continue;
        }

        if (strtolower($class->getConcreteString()) != 'self') {
          continue;
        }

        $this->raiseLintAtNode(
          $class,
          pht(
            'The use of `%s` in an anonymous closure is not '.
            'available before PHP 5.4.',
            'self'));
      }

      $property_accesses = $closure
        ->selectDescendantsOfType('n_OBJECT_PROPERTY_ACCESS');
      foreach ($property_accesses as $property_access) {
        $variable = $property_access->getChildByIndex(0);

        if ($variable->getTypeName() != 'n_VARIABLE') {
          continue;
        }

        if ($variable->getConcreteString() != '$this') {
          continue;
        }

        $this->raiseLintAtNode(
          $variable,
          pht(
            'The use of `%s` in an anonymous closure is not '.
            'available before PHP 5.4.',
            '$this'));
      }
    }

    $numeric_scalars = $root->selectDescendantsOfType('n_NUMERIC_SCALAR');
    foreach ($numeric_scalars as $numeric_scalar) {
      if (preg_match('/^0b[01]+$/i', $numeric_scalar->getConcreteString())) {
        $this->raiseLintAtNode(
          $numeric_scalar,
          pht(
            'Binary integer literals are not available before PHP 5.4.'));
      }
    }
  }

  private function lintPHP54Incompatibilities(XHPASTNode $root) {
    $breaks = $root->selectDescendantsOfTypes(array('n_BREAK', 'n_CONTINUE'));

    foreach ($breaks as $break) {
      $arg = $break->getChildByIndex(0);

      switch ($arg->getTypeName()) {
        case 'n_EMPTY':
          break;

        case 'n_NUMERIC_SCALAR':
          if ($arg->getConcreteString() != '0') {
            break;
          }

        default:
          $this->raiseLintAtNode(
            $break->getChildByIndex(0),
            pht(
              'The `%s` and `%s` statements no longer accept '.
              'variable arguments.',
              'break',
              'continue'));
          break;
      }
    }
  }

}
