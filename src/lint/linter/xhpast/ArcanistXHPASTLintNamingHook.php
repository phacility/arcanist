<?php

/**
 * You can extend this class and set `xhpast.naminghook` in your `.arclint` to
 * have an opportunity to override lint results for symbol names.
 *
 * @task override   Overriding Symbol Name Lint Messages
 * @task util       Name Utilities
 * @task internal   Internals
 */
abstract class ArcanistXHPASTLintNamingHook extends Phobject {


/* -(  Internals  )---------------------------------------------------------- */

  /**
   * The constructor is final because @{class:ArcanistXHPASTLinter} is
   * responsible for hook instantiation.
   *
   * @return this
   * @task internals
   */
  final public function __construct() {
    // <empty>
  }


/* -(  Overriding Symbol Name Lint Messages  )------------------------------- */

  /**
   * Callback invoked for each symbol, which can override the default
   * determination of name validity or accept it by returning $default. The
   * symbol types are: xhp-class, class, interface, function, method, parameter,
   * constant, and member.
   *
   * For example, if you want to ban all symbols with "quack" in them and
   * otherwise accept all the defaults, except allow any naming convention for
   * methods with "duck" in them, you might implement the method like this:
   *
   *   if (preg_match('/quack/i', $name)) {
   *     return 'Symbol names containing "quack" are forbidden.';
   *   }
   *   if ($type == 'method' && preg_match('/duck/i', $name)) {
   *     return null; // Always accept.
   *   }
   *   return $default;
   *
   * @param   string      The symbol type.
   * @param   string      The symbol name.
   * @param   string|null The default result from the main rule engine.
   * @return  string|null Null to accept the name, or a message to reject it
   *                      with. You should return the default value if you don't
   *                      want to specifically provide an override.
   * @task override
   */
  abstract public function lintSymbolName($type, $name, $default);


/* -(  Name Utilities  )----------------------------------------------------- */

  /**
   * Returns true if a symbol name is UpperCamelCase.
   *
   * @param string Symbol name.
   * @return bool True if the symbol is UpperCamelCase.
   * @task util
   */
  public static function isUpperCamelCase($symbol) {
    return preg_match('/^[A-Z][A-Za-z0-9]*$/', $symbol);
  }

  /**
   * Returns true if a symbol name is lowerCamelCase.
   *
   * @param string Symbol name.
   * @return bool True if the symbol is lowerCamelCase.
   * @task util
   */
  public static function isLowerCamelCase($symbol) {
    return preg_match('/^[a-z][A-Za-z0-9]*$/', $symbol);
  }

  /**
   * Returns true if a symbol name is UPPERCASE_WITH_UNDERSCORES.
   *
   * @param string Symbol name.
   * @return bool True if the symbol is UPPERCASE_WITH_UNDERSCORES.
   * @task util
   */
  public static function isUppercaseWithUnderscores($symbol) {
    return preg_match('/^[A-Z0-9_]+$/', $symbol);
  }

  /**
   * Returns true if a symbol name is lowercase_with_underscores.
   *
   * @param string Symbol name.
   * @return bool True if the symbol is lowercase_with_underscores.
   * @task util
   */
  public static function isLowercaseWithUnderscores($symbol) {
    return preg_match('/^[a-z0-9_]+$/', $symbol);
  }

  /**
   * Strip non-name components from PHP function symbols. Notably, this discards
   * the "__" magic-method signifier, to make a symbol appropriate for testing
   * with methods like @{method:isLowerCamelCase}.
   *
   * @param   string Symbol name.
   * @return  string Stripped symbol.
   * @task util
   */
  public static function stripPHPFunction($symbol) {
    switch ($symbol) {
      case '__assign_concat':
      case '__call':
      case '__callStatic':
      case '__clone':
      case '__concat':
      case '__construct':
      case '__debugInfo':
      case '__destruct':
      case '__get':
      case '__invoke':
      case '__isset':
      case '__set':
      case '__set_state':
      case '__sleep':
      case '__toString':
      case '__unset':
      case '__wakeup':
        return preg_replace('/^__/', '', $symbol);

      default:
        return $symbol;
    }
  }

  /**
   * Strip non-name components from PHP variable symbols. Notably, this discards
   * the "$", to make a symbol appropriate for testing with methods like
   * @{method:isLowercaseWithUnderscores}.
   *
   * @param string Symbol name.
   * @return string Stripped symbol.
   * @task util
   */
  public static function stripPHPVariable($symbol) {
    return preg_replace('/^\$/', '', $symbol);
  }

}
