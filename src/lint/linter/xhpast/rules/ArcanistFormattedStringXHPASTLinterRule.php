<?php

final class ArcanistFormattedStringXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 54;

  private $printfFunctions = array();

  public function getLintName() {
    return pht('Formatted String');
  }

  public function getLinterConfigurationOptions() {
    return parent::getLinterConfigurationOptions() + array(
      'xhpast.printf-functions' => array(
        'type' => 'optional map<string, int>',
        'help' => pht(
          '`%s`-style functions which take a format string and list of values '.
          'as arguments. The value for the mapping is the start index of the '.
          'function parameters (the index of the format string parameter).',
          'printf()'),
      ),
    );
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'xhpast.printf-functions':
        $this->printfFunctions = $value;
        return;
      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
  }

  public function process(XHPASTNode $root) {
    static $functions = array(
      // Core PHP
      'fprintf' => 1,
      'printf' => 0,
      'sprintf' => 0,
      'vfprintf' => 1,

      // libphutil
      'csprintf' => 0,
      'execx' => 0,
      'exec_manual' => 0,
      'hgsprintf' => 0,
      'hsprintf' => 0,
      'jsprintf' => 0,
      'pht' => 0,
      'phutil_passthru' => 0,
      'qsprintf' => 1,
      'queryfx' => 1,
      'queryfx_all' => 1,
      'queryfx_one' => 1,
      'vcsprintf' => 0,
      'vqsprintf' => 1,
    );

    $function_calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');

    foreach ($function_calls as $call) {
      $name = $call->getChildByIndex(0)->getConcreteString();

      $name = strtolower($name);
      $start = idx($functions + $this->printfFunctions, $name);

      if ($start === null) {
        continue;
      }

      $parameters = $call->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
      $argc = count($parameters->getChildren()) - $start;

      if ($argc < 1) {
        $this->raiseLintAtNode(
          $call,
          pht('This function is expected to have a format string.'));
          continue;
      }

      $format = $parameters->getChildByIndex($start);
      if (!$format->isConstantString()) {

        // TODO: When this parameter is not a constant string, the call may
        // be unsafe. We should make some attempt to warn about this for
        // "qsprintf()" and other security-sensitive functions.

        continue;
      }

      $argv = array($format->evalStatic()) + array_fill(0, $argc, null);

      try {
        xsprintf(array(__CLASS__, 'processXsprintfCallback'), null, $argv);
      } catch (BadFunctionCallException $ex) {
        $this->raiseLintAtNode(
          $call,
          str_replace('xsprintf', $name, $ex->getMessage()));
      } catch (InvalidArgumentException $ex) {
        // Ignore.
      }
    }
  }

  public static function processXsprintfCallback(
    $userdata,
    &$pattern,
    &$pos,
    &$value,
    &$length) {

    if ($value !== null) {
      throw new Exception('Expected dummy value to be null');
    }

    // Turn format "%$pattern" with argument null into format "%s" with
    // argument "%$pattern". This ensures we always provide valid input for
    // sprintf to avoid getting a ValueError when using custom format
    // specifiers.
    $value = '%'.$pattern[$pos];
    $pattern[$pos] = 's';
  }

}
