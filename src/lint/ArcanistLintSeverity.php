<?php

/**
 * Describes the severity of an @{class:ArcanistLintMessage}.
 */
final class ArcanistLintSeverity extends Phobject {

  const SEVERITY_ADVICE       = 'advice';
  const SEVERITY_AUTOFIX      = 'autofix';
  const SEVERITY_WARNING      = 'warning';
  const SEVERITY_ERROR        = 'error';
  const SEVERITY_DISABLED     = 'disabled';

  public static function getLintSeverities() {
    return array(
      self::SEVERITY_ADVICE   => pht('Advice'),
      self::SEVERITY_AUTOFIX  => pht('Auto-Fix'),
      self::SEVERITY_WARNING  => pht('Warning'),
      self::SEVERITY_ERROR    => pht('Error'),
      self::SEVERITY_DISABLED => pht('Disabled'),
    );
  }

  public static function getStringForSeverity($severity_code) {
    $map = self::getLintSeverities();

    if (!array_key_exists($severity_code, $map)) {
      throw new Exception(pht("Unknown lint severity '%s'!", $severity_code));
    }

    return $map[$severity_code];
  }

  public static function isAtLeastAsSevere($message_sev, $level) {
    static $map = array(
      self::SEVERITY_DISABLED => 10,
      self::SEVERITY_ADVICE   => 20,
      self::SEVERITY_AUTOFIX  => 25,
      self::SEVERITY_WARNING  => 30,
      self::SEVERITY_ERROR    => 40,
    );

    if (empty($map[$message_sev])) {
      return true;
    }

    return $map[$message_sev] >= idx($map, $level, 0);
  }

}
