<?php

/**
 * A "linter standard" is a collection of linter rules with associated
 * severities and configuration.
 *
 * Basically, a linter standard allows a set of linter rules and configuration
 * to be easily reused across multiple repositories without duplicating the
 * contents of the `.arclint` file (and the associated maintenance costs in
 * keeping changes to this file synchronized).
 */
abstract class ArcanistLinterStandard extends Phobject {

  /**
   * Returns a unique identifier for the linter standard.
   *
   * @return string
   */
  abstract public function getKey();

  /**
   * Returns a human-readable name for the linter standard.
   *
   * @return string
   */
  abstract public function getName();

  /**
   * Returns a human-readable description for the linter standard.
   *
   * @return string
   */
  abstract public function getDescription();

  /**
   * Checks whether the linter standard supports a specified linter.
   *
   * @param  ArcanistLinter  The linter which is being configured.
   * @return bool            True if the linter standard supports the specified
   *                         linter, otherwise false.
   */
  abstract public function supportsLinter(ArcanistLinter $linter);

  /**
   * Get linter configuration.
   *
   * Returns linter configuration which is passed to
   * @{method:ArcanistLinter::setLinterConfigurationValue}.
   *
   * @return map<string, wild>
   */
  public function getLinterConfiguration() {
    return array();
  }

  /**
   * Get linter severities.
   *
   * Returns linter severities which are passed to
   * @{method:ArcanistLinter::addCustomSeverityMap}.
   *
   * @return map
   */
  public function getLinterSeverityMap() {
    return array();
  }

  /**
   * Load a linter standard by key.
   *
   * @param  string
   * @param  ArcanistLinter
   * @return ArcanistLinterStandard
   */
  final public static function getStandard($key, ArcanistLinter $linter) {
    $standards = self::loadAllStandardsForLinter($linter);

    if (empty($standards[$key])) {
      throw new ArcanistUsageException(
        pht(
          'No such linter standard. Available standards are: %s.',
          implode(', ', array_keys($standards))));
    }

    return $standards[$key];
  }

  /**
   * Load all linter standards.
   *
   * @return list<ArcanistLinterStandard>
   */
  final public static function loadAllStandards() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getKey')
      ->execute();
  }

  /**
   * Load all linter standards which support a specified linter.
   *
   * @param  ArcanistLinter
   * @return list<ArcanistLinterStandard>
   */
  final public static function loadAllStandardsForLinter(
    ArcanistLinter $linter) {

    $all_standards = self::loadAllStandards();
    $standards = array();

    foreach ($all_standards as $standard) {
      if ($standard->supportsLinter($linter)) {
        $standards[$standard->getKey()] = $standard;
      }
    }

    return $standards;
  }

}
