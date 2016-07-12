<?php

final class ArcanistNamingConventionsXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 9;

  private $naminghook;

  public function getLintName() {
    return pht('Naming Conventions');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function getLinterConfigurationOptions() {
    return parent::getLinterConfigurationOptions() + array(
      'xhpast.naminghook' => array(
        'type' => 'optional string',
        'help' => pht(
          'Name of a concrete subclass of `%s` which enforces more '.
          'granular naming convention rules for symbols.',
          'ArcanistXHPASTLintNamingHook'),
      ),
    );
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'xhpast.naminghook':
        $this->naminghook = $value;
        return;

      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
  }

  public function process(XHPASTNode $root) {
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
          : pht(
            'Follow naming conventions: classes should be named using `%s`.',
            'UpperCamelCase'),
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
          : pht(
            'Follow naming conventions: interfaces should be named using `%s`.',
            'UpperCamelCase'),
      );
    }


    $functions = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
    foreach ($functions as $function) {
      $name_token = $function->getChildByIndex(2);
      if ($name_token->getTypeName() === 'n_EMPTY') {
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
          : pht(
            'Follow naming conventions: functions should be named using `%s`.',
            'lowercase_with_underscores'),
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
          : pht(
            'Follow naming conventions: methods should be named using `%s`.',
            'lowerCamelCase'),
      );
    }

    $param_tokens = array();

    $params = $root->selectDescendantsOfType('n_DECLARATION_PARAMETER_LIST');
    foreach ($params as $param_list) {
      foreach ($param_list->getChildren() as $param) {
        $name_token = $param->getChildByIndex(1);
        if ($name_token->getTypeName() === 'n_VARIABLE_REFERENCE') {
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
            : pht(
              'Follow naming conventions: parameters '.
              'should be named using `%s`',
              'lowercase_with_underscores'),
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
            : pht(
              'Follow naming conventions: class constants '.
              'should be named using `%s`',
              'UPPERCASE_WITH_UNDERSCORES'),
        );
      }
    }

    $member_tokens = array();

    $props = $root->selectDescendantsOfType('n_CLASS_MEMBER_DECLARATION_LIST');
    foreach ($props as $prop_list) {
      foreach ($prop_list->getChildren() as $token_id => $prop) {
        if ($prop->getTypeName() === 'n_CLASS_MEMBER_MODIFIER_LIST') {
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
            : pht(
              'Follow naming conventions: class properties '.
              'should be named using `%s`.',
              'lowerCamelCase'),
        );
      }
    }

    $superglobal_map = array_fill_keys(
      $this->getSuperGlobalNames(),
      true);


    $defs = $root->selectDescendantsOfTypes(array(
      'n_FUNCTION_DECLARATION',
      'n_METHOD_DECLARATION',
    ));

    foreach ($defs as $def) {
      $globals = $def->selectDescendantsOfType('n_GLOBAL_DECLARATION_LIST');
      $globals = $globals->selectDescendantsOfType('n_VARIABLE');

      $globals_map = array();
      foreach ($globals as $global) {
        $global_string = $global->getConcreteString();
        $globals_map[$global_string] = true;
        $names[] = array(
          'user',
          $global_string,
          $global,

          // No advice for globals, but hooks have an option to provide some.
          null,
        );
      }

      // Exclude access of static properties, since lint will be raised at
      // their declaration if they're invalid and they may not conform to
      // variable rules. This is slightly overbroad (includes the entire
      // RHS of a "Class::..." token) to cover cases like "Class:$x[0]". These
      // variables are simply made exempt from naming conventions.
      $exclude_tokens = array();
      $statics = $def->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
      foreach ($statics as $static) {
        $rhs = $static->getChildByIndex(1);
        if ($rhs->getTypeName() == 'n_VARIABLE') {
          $exclude_tokens[$rhs->getID()] = true;
        } else {
          $rhs_vars = $rhs->selectDescendantsOfType('n_VARIABLE');
          foreach ($rhs_vars as $var) {
            $exclude_tokens[$var->getID()] = true;
          }
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
              : pht(
                'Follow naming conventions: variables '.
                'should be named using `%s`.',
                'lowercase_with_underscores'),
        );
      }
    }

    // If a naming hook is configured, give it a chance to override the
    // default results for all the symbol names.
    $hook_class = $this->naminghook;
    if ($hook_class) {
      $hook_obj = newv($hook_class, array());
      foreach ($names as $k => $name_attrs) {
        list($type, $name, $token, $default) = $name_attrs;
        $result = $hook_obj->lintSymbolName($type, $name, $default);
        $names[$k][3] = $result;
      }
    }

    // Raise anything we're left with.
    foreach ($names as $k => $name_attrs) {
      list($type, $name, $token, $result) = $name_attrs;
      if ($result) {
        $this->raiseLintAtNode(
          $token,
          $result);
      }
    }

    // Lint constant declarations.
    $defines = $this
      ->getFunctionCalls($root, array('define'))
      ->add($root->selectDescendantsOfTypes(array(
        'n_CLASS_CONSTANT_DECLARATION',
        'n_CONSTANT_DECLARATION',
      )));

    foreach ($defines as $define) {
      switch ($define->getTypeName()) {
        case 'n_CLASS_CONSTANT_DECLARATION':
        case 'n_CONSTANT_DECLARATION':
          $constant = $define->getChildByIndex(0);

          if ($constant->getTypeName() !== 'n_STRING') {
            $constant = null;
          }

          break;

        case 'n_FUNCTION_CALL':
          $constant = $define
            ->getChildOfType(1, 'n_CALL_PARAMETER_LIST')
            ->getChildByIndex(0);

          if ($constant->getTypeName() !== 'n_STRING_SCALAR') {
            $constant = null;
          }

          break;

        default:
          $constant = null;
          break;
      }

      if (!$constant) {
        continue;
      }
      $constant_name = $constant->getConcreteString();

      if ($constant_name !== strtoupper($constant_name)) {
        $this->raiseLintAtNode(
          $constant,
          pht('Constants should be uppercase.'));
      }
    }
  }

}
