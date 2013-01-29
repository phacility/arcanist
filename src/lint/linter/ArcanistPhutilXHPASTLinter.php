<?php

/**
 * @group linter
 */
final class ArcanistPhutilXHPASTLinter extends ArcanistBaseXHPASTLinter {

  const LINT_PHT_WITH_DYNAMIC_STRING = 1;
  const LINT_ARRAY_COMBINE           = 2;

  private $xhpastLinter;

  public function setXHPASTLinter(ArcanistXHPASTLinter $linter) {
    $this->xhpastLinter = $linter;
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
      self::LINT_PHT_WITH_DYNAMIC_STRING => 'Use of pht() on Dynamic String',
      self::LINT_ARRAY_COMBINE           => 'array_combine() Unreliable',
    );
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_ARRAY_COMBINE => ArcanistLintSeverity::SEVERITY_WARNING,
    );
  }

  public function getLinterName() {
    return 'PHLXHP';
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

    $this->lintPHT($root);
    $this->lintArrayCombine($root);
  }


  private function lintPHT($root) {
    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();
      if (strcasecmp($name, 'pht') != 0) {
        continue;
      }

      $parameters = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
      if (!$parameters->getChildren()) {
        continue;
      }

      $identifier = $parameters->getChildByIndex(0);
      if ($this->isConstantString($identifier)) {
        continue;
      }

      $this->raiseLintAtNode(
        $call,
        self::LINT_PHT_WITH_DYNAMIC_STRING,
        "The first parameter of pht() can be only a scalar string, ".
          "otherwise it can't be extracted.");
    }
  }

  private function isConstantString(XHPASTNode $node) {
    $value = $node->getConcreteString();

    switch ($node->getTypeName()) {
      case 'n_HEREDOC':
        if ($value[3] == "'") { // Nowdoc: <<<'EOT'
          return true;
        }
        $value = preg_replace('/^.+\n|\n.*$/', '', $value);
        break;

      case 'n_STRING_SCALAR':
        if ($value[0] == "'") {
          return true;
        }
        $value = substr($value, 1, -1);
        break;

      case 'n_CONCATENATION_LIST':
        foreach ($node->getChildren() as $child) {
          if ($child->getTypeName() == 'n_OPERATOR') {
            continue;
          }
          if (!$this->isConstantString($child)) {
            return false;
          }
        }
        return true;

      default:
        return false;
    }

    return preg_match('/^((?>[^$\\\\]*)|\\\\.)*$/s', $value);
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

}
