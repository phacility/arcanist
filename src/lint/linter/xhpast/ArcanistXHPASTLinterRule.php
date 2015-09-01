<?php

abstract class ArcanistXHPASTLinterRule extends Phobject {

  private $linter = null;
  private $lintID = null;

  protected $version;
  protected $windowsVersion;

  final public static function loadAllRules() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getLintID')
      ->execute();
  }

  final public function getLintID() {
    if ($this->lintID === null) {
      $class = new ReflectionClass($this);

      $const = $class->getConstant('ID');
      if ($const === false) {
        throw new Exception(
          pht(
            '`%s` class `%s` must define an ID constant.',
            __CLASS__,
            get_class($this)));
      }

      if (!is_int($const)) {
        throw new Exception(
          pht(
            '`%s` class `%s` has an invalid ID constant. '.
            'ID must be an integer.',
            __CLASS__,
            get_class($this)));
      }

      $this->lintID = $const;
    }

    return $this->lintID;
  }

  abstract public function getLintName();

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  public function getLinterConfigurationOptions() {
    return array(
      'xhpast.php-version' => array(
        'type' => 'optional string',
        'help' => pht('PHP version to target.'),
      ),
      'xhpast.php-version.windows' => array(
        'type' => 'optional string',
        'help' => pht('PHP version to target on Windows.'),
      ),
    );
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'xhpast.php-version':
        $this->version = $value;
        return;

      case 'xhpast.php-version.windows':
        $this->windowsVersion = $value;
        return;
    }
  }

  abstract public function process(XHPASTNode $root);

  final public function setLinter(ArcanistXHPASTLinter $linter) {
    $this->linter = $linter;
    return $this;
  }

  /**
   * Statically evaluate a boolean value from an XHP tree.
   *
   * TODO: Improve this and move it to XHPAST proper?
   *
   * @param  string  The "semantic string" of a single value.
   * @return mixed   `true` or `false` if the value could be evaluated
   *                 statically; `null` if static evaluation was not possible.
   */
  protected function evaluateStaticBoolean($string) {
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

  protected function getConcreteVariableString(XHPASTNode $var) {
    $concrete = $var->getConcreteString();
    // Strip off curly braces as in `$obj->{$property}`.
    $concrete = trim($concrete, '{}');
    return $concrete;
  }

  // These methods are proxied to the @{class:ArcanistLinter}.

  final public function getActivePath() {
    return $this->linter->getActivePath();
  }

  final public function getOtherLocation($offset, $path = null) {
    return $this->linter->getOtherLocation($offset, $path);
  }

  final protected function raiseLintAtNode(
    XHPASTNode $node,
    $desc,
    $replace = null) {

    return $this->linter->raiseLintAtNode(
      $node,
      $this->getLintID(),
      $desc,
      $replace);
  }

  final public function raiseLintAtOffset(
    $offset,
    $desc,
    $text = null,
    $replace = null) {

    return $this->linter->raiseLintAtOffset(
      $offset,
      $this->getLintID(),
      $desc,
      $text,
      $replace);
  }

  final protected function raiseLintAtPath($desc) {
    return $this->linter->raiseLintAtPath($this->getLintID(), $desc);
  }

  final protected function raiseLintAtToken(
    XHPASTToken $token,
    $desc,
    $replace = null) {

    return $this->linter->raiseLintAtToken(
      $token,
      $this->getLintID(),
      $desc,
      $replace);
  }

/* -(  Utility  )------------------------------------------------------------ */

  /**
   * Retrieve all anonymous closure(s).
   *
   * Returns all descendant nodes which represent an anonymous function
   * declaration.
   *
   * @param  XHPASTNode    Root node.
   * @return AASTNodeList
   */
  protected function getAnonymousClosures(XHPASTNode $root) {
    $func_decls = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
    $nodes      = array();

    foreach ($func_decls as $func_decl) {
      if ($func_decl->getChildByIndex(2)->getTypeName() == 'n_EMPTY') {
        $nodes[] = $func_decl;
      }
    }

    return AASTNodeList::newFromTreeAndNodes($root->getTree(), $nodes);
  }

  /**
   * Retrieve all calls to some specified function(s).
   *
   * Returns all descendant nodes which represent a function call to one of the
   * specified functions.
   *
   * @param  XHPASTNode    Root node.
   * @param  list<string>  Function names.
   * @return AASTNodeList
   */
  protected function getFunctionCalls(XHPASTNode $root, array $function_names) {
    $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    $nodes = array();

    foreach ($calls as $call) {
      $node = $call->getChildByIndex(0);
      $name = strtolower($node->getConcreteString());

      if (in_array($name, $function_names)) {
        $nodes[] = $call;
      }
    }

    return AASTNodeList::newFromTreeAndNodes($root->getTree(), $nodes);
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
