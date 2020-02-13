<?php

/**
 * Get information about the current execution environment.
 */
final class PhutilExecutionEnvironment extends Phobject {

  public static function getOSXVersion() {
    if (php_uname('s') != 'Darwin') {
      return null;
    }

    return php_uname('r');
  }

  /**
   * If the PHP configuration setting "variables_order" does not include "E",
   * the `$_ENV` superglobal is not populated with the containing environment.
   * For details, see T12071.
   *
   * This can be fixed by adding "E" to the configuration, but we can also
   * repair it ourselves by re-executing a subprocess with the configuration
   * option defined to include "E". This is clumsy, but saves users from
   * needing to go find and edit their PHP files.
   *
   * @return void
   */
  public static function repairMissingVariablesOrder() {
    $variables_order = ini_get('variables_order');
    $variables_order = strtoupper($variables_order);

    if (strpos($variables_order, 'E') !== false) {
      // The "variables_order" option already has "E", so we don't need to
      // repair $_ENV.
      return;
    }

    list($env) = execx(
      'php -d variables_order=E -r %s',
      'echo json_encode($_ENV);');
    $env = phutil_json_decode($env);

    $_ENV = $_ENV + $env;
  }

}
