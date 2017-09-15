<?php

/**
 * Represents the outcome of running a unit test.
 */
final class ArcanistUnitTestResult extends Phobject {

  const RESULT_PASS         = 'pass';
  const RESULT_FAIL         = 'fail';
  const RESULT_SKIP         = 'skip';
  const RESULT_BROKEN       = 'broken';
  const RESULT_UNSOUND      = 'unsound';

  private $namespace;
  private $name;
  private $link;
  private $result;
  private $duration;
  private $userData;
  private $extraData;
  private $coverage;

  public function setNamespace($namespace) {
    $this->namespace = $namespace;
    return $this;
  }

  public function getNamespace() {
    return $this->namespace;
  }

  public function setName($name) {
    $maximum_bytes = 255;
    $actual_bytes = strlen($name);

    if ($actual_bytes > $maximum_bytes) {
      throw new Exception(
        pht(
          'Parameter ("%s") passed to "%s" when constructing a unit test '.
          'message must be a string with a maximum length of %s bytes, but '.
          'is %s bytes in length.',
          $name,
          'setName()',
          new PhutilNumber($maximum_bytes),
          new PhutilNumber($actual_bytes)));
    }
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setLink($link) {
    $this->link = $link;
    return $this;
  }

  public function getLink() {
    return $this->link;
  }

  public function setResult($result) {
    $this->result = $result;
    return $this;
  }

  public function getResult() {
    return $this->result;
  }


  /**
   * Set the number of seconds spent executing this test.
   *
   * Reporting this information can help users identify slow tests and reduce
   * the total cost of running a test suite.
   *
   * Callers should pass an integer or a float. For example, pass `3` for
   * 3 seconds, or `0.125` for 125 milliseconds.
   *
   * @param int|float Duration, in seconds.
   * @return this
   */
  public function setDuration($duration) {
    if (!is_int($duration) && !is_float($duration)) {
      throw new Exception(
        pht(
          'Parameter passed to setDuration() must be an integer or a float.'));
    }
    $this->duration = $duration;
    return $this;
  }


  public function getDuration() {
    return $this->duration;
  }

  public function setUserData($user_data) {
    $this->userData = $user_data;
    return $this;
  }

  public function getUserData() {
    return $this->userData;
  }

  /**
   * "extra data" allows an implementation to store additional key/value
   * metadata along with the result of the test run.
   */
  public function setExtraData(array $extra_data = null) {
    $this->extraData = $extra_data;
    return $this;
  }

  public function getExtraData() {
    return $this->extraData;
  }

  public function setCoverage($coverage) {
    $this->coverage = $coverage;
    return $this;
  }

  public function getCoverage() {
    return $this->coverage;
  }

  /**
   * Merge several coverage reports into a comprehensive coverage report.
   *
   * @param list List of coverage report strings.
   * @return string Cumulative coverage report.
   */
  public static function mergeCoverage(array $coverage) {
    if (empty($coverage)) {
      return null;
    }

    $base = reset($coverage);
    foreach ($coverage as $more_coverage) {
      $base_len = strlen($base);
      $more_len = strlen($more_coverage);

      $len = min($base_len, $more_len);
      for ($ii = 0; $ii < $len; $ii++) {
        if ($more_coverage[$ii] == 'C') {
          $base[$ii] = 'C';
        }
      }

      // If a secondary report has more data, copy all of it over.
      if ($more_len > $base_len) {
        $base .= substr($more_coverage, $base_len);
      }
    }

    return $base;
  }

  public function toDictionary() {
    return array(
      'namespace' => $this->getNamespace(),
      'name' => $this->getName(),
      'link' => $this->getLink(),
      'result' => $this->getResult(),
      'duration' => $this->getDuration(),
      'extra' => $this->getExtraData(),
      'userData' => $this->getUserData(),
      'coverage' => $this->getCoverage(),
    );
  }

  public static function getAllResultCodes() {
    return array(
      self::RESULT_PASS,
      self::RESULT_FAIL,
      self::RESULT_SKIP,
      self::RESULT_BROKEN,
      self::RESULT_UNSOUND,
    );
  }

  public static function getResultCodeName($result_code) {
    $spec = self::getResultCodeSpec($result_code);
    if (!$spec) {
      return null;
    }
    return idx($spec, 'name');
  }

  public static function getResultCodeDescription($result_code) {
    $spec = self::getResultCodeSpec($result_code);
    if (!$spec) {
      return null;
    }
    return idx($spec, 'description');
  }

  private static function getResultCodeSpec($result_code) {
    $specs = self::getResultCodeSpecs();
    return idx($specs, $result_code);
  }

  private static function getResultCodeSpecs() {
    return array(
      self::RESULT_PASS => array(
        'name' => pht('Pass'),
        'description' => pht(
          'The test passed.'),
      ),
      self::RESULT_FAIL => array(
        'name' => pht('Fail'),
        'description' => pht(
          'The test failed.'),
      ),
      self::RESULT_SKIP => array(
        'name' => pht('Skip'),
        'description' => pht(
          'The test was not executed.'),
      ),
      self::RESULT_BROKEN => array(
        'name' => pht('Broken'),
        'description' => pht(
          'The test failed in an abnormal or severe way. For example, the '.
          'harness crashed instead of reporting a failure.'),
      ),
      self::RESULT_UNSOUND => array(
        'name' => pht('Unsound'),
        'description' => pht(
          'The test failed, but this change is probably not what broke it. '.
          'For example, it might have already been failing.'),
      ),
    );
  }


}
