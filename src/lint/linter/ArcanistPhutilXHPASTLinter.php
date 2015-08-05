<?php

final class ArcanistPhutilXHPASTLinter extends ArcanistBaseXHPASTLinter {

  const LINT_ARRAY_COMBINE          = 2;
  const LINT_DEPRECATED_FUNCTION    = 3;
  const LINT_UNSAFE_DYNAMIC_STRING  = 4;
  const LINT_RAGGED_CLASSTREE_EDGE  = 5;
  const LINT_EXTENDS_PHOBJECT       = 6;

  private $deprecatedFunctions    = array();
  private $dynamicStringFunctions = array();
  private $dynamicStringClasses   = array();

  public function getInfoName() {
    return 'XHPAST/libphutil Lint';
  }

  public function getInfoDescription() {
    return pht(
      'Use XHPAST to run libphutil-specific rules on a PHP library. This '.
      'linter is intended for use in Phabricator libraries and extensions.');
  }

  public function getLintNameMap() {
    return array(
      self::LINT_ARRAY_COMBINE =>
        pht('%s Unreliable', 'array_combine()'),
      self::LINT_DEPRECATED_FUNCTION =>
        pht('Use of Deprecated Function'),
      self::LINT_UNSAFE_DYNAMIC_STRING =>
        pht('Unsafe Usage of Dynamic String'),
      self::LINT_RAGGED_CLASSTREE_EDGE =>
        pht('Class Not %s Or %s', 'abstract', 'final'),
      self::LINT_EXTENDS_PHOBJECT =>
        pht('Class Not Extending %s', 'Phobject'),
    );
  }

  public function getLinterName() {
    return 'PHLXHP';
  }

  public function getLinterConfigurationName() {
    return 'phutil-xhpast';
  }

  public function getLintSeverityMap() {
    $advice  = ArcanistLintSeverity::SEVERITY_ADVICE;
    $warning = ArcanistLintSeverity::SEVERITY_WARNING;

    return array(
      self::LINT_ARRAY_COMBINE          => $warning,
      self::LINT_DEPRECATED_FUNCTION    => $warning,
      self::LINT_UNSAFE_DYNAMIC_STRING  => $warning,
      self::LINT_RAGGED_CLASSTREE_EDGE  => $warning,
      self::LINT_EXTENDS_PHOBJECT       => $advice,
    );
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'phutil-xhpast.deprecated.functions' => array(
        'type' => 'optional map<string, string>',
        'help' => pht(
          'Functions which should should be considered deprecated.'),
      ),
      'phutil-xhpast.dynamic-string.functions' => array(
        'type' => 'optional map<string, string>',
        'help' => pht(
          'Functions which should should not be used because they represent '.
          'the unsafe usage of dynamic strings.'),
      ),
      'phutil-xhpast.dynamic-string.classes' => array(
        'type' => 'optional map<string, string>',
        'help' => pht(
          'Classes which should should not be used because they represent the '.
          'unsafe usage of dynamic strings.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'phutil-xhpast.deprecated.functions':
        $this->setDeprecatedFunctions($value);
        return;
      case 'phutil-xhpast.dynamic-string.functions':
        $this->setDynamicStringFunctions($value);
        return;
      case 'phutil-xhpast.dynamic-string.classes':
        $this->setDynamicStringClasses($value);
        return;
      default:
        parent::setLinterConfigurationValue($key, $value);
        return;
    }
  }

  public function getVersion() {
    // The version number should be incremented whenever a new rule is added.
    return '3';
  }

  protected function resolveFuture($path, Future $future) {
    $tree = $this->getXHPASTLinter()->getXHPASTTreeForPath($path);
    if (!$tree) {
      return;
    }

    $root = $tree->getRootNode();

    $method_codes = array(
      'lintArrayCombine' => self::LINT_ARRAY_COMBINE,
      'lintUnsafeDynamicString' => self::LINT_UNSAFE_DYNAMIC_STRING,
      'lintDeprecatedFunctions' => self::LINT_DEPRECATED_FUNCTION,
      'lintRaggedClasstreeEdges' => self::LINT_RAGGED_CLASSTREE_EDGE,
      'lintClassExtendsPhobject' => self::LINT_EXTENDS_PHOBJECT,
    );

    foreach ($method_codes as $method => $codes) {
      foreach ((array)$codes as $code) {
        if ($this->isCodeEnabled($code)) {
          call_user_func(array($this, $method), $root);
          break;
        }
      }
    }
  }


/* -(  Setters  )------------------------------------------------------------ */

  public function setDeprecatedFunctions(array $map) {
    $this->deprecatedFunctions = $map;
    return $this;
  }

  public function setDynamicStringClasses(array $map) {
    $this->dynamicStringClasses = $map;
    return $this;
  }

  public function setDynamicStringFunctions(array $map) {
    $this->dynamicStringFunctions = $map;
    return $this;
  }


/* -(  Linter Rules  )------------------------------------------------------- */

  private function lintUnsafeDynamicString(XHPASTNode $root) {
    $safe = $this->dynamicStringFunctions + array(
      'pht' => 0,

      'hsprintf' => 0,
      'jsprintf' => 0,

      'hgsprintf' => 0,

      'csprintf' => 0,
      'vcsprintf' => 0,
      'execx' => 0,
      'exec_manual' => 0,
      'phutil_passthru' => 0,

      'qsprintf' => 1,
      'vqsprintf' => 1,
      'queryfx' => 1,
      'queryfx_all' => 1,
      'queryfx_one' => 1,
    );

    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    $this->lintUnsafeDynamicStringCall($calls, $safe);

    $safe = $this->dynamicStringClasses + array(
      'ExecFuture' => 0,
    );

    $news = $root->selectDescendantsOfType('n_NEW');
    $this->lintUnsafeDynamicStringCall($news, $safe);
  }

  private function lintUnsafeDynamicStringCall(
    AASTNodeList $calls,
    array $safe) {

    $safe = array_combine(
      array_map('strtolower', array_keys($safe)),
      $safe);

    foreach ($calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();
      $param = idx($safe, strtolower($name));

      if ($param === null) {
        continue;
      }

      $parameters = $call->getChildByIndex(1);
      if (count($parameters->getChildren()) <= $param) {
        continue;
      }

      $identifier = $parameters->getChildByIndex($param);
      if (!$identifier->isConstantString()) {
        $this->raiseLintAtNode(
          $call,
          self::LINT_UNSAFE_DYNAMIC_STRING,
          pht(
            "Parameter %d of %s should be a scalar string, ".
            "otherwise it's not safe.",
            $param + 1,
            $name.'()'));
      }
    }
  }

  private function lintArrayCombine(XHPASTNode $root) {
    $function_calls = $this->getFunctionCalls($root, array('array_combine'));

    foreach ($function_calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();
      $parameter_list = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');

      if (count($parameter_list->getChildren()) !== 2) {
        // Wrong number of parameters, but raise that elsewhere if we want.
        continue;
      }

      $first  = $parameter_list->getChildByIndex(0);
      $second = $parameter_list->getChildByIndex(1);

      if ($first->getConcreteString() == $second->getConcreteString()) {
        $this->raiseLintAtNode(
          $call,
          self::LINT_ARRAY_COMBINE,
          pht(
            'Prior to PHP 5.4, `%s` fails when given empty arrays. '.
            'Prefer to write `%s` as `%s`.',
            'array_combine()',
            'array_combine(x, x)',
            'array_fuse(x)'));
      }
    }
  }

  private function lintDeprecatedFunctions(XHPASTNode $root) {
    $map = $this->deprecatedFunctions;
    $function_calls = $this->getFunctionCalls($root, array_keys($map));

    foreach ($function_calls as $call) {
      $name = $call
        ->getChildByIndex(0)
        ->getConcreteString();

      $name = strtolower($name);
      if (empty($map[$name])) {
        continue;
      }

      $this->raiseLintAtNode(
        $call,
        self::LINT_DEPRECATED_FUNCTION,
        $map[$name]);
    }
  }

  private function lintRaggedClasstreeEdges(XHPASTNode $root) {
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
          pht(
            "This class is neither '%s' nor '%s', and does not have ".
            "a docblock marking it '%s'.",
            'final',
            'abstract',
            '@concrete-extensible'));
      }
    }
  }

  private function lintClassExtendsPhobject(XHPASTNode $root) {
    $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');

    foreach ($classes as $class) {
      // TODO: This doesn't quite work for namespaced classes (see T8534).
      $name    = $class->getChildOfType(1, 'n_CLASS_NAME');
      $extends = $class->getChildByIndex(2);

      if ($name->getConcreteString() == 'Phobject') {
        continue;
      }

      if ($extends->getTypeName() == 'n_EMPTY') {
        $this->raiseLintAtNode(
          $class,
          self::LINT_EXTENDS_PHOBJECT,
          pht(
            'Classes should extend from %s or from some other class. '.
            'All classes (except for %s itself) should have a base class.',
            'Phobject',
            'Phobject'));
      }
    }
  }

}
