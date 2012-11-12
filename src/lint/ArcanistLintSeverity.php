<?php

/**
 * Describes the severity of an @{class:ArcanistLintMessage}.
 *
 * @group lint
 */
final class ArcanistLintSeverity {

  const SEVERITY_ADVICE       = 'advice';
  const SEVERITY_AUTOFIX      = 'autofix';
  const SEVERITY_WARNING      = 'warning';
  const SEVERITY_ERROR        = 'error';
  const SEVERITY_DISABLED     = 'disabled';

  public static function getStringForSeverity($severity_code) {
    static $map = array(
      self::SEVERITY_ADVICE   => 'Advice',
      self::SEVERITY_AUTOFIX  => 'Auto-Fix',
      self::SEVERITY_WARNING  => 'Warning',
      self::SEVERITY_ERROR    => 'Error',
      self::SEVERITY_DISABLED => 'Disabled',
    );

    if (!array_key_exists($severity_code, $map)) {
      throw new Exception("Unknown lint severity '{$severity_code}'!");
    }

    return $map[$severity_code];
  }

  public static function isAtLeastAsSevere(
    ArcanistLintMessage $message,
    $level) {

    static $map = array(
      self::SEVERITY_DISABLED => 10,
      self::SEVERITY_ADVICE   => 20,
      self::SEVERITY_AUTOFIX  => 25,
      self::SEVERITY_WARNING  => 30,
      self::SEVERITY_ERROR    => 40,
    );

    $message_sev = $message->getSeverity();
    if (empty($map[$message_sev])) {
      return true;
    }

    return $map[$message_sev] >= idx($map, $level, 0);
  }


}
