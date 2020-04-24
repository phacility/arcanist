<?php

final class ArcanistConfigurationSourceList
  extends Phobject {

  private $sources = array();
  private $configOptions;

  public function addSource(ArcanistConfigurationSource $source) {
    $this->sources[] = $source;
    return $this;
  }

  public function getSources() {
    return $this->sources;
  }

  private function getSourcesWithScopes($scopes) {
    if ($scopes !== null) {
      $scopes = array_fuse($scopes);
    }

    $results = array();
    foreach ($this->getSources() as $source) {
      if ($scopes !== null) {
        $scope = $source->getConfigurationSourceScope();
        if ($scope === null) {
          continue;
        }
        if (!isset($scopes[$scope])) {
          continue;
        }
      }

      $results[] = $source;
    }

    return $results;
  }

  public function getWritableSourceFromScope($scope) {
    $sources = $this->getSourcesWithScopes(array($scope));

    $writable = array();
    foreach ($sources as $source) {
      if (!$source->isWritableConfigurationSource()) {
        continue;
      }

      $writable[] = $source;
    }

    if (!$writable) {
      throw new Exception(
        pht(
          'Unable to write configuration: there is no writable configuration '.
          'source in the "%s" scope.',
          $scope));
    }

    if (count($writable) > 1) {
      throw new Exception(
        pht(
          'Unable to write configuration: more than one writable source '.
          'exists in the "%s" scope.',
          $scope));
    }

    return head($writable);
  }

  public function getConfig($key) {
    $option = $this->getConfigOption($key);
    $values = $this->getStorageValueList($key);
    return $option->getValueFromStorageValueList($values);
  }

  public function getConfigFromScopes($key, array $scopes) {
    $option = $this->getConfigOption($key);
    $values = $this->getStorageValueListFromScopes($key, $scopes);
    return $option->getValueFromStorageValueList($values);
  }

  public function getStorageValueList($key) {
    return $this->getStorageValueListFromScopes($key, null);
  }

  private function getStorageValueListFromScopes($key, $scopes) {
    $values = array();

    foreach ($this->getSourcesWithScopes($scopes) as $source) {
      if ($source->hasValueForKey($key)) {
        $value = $source->getValueForKey($key);
        $values[] = new ArcanistConfigurationSourceValue(
          $source,
          $source->getValueForKey($key));
      }
    }

    return $values;
  }

  public function getConfigOption($key) {
    $options = $this->getConfigOptions();

    if (!isset($options[$key])) {
      throw new Exception(
        pht(
          'Configuration option ("%s") is unrecognized. You can only read '.
          'recognized configuration options.',
          $key));
    }

    return $options[$key];
  }

  public function setConfigOptions(array $config_options) {
    assert_instances_of($config_options, 'ArcanistConfigOption');

    $config_options = mpull($config_options, null, 'getKey');
    $this->configOptions = $config_options;

    return $this;
  }

  public function getConfigOptions() {
    if ($this->configOptions === null) {
      throw new PhutilInvalidStateException('setConfigOptions');
    }

    return $this->configOptions;
  }

  public function validateConfiguration(ArcanistRuntime $runtime) {
    $options = $this->getConfigOptions();

    $aliases = array();
    foreach ($options as $key => $option) {
      foreach ($option->getAliases() as $alias) {
        $aliases[$alias] = $key;
      }
    }

    // TOOLSETS: Handle the case where config specifies both a value and an
    // alias for that value. The alias should be ignored and we should emit
    // a warning. This also needs to be implemented when actually reading
    // configuration.

    $value_lists = array();
    foreach ($this->getSources() as $source) {
      $keys = $source->getAllKeys();
      foreach ($keys as $key) {
        $resolved_key = idx($aliases, $key, $key);
        $option = idx($options, $resolved_key);

        // If there's no option object for this config, this value is
        // unrecognized. Sources are free to handle this however they want:
        // for config files we emit a warning; for "--config" we fatal.

        if (!$option) {
          $source->didReadUnknownOption($runtime, $key);
          continue;
        }

        $raw_value = $source->getValueForKey($key);

        // Make sure we can convert whatever value the configuration source is
        // providing into a legitimate runtime value.
        try {
          $value = $raw_value;
          if ($source->isStringSource()) {
            $value = $option->getStorageValueFromStringValue($value);
          }
          $option->getValueFromStorageValue($value);

          $value_lists[$resolved_key][] = new ArcanistConfigurationSourceValue(
            $source,
            $raw_value);
        } catch (Exception $ex) {
          throw new PhutilProxyException(
            pht(
              'Configuration value ("%s") defined in source "%s" is not '.
              'valid.',
              $key,
              $source->getSourceDisplayName()),
            $ex);
        }
      }
    }

    // Make sure each value list can be merged.
    foreach ($value_lists as $key => $value_list) {
      try {
        $options[$key]->getValueFromStorageValueList($value_list);
      } catch (Exception $ex) {
        throw $ex;
      }
    }

  }

}
