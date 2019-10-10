<?php

abstract class ICFlowField extends Phobject {

  private $futureResults = array();
  private $cache;
  private $configuration;

  abstract public function getFieldKey();

  final public function renderTableCell(ICFlowFeature $feature) {
    $values = $this->getValues($feature);
    if ($values) {
      return $this->renderValues($values);
    }
    return '';
  }

  abstract protected function renderValues(array $values);
  abstract public function getValues(ICFlowFeature $feature);

  public function getSummary() {
    return null;
  }

  public function isDefaultField() {
    return true;
  }

  final public function isEnabled() {
    return idx($this->getConfiguration(), 'enabled');
  }

  public function getDefaultFieldOrder() {
    return 0;
  }

  final public function getFieldOrder() {
    return idx($this->getConfiguration(), 'order');
  }

  final protected function getDefaultConfiguration() {
    return array(
      'enabled' => $this->isDefaultField(),
      'order' => $this->getDefaultFieldOrder(),
    );
  }

  public function validateOption($option, $value) {
    throw new Exception(pht(
      'No config option named "%s" exists.',
      $option));
  }

  protected function getOptions() {
    return array();
  }

  protected function getOptionValue($option) {
    $info = idx($this->getOptions(), $option);
    if (!$info) {
      throw new Exception(pht(
        'No config option named "%s" exists.',
        $option));
    }
    $default = idx($info, 'default');
    return idx($this->getConfiguration(), $option, $default);
  }

  final public function getConfiguration() {
    if (!$this->configuration) {
      return $this->getDefaultConfiguration();
    }
    return $this->configuration;
  }

  final public function loadConfiguration(array $configuration) {
    $this->configuration = array_merge(
      $this->getConfiguration(),
      $configuration);
    return $this;
  }

  private function getCache() {
    if (!$this->cache) {
      $this->cache = ic_standard_cache(
        ic_data_cache('flow'),
        $this->getFieldKey(),
        128,
        false);
    }
    return $this->cache;
  }

  protected function cacheGet($key, $default = null) {
    $rval = $this->getCache()->getKey($key);
    return $rval !== null ? $rval : $default;
  }

  protected function cacheSet($key, $value) {
    $this->getCache()->setKey($key, $value);
  }

  protected function getFutures(ICFlowWorkspace $workspace) {
    return array();
  }

  private function setFutureResult($key, $value) {
    $this->futureResults[$key] = $value;
    return $this;
  }

  protected function getFutureResult($key, $default = null) {
    return idx($this->futureResults, $key, $default);
  }

  public function getTableColumn() {
    return array('title' => '');
  }

  final public static function getAllFieldKeys() {
    $fields = (new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getFieldKey')
      ->execute();
    return array_keys($fields);
  }

  final public static function newField($key) {
    $fields = (new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getFieldKey')
      ->execute();
    $field = idx($fields, $key);
    if (!$field) {
      $valid_fields = implode(', ', array_keys($fields));
      throw new Exception(
        pht(
          'No Flow field exists with key "%s". Valid keys are: %s.',
          $key,
          $valid_fields));
    }

    return (clone $field);
  }

  final public static function resolveFutures(
    array $fields,
    ICFlowWorkspace $workspace) {

    assert_instances_of($fields, __CLASS__);
    $fields = mpull($fields, null, 'getFieldKey');
    $futures = array();
    $profiler = PhutilServiceProfiler::getInstance();
    $all_id = $profiler->beginServiceCall(array(
      'type' => 'flow-futures',
    ));
    foreach ($fields as $field_key => $field) {
      $id = $profiler->beginServiceCall(array(
        'type' => "flow-futures:{$field_key}",
      ));
      foreach ($field->getFutures($workspace) as $future_key => $future) {
        $field_future_key = "{$field_key}:{$future_key}";
        $futures[$field_future_key] = $future;
      }
      $profiler->endServiceCall($id, array());
    }
    $iterator = new FutureIterator($futures);
    foreach ($iterator as $field_future_key => $future) {
      $key_parts = explode(':', $field_future_key);
      $field_key = array_shift($key_parts);
      $future_key = implode(':', $key_parts);
      $field = $fields[$field_key];
      $field->setFutureResult($future_key, $future->resolve());
    }
    $profiler->endServiceCall($all_id, array());
  }

  final public function renderInformation() {
    $option_summaries = array();
    foreach ($this->getOptions() as $option => $info) {
      $option_summaries[] = tsprintf(
        "    **%s**\n\n%B\n\n      value: %s\n\n",
        $option,
        phutil_console_wrap($info['summary'], 6),
        (string)$this->getOptionValue($option));
    }
    $options_summary = '';
    if ($option_summaries) {
      $options_summary = tsprintf(
        "  **%s**\n\n%R",
        pht('OPTIONS'),
        implode('', $option_summaries));
    }
    if ($this->isEnabled()) {
      $state = tsprintf('<bg:green>** %s **</bg>', pht('ENABLED'));
    } else {
      $state = tsprintf('<bg:yellow>** %s **</bg>', pht('DISABLED'));
    }
    $summary = '';
    if ($this->getSummary()) {
      $summary = tsprintf(
        "\n%B\n",
        phutil_console_wrap($this->getSummary(), 2));
    }
    return tsprintf(
      "**%s** %R\n%B\n%R",
      $this->getFieldKey(),
      $state,
      $summary,
      $options_summary);
  }

}
