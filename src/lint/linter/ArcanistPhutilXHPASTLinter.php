<?php

/**
 * @group linter
 */
final class ArcanistPhutilXHPASTLinter extends ArcanistBaseXHPASTLinter {

  const LINT_ARRAY_COMBINE           = 2;
  const LINT_DEPRECATED_FUNCTION     = 3;
  const LINT_UNSAFE_DYNAMIC_STRING   = 4;

  private $xhpastLinter;
  private $deprecatedFunctions = array();
  private $dynamicStringFunctions = array();
  private $dynamicStringClasses = array();

  public function setXHPASTLinter(ArcanistXHPASTLinter $linter) {
    $this->xhpastLinter = $linter;
    return $this;
  }

  public function setDeprecatedFunctions($map) {
    $this->deprecatedFunctions = $map;
    return $this;
  }

  public function setDynamicStringFunctions($map) {
    $this->dynamicStringFunctions = $map;
    return $this;
  }

  public function setDynamicStringClasses($map) {
    $this->dynamicStringClasses = $map;
    return $this;
  }

  public function setEngine(ArcanistLintEngine $engine) {
    if (!$this->xhpastLinter) {
      throw new Exception(
        'Call setXHPASTLinter() before using ArcanistPhutilXHPASTLinter.');
    }
    $this->xhpastLinter->setEngine($engine);
    return parent::setEngine($engine);
  }

  public function getLintNameMap() {
    return array(
      self::LINT_ARRAY_COMBINE           => 'array_combine() Unreliable',
      self::LINT_DEPRECATED_FUNCTION     => 'Use of Deprecated Function',
      self::LINT_UNSAFE_DYNAMIC_STRING   => 'Unsafe Usage of Dynamic String',
    );
  }

  public function getLintSeverityMap() {
    $warning = ArcanistLintSeverity::SEVERITY_WARNING;
    return array(
      self::LINT_ARRAY_COMBINE           => $warning,
      self::LINT_DEPRECATED_FUNCTION     => $warning,
      self::LINT_UNSAFE_DYNAMIC_STRING   => $warning,
    );
  }

  public function getLinterName() {
    return 'PHLXHP';
  }

  public function getCacheVersion() {
    return 2;
  }

  public function willLintPaths(array $paths) {
    $this->xhpastLinter->willLintPaths($paths);
  }

  public function lintPath($path) {
    $tree = $this->xhpastLinter->getXHPASTTreeForPath($path);
    if (!$tree) {
      return;
    }

    $root = $tree->getRootNode();

    $this->lintArrayCombine($root);
    $this->lintUnsafeDynamicString($root);
    $this->lintDeprecatedFunctions($root);
  }


  private function lintUnsafeDynamicString($root) {
    $safe = $this->dynamicStringFunctions + array(
      'pht' => 0,

      'hsprintf' => 0,

      'csprintf' => 0,
      'vcsprintf' => 0,
      'execx' => 0,
      'exec_manual' => 0,
      'phutil_passthru' => 0,

      'qsprintf' => 1,
      'vqsprintf' => 1,
      'queryfx' => 1,
      'vqueryfx' => 1,
      'queryfx_all' => 1,
      'vqueryfx_all' => 1,
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
          "Parameter ".($param + 1)." of {$name}() should be a scalar string, ".
            "otherwise it's not safe.");
      }
    }
  }


  private function lintArrayCombine($root) {
    $function_calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($function_calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();
      if (strcasecmp($name, 'array_combine') == 0) {
        $parameter_list = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
        if (count($parameter_list->getChildren()) !== 2) {
          // Wrong number of parameters, but raise that elsewhere if we want.
          continue;
        }

        $first = $parameter_list->getChildByIndex(0);
        $second = $parameter_list->getChildByIndex(1);

        if ($first->getConcreteString() == $second->getConcreteString()) {
          $this->raiseLintAtNode(
            $call,
            self::LINT_ARRAY_COMBINE,
            'Prior to PHP 5.4, array_combine() fails when given empty '.
            'arrays. Prefer to write array_combine(x, x) as array_fuse(x).');
        }
      }
    }
  }

  private function lintDeprecatedFunctions($root) {
    $map = $this->deprecatedFunctions + array(
      'phutil_render_tag' =>
        'The phutil_render_tag() function is deprecated and unsafe. '.
        'Use phutil_tag() instead.',
    );

    $function_calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($function_calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();

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

}
