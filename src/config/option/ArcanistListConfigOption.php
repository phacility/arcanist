<?php

abstract class ArcanistListConfigOption
  extends ArcanistSingleSourceConfigOption {

  final public function getStorageValueFromStringValue($value) {
    try {
      $json_value = phutil_json_decode($value);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilArgumentUsageException(
        pht(
          'Value "%s" is not valid, specify a JSON list: %s',
          $value,
          $ex->getMessage()));
    }

    if (!is_array($json_value) || !phutil_is_natural_list($json_value)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Value "%s" is not valid: expected a list, got "%s".',
          $value,
          phutil_describe_type($json_value)));
    }

    foreach ($json_value as $idx => $item) {
      $this->validateListItem($idx, $item);
    }

    return $json_value;
  }

  final public function getValueFromStorageValue($value) {
    if (!is_array($value)) {
      throw new Exception(pht('Expected a list!'));
    }

    if (!phutil_is_natural_list($value)) {
      throw new Exception(pht('Expected a natural list!'));
    }

    foreach ($value as $idx => $item) {
      $this->validateListItem($idx, $item);
    }

    return $value;
  }

  public function getDisplayValueFromValue($value) {
    return json_encode($value);
  }

  public function getStorageValueFromValue($value) {
    return $value;
  }

  abstract protected function validateListItem($idx, $item);

}
